<?php

namespace App\Http\Controllers;

use App\Exports\ChatTableExport;
use App\Services\ToolCallExecutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\Core\QueryService;
use Exception;

class AgenticChatbotController extends Controller
{
    private int $maxToolLoops = 20;
    private int $maxHistory = 20;

    private \App\Services\ToolCallExecutor $toolExecutor;
    private \App\Services\Core\QueryService $queryService;

    public function __construct(\App\Services\ToolCallExecutor $toolExecutor, \App\Services\Core\QueryService $queryService)
    {
        $this->toolExecutor = $toolExecutor;
        $this->queryService = $queryService;
    }

    public function index()
    {
        $user = Auth::user();
        $availableModels = $user->aiModels()
            ->where('ai_models.is_active', true)
            ->where('user_ai_models.is_enabled', true)
            ->with('provider')
            ->get();

        return view('chatbot', compact('availableModels'));
    }

    public function send(Request $request)
    {
        set_time_limit(0); // Prevent PHP script timeout

        $request->validate([
            'message' => 'required|string',
            'model_id' => 'nullable|exists:ai_models,id'
        ]);

        $user = Auth::user();
        $message = $request->message;
        $selectedModelId = $request->model_id;

        if (!$selectedModelId) {
            $selectedModel = $user->aiModels()
                ->where('ai_models.is_active', true)
                ->where('user_ai_models.is_enabled', true)
                ->first();

            if (!$selectedModel) {
                return response()->json(['error' => 'Tidak ada model AI yang aktif. Silakan aktifkan di Pengaturan.'], 400);
            }
            $selectedModelId = $selectedModel->id;
        } else {
            $selectedModel = $user->aiModels()->with('provider')->findOrFail($selectedModelId);
        }

        $chatSessionId = $request->chat_session_id;

        $apiKey = $user->aiKeys()->where('provider_id', $selectedModel->provider_id)->where('is_active', true)->first();

        if (!$apiKey) {
            return response()->json(['error' => 'Mohon maaf, akses layanan analisis AI belum dikonfigurasi. Harap hubungi Administrator Sistem.'], 403);
        }

        $allowedDatabases = [];
        if ($user->is_admin) {
            // FIX: Admin mendapat schema NYATA dari setiap database agar AI tidak
            // menebak schema_name='*' saat memanggil describe_table / execute_query.
            // Tanpa ini, MANDATORY_SCHEMA_USAGE di getSchemaInfo kosong karena
            // array_filter membuang semua key '*', sehingga AI looping dengan
            // wildcard schema dan memerlukan 3-4 loop ekstra sebelum eksekusi query.
            $conns = \App\Models\DatabaseConnection::active()->get();
            foreach ($conns as $c) {
                $tables = $c->getTables(); // [{schema_name, table_name, description}]
                if (empty($tables)) {
                    // Fallback ke wildcard jika DB tidak mengembalikan tabel apapun
                    $allowedDatabases[$c->database] = ['*' => [['name' => '*', 'description' => '']]];
                    continue;
                }
                foreach ($tables as $t) {
                    $sch = $t['schema_name'];
                    $tbl = $t['table_name'];
                    $desc = $t['description'] ?? '';
                    $allowedDatabases[$c->database][$sch][] = ['name' => $tbl, 'description' => $desc];
                }
            }
        } elseif ($user->roleModel) {
            if (method_exists($user->roleModel, 'getAllowedDatabases')) {
                $allowedDatabases = $user->roleModel->getAllowedDatabases();
            } else {
                foreach ($user->roleModel->permissions ?? [] as $perm) {
                    $conn = $perm->databaseConnection;
                    if (!$conn || !$conn->is_active)
                        continue;

                    $db = $conn->database;
                    $schema = $perm->schema_name;
                    $tbl = $perm->table_name;

                    if ($db === '*') {
                        $conns2 = \App\Models\DatabaseConnection::active()->get();
                        foreach ($conns2 as $c) {
                            $tables2 = $c->getTables();
                            if (empty($tables2)) {
                                $allowedDatabases[$c->database] = ['*' => [['name' => '*', 'description' => '']]];
                                continue;
                            }
                            foreach ($tables2 as $t2) {
                                $allowedDatabases[$c->database][$t2['schema_name']][] = ['name' => $t2['table_name'], 'description' => $t2['description'] ?? ''];
                            }
                        }
                        continue;
                    }
                    if (!$db)
                        continue;

                    if (!isset($allowedDatabases[$db]))
                        $allowedDatabases[$db] = [];
                    $schemaKey = ($schema && $schema !== '*') ? $schema : '*';

                    if (!isset($allowedDatabases[$db][$schemaKey]))
                        $allowedDatabases[$db][$schemaKey] = [];

                    if ($tbl && $tbl !== '*') {
                        $allowedDatabases[$db][$schemaKey][] = $tbl;
                    } elseif ($schemaKey !== '*') {
                        $allowedDatabases[$db][$schemaKey][] = '*';
                    }
                }
            }
        } else {
            Log::warning("[Agentic] User ID {$user->id} has no roleModel and is not admin. allowedDatabases will be empty.");
        }

        if ($chatSessionId) {
            $session = ChatSession::where('user_id', $user->id)->findOrFail($chatSessionId);
            $history = ChatMessage::where('chat_session_id', $session->id)
                ->orderBy('created_at', 'asc')
                ->get()
                ->toArray();
        } else {
            $session = ChatSession::create([
                'user_id' => $user->id,
                'title' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
            ]);
            $chatSessionId = $session->id;
            $history = [];
        }

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $message,
            'tool_results' => null,
        ]);

        if (!empty($history) && $session->title === 'New Chat') {
            $session->update(['title' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')]);
        }

        $scopeLimited = (bool) ($user->analysis_scope_limited ?? true);

        $systemPrompt = $this->buildSystemPrompt($allowedDatabases, $scopeLimited);

        $messages = $this->buildMessages($systemPrompt, $history, $message);
        $maxTokens = $user->max_tokens ?? 32768;

        session_write_close();

        // Paksa PHP flush buffer secara implicit agar SSE tidak menunggu buffer penuh
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_implicit_flush(true);

        return response()->stream(
            function () use ($messages, $apiKey, $selectedModel, $allowedDatabases, $chatSessionId, $maxTokens) {
                try {
                    $this->runAgenticLoop($messages, $apiKey, $selectedModel, $allowedDatabases, $chatSessionId, $maxTokens);
                } catch (\Throwable $e) {
                    Log::error("[Agentic] Fatal Stream Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                    $this->streamText("⚠️ Maaf, terjadi masalah internal saat mengeksekusi AI: " . $e->getMessage());
                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0)
                        ob_flush();
                    flush();
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ]
        );
    }

    private function runAgenticLoop(array $messages, $apiKey, $model, array $allowedDatabases = [], $chatSessionId = null, $maxTokens = null): void
    {
        $systemPrompt = '';
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemPrompt = $m['content'];
                break;
            }
        }
        if ($chatSessionId) {
            echo "data: " . json_encode(['chat_session_id' => $chatSessionId]) . "\n\n";
        }
        echo "data: " . json_encode(['chunk' => '', 'status' => 'thinking']) . "\n\n";
        if (ob_get_level() > 0)
            ob_flush();
        flush();

        $this->toolExecutor->setAllowedTables($allowedDatabases);
        $tools = ToolCallExecutor::getToolDefinitions();
        $loopCount = 0;
        $allTurnToolResults = [];
        $textContent = '';

        // ── Fix #1: Track tool terakhir yang dieksekusi ──────────────────────
        // Digunakan untuk heuristik streaming: hanya stream jika tool terakhir
        // adalah "terminal tool" (execute_query / get_erp_guidance) yang hampir
        // pasti menghasilkan final answer, bukan tool intermediate seperti
        // get_database_schema_info / search_schema / describe_table.
        $lastExecutedToolName = null;

        // Tool-tool yang hampir pasti menghasilkan final answer setelah dieksekusi.
        $terminalTools = [
            'execute_query',
            'get_erp_guidance',
            'get_erp_menu_navigation',
            'fetch_erp_guidance_from_web',
        ];

        // Track berapa kali execute_query dipanggil di turn ini.
        // Streaming hanya boleh aktif jika execute_query sudah dipanggil >= 2x
        // (artinya ada query exploratory sebelumnya dan ini kemungkinan query final),
        // ATAU jika execute_query dipanggil 1x dan tidak ada SELECT DISTINCT di SQL-nya
        // (query langsung ke data akhir, bukan query probe nilai kolom).
        $executeQueryCount = 0;
        $lastExecutedSql = '';

        // Hard limit probe query: jika model sudah melakukan >= 2 SELECT DISTINCT
        // berturut-turut tanpa GROUP BY, inject reminder paksa agar langsung ke query utama.
        // Ini mencegah model terus eksplor tanpa batas.
        $probeQueryCount = 0;
        $maxProbeQueries = 3; // maksimal 3 probe query sebelum dipaksa ke query utama

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            $providerCode = strtolower($apiKey->provider->code ?? '');
            $isGroq = $providerCode === 'groq' || str_contains($apiKey->provider->base_url ?? '', 'groq.com');
            Log::info("[Agentic] Loop #{$loopCount} - Model: " . $model->model_name);

            // ── STRATEGI TRUE SSE STREAMING ──────────────────────────────────
            // Streaming HANYA aktif jika loop ini kemungkinan besar adalah FINAL ANSWER.
            //
            // Untuk execute_query: streaming aktif hanya jika:
            //   - Ini bukan query probe (SELECT DISTINCT ... LIMIT x tanpa GROUP BY)
            //   - execute_query sudah pernah dipanggil sebelumnya di turn ini
            // Ini mencegah streaming aktif di loop SELECT DISTINCT yang hasilnya
            // masih akan dipakai sebagai input untuk query berikutnya.
            // ────────────────────────────────────────────────────────────────────
            $isProbeQuery = $executeQueryCount > 0
                && stripos($lastExecutedSql, 'SELECT DISTINCT') !== false
                && stripos($lastExecutedSql, 'GROUP BY') === false;

            $useStreaming = !empty($textContent) || ($loopCount > 1 && !empty($allTurnToolResults));

            try {
                if ($useStreaming) {
                    // ── STREAMING MODE: langsung kirim token ke browser ──
                    // streamFinalResponseFromApi() mengembalikan teks lengkap
                    // DAN sudah mengirim setiap token via SSE ke browser.
                    Log::info("[Agentic] Loop #{$loopCount} using STREAMING mode (tool results available)");
                    try {
                        $response = $this->streamFinalResponseFromApi(
                            $messages,
                            $tools,
                            $apiKey,
                            $model,
                            $maxTokens,
                            $systemPrompt,
                            $loopCount
                        );
                    } catch (\RuntimeException $e) {
                        if ($e->getMessage() === '__RATE_LIMIT__') {
                            // Otomatis tandai key ini limit_reached di database
                            $apiKey->update(['limit_reached' => true]);
                            Log::warning("[Agentic] Rate limit hit — marked api_key_id={$apiKey->id} as limit_reached.");
                            $this->streamText("Mohon maaf, layanan analisis AI telah mencapai batas kuota penggunaan untuk periode ini. Silakan hubungi Administrator Sistem untuk memperbarui kuota layanan, atau coba kembali beberapa saat lagi.");
                            echo "data: [DONE]\n\n";
                            if (ob_get_level() > 0)
                                ob_flush();
                            flush();
                            return;
                        }
                        throw $e;
                    }

                    // Ambil data dari response stream
                    $assistantMsg = $response['choices'][0]['message'] ?? [];
                    $finishReason = $response['choices'][0]['finish_reason'] ?? 'stop';
                    $toolCalls = $assistantMsg['tool_calls'] ?? [];
                    $textContent = $assistantMsg['content'] ?? '';

                    // Jika stream menghasilkan teks (bukan tool call) DAN tidak ada tool calls → selesai
                    if (!empty(trim($textContent)) && empty($toolCalls)) {
                        $textContent = $this->stripThinkingLeakage($textContent);
                        $textContent = $this->processContentForCharts($textContent, $allTurnToolResults);

                        if ($chatSessionId) {
                            ChatMessage::create([
                                'chat_session_id' => $chatSessionId,
                                'role' => 'assistant',
                                'content' => $textContent,
                                'tool_results' => !empty($allTurnToolResults) ? $allTurnToolResults : null
                            ]);
                        }
                        echo "data: [DONE]\n\n";
                        if (ob_get_level() > 0)
                            ob_flush();
                        flush();
                        return;
                    }

                    // Hanya fallback ke non-streaming jika benar-benar kosong total (teks & tool)
                    if (empty($textContent) && empty($toolCalls)) {
                        Log::warning("[Agentic] Streaming returned empty, falling back to non-streaming for loop #{$loopCount}");
                        $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount);
                    }
                } else {
                    $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount);
                }
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === '__RATE_LIMIT__') {
                    // Otomatis tandai key ini limit_reached di database
                    $apiKey->update(['limit_reached' => true]);
                    Log::warning("[Agentic] Rate limit hit — marked api_key_id={$apiKey->id} as limit_reached.");
                    $this->streamText("Mohon maaf, layanan analisis AI telah mencapai batas kuota penggunaan untuk periode ini. Silakan hubungi Administrator Sistem untuk memperbarui kuota layanan, atau coba kembali beberapa saat lagi. / We apologize, the AI analysis service has reached its usage limit. Please contact your System Administrator or try again later.");
                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0)
                        ob_flush();
                    flush();
                    return;
                }
                Log::error("[Agentic] Critical Exception in callAiApi: " . $e->getMessage());
                $response = null;
            } catch (\Throwable $e) {
                Log::error("[Agentic] Critical Exception in callAiApi: " . $e->getMessage());
                $response = null;
            }

            if (!$response || !isset($response['choices'][0]['message'])) {
                $this->streamText("Infrastruktur analisis sedang mengalami gangguan. Harap hubungi Administrator. / Analytical infrastructure is experiencing issues. Please contact Administrator.");
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0)
                    ob_flush();
                flush();
                return;
            }

            $assistantMsg = $response['choices'][0]['message'];
            $finishReason = $response['choices'][0]['finish_reason'] ?? 'stop';
            $toolCalls = $assistantMsg['tool_calls'] ?? [];
            $textContent = $assistantMsg['content'] ?? '';

            $providerCodeCheck = strtolower($apiKey->provider->code ?? '');
            if ($providerCodeCheck === 'gemini' && empty($textContent) && empty($toolCalls) && $loopCount <= 2) {
                Log::warning("[Agentic] Gemini empty response at loop #{$loopCount} (likely thinking-only). Injecting output reminder.");
                array_pop($messages);
                $messages[] = [
                    'role' => 'user',
                    'content' => '[SYSTEM]: Mohon berikan jawaban atau panggil tool yang sesuai. Jangan hanya berpikir tanpa output.',
                ];
                continue;
            }

            Log::info("[Agentic] Loop #{$loopCount} response — finish_reason={$finishReason} tool_calls=" . count($toolCalls) . " text_len=" . strlen($textContent));

            $providerCodeLive = strtolower($apiKey->provider->code ?? '');
            if ($providerCodeLive === 'gemini' && !empty($toolCalls)) {
                $assistantMsg['_is_live_gemini_response'] = true;
            }

            $messages[] = $assistantMsg;

            if (empty($toolCalls) && preg_match('/SELECT\s+.*\s+FROM\s+/i', $textContent)) {
                Log::warning("[Agentic] Detected raw SQL in text content. Intercepting and retrying...");
                $messages[] = [
                    'role' => 'user',
                    'content' => "[SYSTEM REMINDER]: Anda baru saja mengirimkan query SQL mentah ke dalam teks jawaban. Ini DILARANG. Jangan pernah tunjukkan query SQL kepada Bapak/Ibu user. Gunakan tool 'execute_query' jika Anda ingin mengambil data, lalu sajikan hasilnya dalam Bahasa Indonesia bisnis yang sopan menggunakan 'smart_table'. Silakan perbaiki respon Anda sekarang."
                ];
                continue;
            }

            $totalToolCallsThisTurn = count($allTurnToolResults);
            if (empty($toolCalls) && $totalToolCallsThisTurn === 0 && $loopCount === 1) {
                $alreadyTriedPhrases = [
                    'tidak ada data',
                    'tidak ditemukan',
                    'sudah memeriksa',
                    'tidak tersedia di database',
                    'tidak ada tabel',
                    'tidak ada informasi',
                    'no data',
                    'not found',
                    'not available',
                    'no table',
                    'no information',
                    'i have checked',
                    'after checking',
                    'setelah memeriksa',
                ];
                $alreadyTried = false;
                foreach ($alreadyTriedPhrases as $phrase) {
                    if (stripos($textContent, $phrase) !== false) {
                        $alreadyTried = true;
                        break;
                    }
                }

                $outOfDomainPhrases = [
                    'saya tidak dapat membantu dengan pertanyaan',
                    'saya tidak dapat menjawab pertanyaan tentang',
                    'pertanyaan ini di luar kapasitas saya',
                    'tidak dalam kapasitas saya untuk',
                    'i cannot assist with this type',
                    'this question is outside my scope',
                    'i am not able to answer questions about',
                    'i can only assist with business data',
                ];
                $isOutOfDomain = false;
                if (!$alreadyTried) {
                    foreach ($outOfDomainPhrases as $phrase) {
                        if (stripos($textContent, $phrase) !== false) {
                            $isOutOfDomain = true;
                            break;
                        }
                    }
                }

                if ($isOutOfDomain) {
                    Log::warning("[Agentic] FIX#3 — False 'out-of-domain' detected at loop #{$loopCount} before any successful tool call. Injecting schema recovery.");

                    $schemaHints = [];
                    foreach ($allowedDatabases as $dbCode => $schemas) {
                        $realSchemas = array_filter(array_keys($schemas), fn($s) => $s !== '*');
                        foreach ($realSchemas as $s) {
                            $schemaHints[] = "database_code='{$dbCode}', schema_name='{$s}'";
                        }
                    }
                    $schemaHintText = !empty($schemaHints)
                        ? implode('; ', $schemaHints)
                        : 'Panggil get_database_schema_info untuk melihat daftar schema';

                    $messages[] = [
                        'role' => 'user',
                        'content' => implode("\n", [
                            "[SYSTEM CORRECTION — WAJIB DIBACA]:",
                            "Pertanyaan user adalah pertanyaan DATA BISNIS yang VALID. Jangan tolak.",
                            "Anda BELUM berhasil mengambil data apapun. Ini bukan pertanyaan di luar domain.",
                            "",
                            "SCHEMA YANG BENAR UNTUK DIGUNAKAN:",
                            $schemaHintText,
                            "",
                            "LANGKAH WAJIB SEKARANG:",
                            "1. Panggil get_database_schema_info untuk melihat daftar tabel yang tersedia.",
                            "2. Gunakan schema_name yang EKSAK dari hasil di atas (JANGAN gunakan '*').",
                            "3. Panggil describe_table dengan schema_name yang benar.",
                            "4. Jalankan execute_query dan sajikan hasilnya kepada user.",
                            "",
                            "DILARANG memberikan jawaban penolakan sebelum mencoba tool.",
                        ]),
                    ];
                    continue;
                }
            }

            if (empty($toolCalls) || in_array($finishReason, ['stop', 'end_turn'])) {
                $providerCodeFmt = strtolower($apiKey->provider->code ?? '');
                $baseUrlFmt = $apiKey->provider->base_url ?? '';
                $isOpenRouterFmt = $providerCodeFmt === 'openrouter' || str_contains($baseUrlFmt, 'openrouter.ai');

                if ($isOpenRouterFmt && !empty($allTurnToolResults) && $loopCount <= $this->maxToolLoops - 2) {
                    $isSingleValue = false;
                    $singleValueNumber = null;
                    foreach ($allTurnToolResults as $tr) {
                        $rows = $tr['data']['rows'] ?? [];
                        $cols = $tr['data']['columns'] ?? [];
                        if (count($rows) === 1 && count($cols) === 1) {
                            $isSingleValue = true;
                            $firstRow = reset($rows);
                            $singleValueNumber = is_array($firstRow) ? reset($firstRow) : $firstRow;
                            break;
                        }
                    }

                    if ($isSingleValue) {
                        $hasSmartTable = str_contains($textContent, '```smart_table');
                        if ($hasSmartTable || strlen(trim($textContent)) < 250) {
                            Log::warning('[Agentic] OpenRouter single-value result — injecting inline answer reminder. Value: ' . $singleValueNumber);
                            $messages[] = [
                                'role' => 'user',
                                'content' =>
                                    '[SYSTEM FORMAT CORRECTION — WAJIB DIIKUTI]:' . "\n" .
                                    'Hasil query hanya mengandung SATU ANGKA TUNGGAL: ' . $singleValueNumber . "\n" .
                                    '❌ DILARANG KERAS menggunakan smart_table untuk hasil 1 baris 1 kolom.' . "\n" .
                                    '✅ WAJIB sebutkan angkanya langsung dalam kalimat naratif.' . "\n\n" .
                                    'Contoh BENAR: "**Perusahaan memiliki total ' . $singleValueNumber . ' cabang.**"' . "\n" .
                                    'Contoh SALAH: membuat tabel hanya untuk menampilkan angka ' . $singleValueNumber . "\n\n" .
                                    'Format respons yang benar:' . "\n" .
                                    '**Ringkasan Eksekutif**: Sebutkan angka ' . $singleValueNumber . ' secara langsung dalam 1-2 kalimat tebal.' . "\n" .
                                    '**Insight Strategis**: 2-3 poin insight bisnis.' . "\n" .
                                    chr(0xF0) . chr(0x9F) . chr(0x92) . chr(0xA1) . ' **Rekomendasi Prompt Selanjutnya**: 3-4 pertanyaan lanjutan.' . "\n\n" .
                                    'JANGAN gunakan smart_table. Jawab langsung dalam narasi.',
                            ];
                            continue;
                        }
                    }

                    if (!$isSingleValue && strlen(trim($textContent)) < 250) {
                        Log::warning('[Agentic] OpenRouter short response (' . strlen(trim($textContent)) . ' chars) — injecting format reminder.');

                        $toolSummary = '';
                        foreach ($allTurnToolResults as $tr) {
                            if (isset($tr['data']['rows_returned'])) {
                                $count = $tr['data']['rows_returned'];
                                $toolSummary .= "Data berhasil diambil ({$count} baris). ";
                            } elseif (!empty($tr['data']['rows'])) {
                                $toolSummary .= 'Data berhasil diambil. ';
                            }
                        }

                        $messages[] = [
                            'role' => 'user',
                            'content' =>
                                '[SYSTEM FORMAT REMINDER]:' . "\n" .
                                $toolSummary . "\n" .
                                'Anda WAJIB menyajikan jawaban LENGKAP dalam format berikut:' . "\n\n" .
                                '**Ringkasan Eksekutif**' . "\n" .
                                'Tulis 1-2 kalimat cetak tebal yang menyebutkan angka kunci.' . "\n\n" .
                                '**Insight Strategis**' . "\n" .
                                'Tulis 2-3 poin insight bisnis yang relevan.' . "\n\n" .
                                chr(0xF0) . chr(0x9F) . chr(0x92) . chr(0xA1) . ' **Rekomendasi Prompt Selanjutnya:**' . "\n" .
                                '1. "[pertanyaan lanjutan spesifik]"' . "\n" .
                                '2. "[pertanyaan analisis lebih dalam]"' . "\n" .
                                '3. "[pertanyaan tren atau perbandingan]"' . "\n" .
                                '4. "[pertanyaan cross-analysis]"' . "\n\n" .
                                'Gunakan Bahasa Indonesia formal. JANGAN hanya menulis 1 kalimat pendek.',
                        ];
                        continue;
                    }
                }

                $finalContent = trim($textContent);
                if (empty($finalContent)) {
                    $finalContent = "Mohon maaf, sistem tidak memberikan respon. Silakan coba pertanyaan lain.";
                }

                $finalContent = $this->stripThinkingLeakage($finalContent);
                $processedContent = $this->processContentForCharts($finalContent, $allTurnToolResults);

                if ($chatSessionId) {
                    ChatMessage::create([
                        'chat_session_id' => $chatSessionId,
                        'role' => 'assistant',
                        'content' => $processedContent,
                        'tool_results' => !empty($allTurnToolResults) ? $allTurnToolResults : null
                    ]);
                }

                $this->streamText($processedContent);
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0)
                    ob_flush();
                flush();
                return;
            }

            $toolCallCount = count($toolCalls);
            $useParallel = $toolCallCount > 1;

            $processedCalls = [];
            foreach ($toolCalls as $toolCall) {
                $toolCallId = $toolCall['id'] ?? ('call_' . uniqid());
                $toolName = $toolCall['function']['name'] ?? '';
                $argsRaw = $toolCall['function']['arguments'] ?? '{}';
                $arguments = is_string($argsRaw) ? (json_decode($argsRaw, true) ?? []) : $argsRaw;

                $countWithoutWhereWarning = false;
                if ($toolName === 'execute_query') {
                    $sqlToCheck = strtolower(trim($arguments['sql'] ?? ''));
                    $hasCount = preg_match('/\bcount\s*\(/i', $sqlToCheck);
                    $hasWhere = preg_match('/\bwhere\b/i', $sqlToCheck);
                    $hasGroup = preg_match('/\bgroup by\b/i', $sqlToCheck);
                    if ($hasCount && !$hasWhere && !$hasGroup) {
                        Log::warning("[Agentic] COUNT without WHERE detected: {$toolName}");
                        $countWithoutWhereWarning = true;
                    }
                }

                $processedCalls[] = [
                    'id' => $toolCallId,
                    'name' => $toolName,
                    'arguments' => $arguments,
                    'countWithoutWhereWarning' => $countWithoutWhereWarning,
                ];

                // ── IMMEDIATE FEEDBACK: Kirim 'running' status SEKARANG ──
                echo "data: " . json_encode([
                    'tool_call' => [
                        'id' => $toolCallId,
                        'name' => $toolName,
                        'arguments' => $arguments,
                        'status' => 'running',
                    ]
                ]) . "\n\n";
            }
            if (ob_get_level() > 0) ob_flush();
            flush();

            $executedResults = [];
            if ($useParallel) {
                Log::info("[Agentic] Parallel tool execution: {$toolCallCount} tools");
                $fibers = [];
                foreach ($processedCalls as $call) {
                    Log::info("[Agentic] Starting Fiber for tool: {$call['name']}");
                    $tName = $call['name'];
                    $tArgs = $call['arguments'];
                    $fiber = new \Fiber(function () use ($tName, $tArgs, $isGroq): string {
                        return $this->toolExecutor->execute($tName, $tArgs, $isGroq);
                    });
                    $fiber->start();
                    $fibers[] = ['fiber' => $fiber, 'call' => $call];
                }
                foreach ($fibers as $item) {
                    $fiber = $item['fiber'];
                    while (!$fiber->isTerminated()) {
                        $fiber->resume();
                    }
                    $executedResults[] = [
                        'call' => $item['call'],
                        'result' => $fiber->getReturn(),
                    ];
                }
            } else {
                $call = $processedCalls[0];
                Log::info("[Agentic] Executing Tool: {$call['name']}");
                $executedResults[] = [
                    'call' => $call,
                    'result' => $this->toolExecutor->execute($call['name'], $call['arguments'], $isGroq),
                ];
            }

            foreach ($executedResults as $execItem) {
                $call = $execItem['call'];
                $toolCallId = $call['id'];
                $toolName = $call['name'];
                $arguments = $call['arguments'];

                $countWithoutWhereWarning = $call['countWithoutWhereWarning'];
                $toolResult = $execItem['result'];

                $decodedRes = json_decode($toolResult, true);

                if (is_array($decodedRes) && $toolName === 'execute_query') {
                    $currencyCols = $decodedRes['currency_columns'] ?? [];
                }

                $aiContent = $toolResult;
                if (is_array($decodedRes) && isset($decodedRes['rows']) && count($decodedRes['rows']) > 50) {
                    $aiContent = json_encode([
                        'rows_returned' => count($decodedRes['rows']),
                        'columns' => $decodedRes['columns'] ?? [],
                        'currency_columns' => $decodedRes['currency_columns'] ?? [],
                        'rows' => array_slice($decodedRes['rows'], 0, 50),
                        'instruction' => "ANALYST NOTE: Results are truncated for display. If the user asked for a 'total' or 'summary', you MUST ensure your SQL uses SUM() and GROUP BY only on identity columns (like branch name) to avoid seeing individual rows. NEVER repeat technical 'truncated' strings to the user."
                    ]);
                }

                $frontendResult = [
                    'tool_name' => $toolName,
                    'data' => $decodedRes ?: $toolResult,
                    'currency_columns' => is_array($decodedRes) ? ($decodedRes['currency_columns'] ?? []) : [],
                    'label' => is_array($decodedRes) ? ($decodedRes['label'] ?? '') : '',
                ];

                echo "data: " . json_encode([
                    'tool_call' => [
                        'id' => $toolCallId,
                        'name' => $toolName,
                        'arguments' => $arguments,
                        'status' => 'success',
                        'result' => $frontendResult,
                    ]
                ]) . "\n\n";
                if (ob_get_level() > 0)
                    ob_flush();
                flush();

                $allTurnToolResults[] = $frontendResult;
                $lastExecutedToolName = $toolName;

                if ($toolName === 'execute_query') {
                    $executeQueryCount++;
                    $lastExecutedSql = $call['arguments']['sql'] ?? '';
                    $currentIsProbe = stripos($lastExecutedSql, 'SELECT DISTINCT') !== false
                        && stripos($lastExecutedSql, 'GROUP BY') === false;

                    if ($currentIsProbe) {
                        $probeQueryCount++;
                        $isProbeLimitReached = ($currentIsProbe && $probeQueryCount >= $maxProbeQueries);
                        if ($isProbeLimitReached) {
                            Log::warning("[Agentic] PROBE LIMIT reached (2/2). Injecting force-execute reminder.");
                            if (!empty($executedResults)) {
                                $lastIdx = count($executedResults) - 1;
                                $executedResults[$lastIdx]['result'] .= "\n\n**MANDATORY_AI_ACTION**: Limit query probe (SELECT DISTINCT) tercapai (2/2). Anda dilarang melakukan probe lagi. Segera eksekusi query utama berdasarkan nilai yang sudah ditemukan.";
                            }
                        }
                    }
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name' => $toolName,
                    'content' => $aiContent,
                    'decoded_data' => $decodedRes,
                    '_is_live_gemini_response' => true, // tandai sebagai live agar Gemini kirim functionResponse
                ];

                // ── Inject reminder STATUS FILTER setelah tool result masuk ke messages ──
                // PENTING: Harus setelah tool result, bukan sebelum — Mistral/OpenAI tidak
                // mengizinkan user message di antara assistant tool_calls dan tool results.
                if (!empty($countWithoutWhereWarning)) {
                    $actualCount = null;
                    if (is_array($decodedRes) && !empty($decodedRes['rows'])) {
                        $firstRow = reset($decodedRes['rows']);
                        $actualCount = is_array($firstRow) ? reset($firstRow) : $firstRow;
                    }
                    $messages[] = [
                        'role' => 'user',
                        'content' => implode("\n", [
                            '[SYSTEM NOTE — STATUS FILTER CHECK]:',
                            'Query COUNT menghasilkan: ' . ($actualCount ?? '?') . '.',
                            'Jika describe_table SUDAH dipanggil sebelumnya dan TIDAK ada kolom status aktif → angka ini sudah benar, LANGSUNG sajikan ke user tanpa komentar teknis.',
                            'Jika ADA kolom status aktif yang terlewat → jalankan ulang COUNT dengan WHERE filter status.',
                            'DILARANG menulis reasoning teknis di response. Langsung sajikan hasilnya.',
                        ]),
                    ];
                }
            } // end foreach $executedResults
            if (ob_get_level() > 0)
                ob_flush();
            flush();
        }
    }

    public function getSessions(Request $request)
    {
        return ChatSession::where('user_id', $request->user()->id)->orderBy('updated_at', 'desc')->get(['id', 'title', 'updated_at']);
    }

    public function getSession($id)
    {
        $session = ChatSession::where('user_id', Auth::user()->id)->findOrFail($id);

        $limit = (int) request('limit', 50);
        $before = request('before');

        $query = ChatMessage::where('chat_session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit + 1);

        if ($before) {
            $query->where('created_at', '<', $before);
        }

        $messages = $query->get();
        $hasMore = $messages->count() > $limit;
        $messages = $messages->take($limit)->sortBy('created_at')->values();
        $oldestCursor = $hasMore ? ($messages->first()?->created_at?->toISOString() ?? null) : null;

        return response()->json([
            'session' => $session,
            'history' => $messages,
            'pagination' => [
                'has_more' => $hasMore,
                'oldest_cursor' => $oldestCursor,
            ]
        ]);
    }

    public function deleteSession($id)
    {
        ChatSession::where('user_id', Auth::user()->id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function updateSessionTitle(Request $request, $id)
    {
        $request->validate(['title' => 'required|string|max:255']);
        ChatSession::where('user_id', Auth::user()->id)->findOrFail($id)->update(['title' => $request->title]);
        return response()->json(['success' => true]);
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'headers' => 'required|array',
            'rows' => 'required|array',
            'title' => 'nullable|string|max:100',
            'currencyColumns' => 'nullable|array',
        ]);

        $headers = $request->input('headers', []);
        $rows = $request->input('rows', []);
        $title = $request->input('title', 'Data Export');
        $currencyColumns = $request->input('currencyColumns', []);
        $filename = $request->input('filename', 'export-' . now()->format('Ymd-His') . '.xlsx');

        $normalizedRows = array_map(function ($row) {
            return is_array($row) ? array_values($row) : (array) $row;
        }, $rows);

        $export = new \App\Exports\ChatTableExport($headers, $normalizedRows, $title, null, $currencyColumns);

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }

    public function exportPdf(Request $request)
    {
        $request->validate([
            'headers' => 'required|array',
            'rows' => 'required|array',
            'title' => 'nullable|string|max:100',
            'currencyColumns' => 'nullable|array',
        ]);

        $headers = $request->input('headers', []);
        $rows = $request->input('rows', []);
        $title = $request->input('title', 'Data Export');
        $currencyColumns = $request->input('currencyColumns', []);
        $filename = $request->input('filename', 'export-' . now()->format('Ymd-His') . '.pdf');

        $normalizedRows = array_map(function ($row) {
            return is_array($row) ? array_values($row) : (array) $row;
        }, $rows);

        // AI sepenuhnya menentukan currency — langsung pakai currencyColumns dari request
        $isCurrencyHeader = function (string $header) use ($currencyColumns): bool {
            return in_array($header, $currencyColumns);
        };

        $columnTypes = array_map(function ($header) use ($isCurrencyHeader) {
            if ($isCurrencyHeader($header))
                return 'currency';
            if (preg_match('/(^qty$|^jumlah$|^count$|^no$|^no\.$)/i', $header))
                return 'number';
            return 'text';
        }, $headers);

        $chartImage = $request->input('chartImage', null);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.pdf-table', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $normalizedRows,
            'currencyColumns' => $currencyColumns,
            'generatedAt' => now()->format('d M Y H:i'),
            'colCount' => count($headers),
            'fontSize' => 10, // Font size 100% (10pt) stabil
            'chartImage' => $chartImage,
            'columnTypes' => $columnTypes,
        ]);

        // Hitung lebar kertas dinamis (A4 landscape min 842pt, atau n_kolom * 130pt)
        $paperWidth = max(842, count($headers) * 130);
        $pdf->setPaper([0, 0, $paperWidth, 595]); // 595pt adalah tinggi standar A4

        return $pdf->download($filename);
    }

    private function callAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens = 32768, string $systemPrompt = '', int $loopCount = 1): ?array
    {
        $providerCode = $apiKey->provider->code;
        $maxTokens = $maxTokens ?? 32768;

        $formattedTools = $this->formatToolsForProvider($providerCode, $tools);
        $formattedMessages = $this->formatMessagesForProvider($providerCode, $messages);

        if ($providerCode === 'gemini')
            return $this->callGeminiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount);
        if ($providerCode === 'claude')
            return $this->callClaudeApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        if ($providerCode === 'mistral')
            return $this->callMistralApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        if ($providerCode === 'openai')
            return $this->callOpenAiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);

        return $this->callCustomApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount);
    }

    private function formatToolsForProvider(string $providerCode, array $tools): array
    {
        if (empty($tools))
            return [];

        $normalized = [];
        foreach ($tools as $t) {
            if (isset($t['function'])) {
                $normalized[] = [
                    'name' => $t['function']['name'],
                    'description' => $t['function']['description'] ?? '',
                    'parameters' => $t['function']['parameters'] ?? (object) [],
                ];
            } else {
                $normalized[] = [
                    'name' => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters' => $t['parameters'] ?? (object) [],
                ];
            }
        }

        if ($providerCode === 'gemini') {
            $geminiTools = [];
            foreach ($normalized as $f) {
                $geminiTools[] = [
                    'name' => $f['name'],
                    'description' => $f['description'],
                    'parameters' => $f['parameters'],
                ];
            }
            return [['function_declarations' => $geminiTools]];
        }

        if ($providerCode === 'claude') {
            $claudeTools = [];
            foreach ($normalized as $f) {
                $claudeTools[] = [
                    'name' => $f['name'],
                    'description' => $f['description'],
                    'input_schema' => $f['parameters'],
                ];
            }
            return $claudeTools;
        }

        $standardTools = [];
        foreach ($normalized as $f) {
            $standardTools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $f['name'],
                    'description' => $f['description'],
                    'parameters' => $f['parameters'],
                ],
            ];
        }
        return $standardTools;
    }

    private function formatMessagesForProvider(string $providerCode, array $messages): array
    {
        if ($providerCode === 'gemini') {
            // Gemini format rules:
            //  1. Role hanya 'user' atau 'model'
            //  2. functionResponse WAJIB dalam role 'user'
            //  3. functionCall HANYA dari model sebagai output — DILARANG client kirim ulang
            //  4. Dua role sama berturut-turut → error 400
            //
            // STRATEGI HISTORY:
            //  - assistant + tool_calls (dari DB/history) → kirim hanya TEXT-nya sebagai 'model'
            //    Jika text kosong, kirim placeholder "[Mengambil data...]"
            //  - tool results (dari DB/history) → kirim sebagai 'user' + functionResponse
            //    Tapi jika toolName tidak dikenal Gemini (fake history), kirim sebagai text biasa
            //  - assistant + tool_calls (LIVE, _is_live_gemini_response=true) → kirim functionCall

            $geminiMessages = [];
            $prevRole = null;

            foreach ($messages as $m) {
                if ($m['role'] === 'system')
                    continue;

                $role = $m['role'];

                // ── TOOL RESULT ──────────────────────────────────────────────
                if ($role === 'tool') {
                    $isHistoryTool = empty($m['_is_live_gemini_response'] ?? null);

                    if ($isHistoryTool) {
                        // History tool result: kirim sebagai text ringkasan biasa
                        // supaya Gemini tidak bingung dengan functionResponse tanpa matching functionCall
                        $toolName = $m['name'] ?? 'tool';
                        $rawContent = $m['content'] ?? '';

                        // Pastikan rawContent adalah string sebelum di-decode
                        if (is_array($rawContent)) {
                            $rawContent = json_encode($rawContent);
                        }
                        $decoded = is_string($rawContent) ? (json_decode($rawContent, true) ?? []) : [];

                        // Buat ringkasan singkat — pastikan semua nilai adalah string
                        if (is_array($decoded) && !empty($decoded)) {
                            $rowCount = (int) ($decoded['rows_returned'] ?? count($decoded['rows'] ?? []));
                            // Pastikan columns adalah array of strings, bukan nested arrays
                            $rawCols = $decoded['columns'] ?? [];
                            $cols = implode(', ', array_map(
                                fn($c) => is_string($c) ? $c : (is_array($c) ? json_encode($c) : (string) $c),
                                array_slice($rawCols, 0, 5)
                            ));
                            $summary = "[Konteks: {$toolName} mengambil {$rowCount} baris" . ($cols ? " ({$cols})" : '') . "]";
                        } else {
                            $summary = "[Konteks: {$toolName} selesai]";
                        }

                        $parts = [['text' => $summary]];
                        if ($prevRole === 'user' && !empty($geminiMessages)) {
                            $last = &$geminiMessages[count($geminiMessages) - 1];
                            $last['parts'] = array_merge($last['parts'], $parts);
                        } else {
                            $geminiMessages[] = ['role' => 'user', 'parts' => $parts];
                            $prevRole = 'user';
                        }
                    } else {
                        // Live tool result: kirim sebagai functionResponse (normal flow)
                        $rawContent = $m['content'] ?? '';
                        if (!empty($m['decoded_data']) && is_array($m['decoded_data'])) {
                            $parsedContent = $m['decoded_data'];
                        } elseif (is_string($rawContent)) {
                            $decoded = json_decode($rawContent, true);
                            $parsedContent = is_array($decoded) ? $decoded : ['result' => $rawContent];
                        } else {
                            $parsedContent = is_array($rawContent) ? $rawContent : ['result' => (string) $rawContent];
                        }

                        $parts = [
                            [
                                'functionResponse' => [
                                    'name' => $m['name'] ?? 'tool',
                                    'response' => $parsedContent,
                                ]
                            ]
                        ];
                        if ($prevRole === 'user' && !empty($geminiMessages)) {
                            $last = &$geminiMessages[count($geminiMessages) - 1];
                            $last['parts'] = array_merge($last['parts'], $parts);
                        } else {
                            $geminiMessages[] = ['role' => 'user', 'parts' => $parts];
                            $prevRole = 'user';
                        }
                    }
                    continue;
                }

                // ── ASSISTANT MESSAGE ─────────────────────────────────────────
                if ($role === 'assistant') {
                    $isLive = !empty($m['_is_live_gemini_response']);
                    $parts = [];

                    if (!empty($m['content'])) {
                        $parts[] = ['text' => (string) $m['content']];
                    }

                    if ($isLive && !empty($m['tool_calls'])) {
                        // Live response: kirim functionCall
                        foreach ($m['tool_calls'] as $tc) {
                            $args = $tc['function']['arguments'] ?? '{}';
                            $argsArr = is_string($args) ? json_decode($args, false) : $args;
                            if (!$argsArr || $argsArr === [])
                                $argsArr = new \stdClass();
                            $parts[] = [
                                'functionCall' => [
                                    'name' => $tc['function']['name'],
                                    'args' => $argsArr,
                                ]
                            ];
                        }
                    } elseif (!$isLive && !empty($m['tool_calls']) && empty($parts)) {
                        // History tanpa text: kirim placeholder
                        $toolNames = array_map(fn($tc) => $tc['function']['name'] ?? 'tool', $m['tool_calls']);
                        $parts[] = ['text' => '[Mengambil data: ' . implode(', ', $toolNames) . ']'];
                    }

                    if (empty($parts))
                        continue;

                    if ($prevRole === 'model' && !empty($geminiMessages)) {
                        $last = &$geminiMessages[count($geminiMessages) - 1];
                        $last['parts'] = array_merge($last['parts'], $parts);
                    } else {
                        $geminiMessages[] = ['role' => 'model', 'parts' => $parts];
                        $prevRole = 'model';
                    }
                    continue;
                }

                // ── USER MESSAGE ──────────────────────────────────────────────
                if ($role === 'user') {
                    $parts = [];
                    if (!empty($m['content'])) {
                        $parts[] = ['text' => (string) $m['content']];
                    }
                    if (empty($parts))
                        continue;

                    if ($prevRole === 'user' && !empty($geminiMessages)) {
                        $last = &$geminiMessages[count($geminiMessages) - 1];
                        $last['parts'] = array_merge($last['parts'], $parts);
                    } else {
                        $geminiMessages[] = ['role' => 'user', 'parts' => $parts];
                        $prevRole = 'user';
                    }
                }
            }

            return $geminiMessages;
        }

        if ($providerCode === 'claude') {
            $claudeMessages = [];
            foreach ($messages as $m) {
                if ($m['role'] === 'system')
                    continue;

                if ($m['role'] === 'tool') {
                    $claudeMessages[] = [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'tool_result',
                                'tool_use_id' => $m['tool_call_id'] ?? ('hist_' . uniqid()),
                                'content' => $m['content'] ?? ''
                            ]
                        ]
                    ];
                    continue;
                }

                if ($m['role'] === 'assistant' && !empty($m['tool_calls'])) {
                    $content = [];
                    if (!empty($m['content'])) {
                        $content[] = ['type' => 'text', 'text' => $m['content']];
                    }
                    foreach ($m['tool_calls'] as $tc) {
                        $args = $tc['function']['arguments'] ?? '{}';
                        $content[] = [
                            'type' => 'tool_use',
                            'id' => $tc['id'] ?? ('hist_' . uniqid()),
                            'name' => $tc['function']['name'],
                            'input' => is_string($args) ? (json_decode($args, true) ?? (object) []) : $args
                        ];
                    }
                    $claudeMessages[] = ['role' => 'assistant', 'content' => $content];
                    continue;
                }

                $claudeMessages[] = ['role' => $m['role'], 'content' => $m['content'] ?? ''];
            }
            return $claudeMessages;
        }

        // Strip internal-only fields before sending to standard OpenAI-compatible providers
        // (decoded_data and _is_live_gemini_response are for internal use only)
        $internalFields = ['decoded_data', '_is_live_gemini_response'];
        return array_map(function ($m) use ($internalFields) {
            foreach ($internalFields as $field) {
                unset($m[$field]);
            }
            return $m;
        }, $messages);
    }

    private function buildMessages(string $systemPrompt, array $history, string $userMessage): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        $recentHistory = array_slice($history, -$this->maxHistory);

        foreach ($recentHistory as $msg) {
            $toolResults = $msg['tool_results'] ?? null;
            if ($msg['role'] === 'assistant' && !empty($toolResults)) {
                // Rekonstruksi history tool calls dalam format OpenAI-compatible.
                // Format ini akan dikonversi oleh formatMessagesForProvider() sebelum
                // dikirim ke masing-masing provider.
                //
                // CATATAN untuk Gemini: fakeToolCalls di history harus menggunakan
                // 'arguments' berupa JSON object string '{}' agar saat di-decode
                // menghasilkan stdClass (bukan array kosong []).
                // formatMessagesForProvider() sudah menangani konversi [] -> stdClass.
                $fakeToolCalls = [];
                foreach ($toolResults as $res) {
                    $fakeToolCalls[] = [
                        'id' => 'hist_' . md5($res['tool_name'] . json_encode($res['data'] ?? '')),
                        'type' => 'function',
                        'function' => [
                            'name' => $res['tool_name'] ?? 'query',
                            'arguments' => '{}', // stdClass setelah decode, bukan array
                        ],
                    ];
                }
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $msg['content'] ?? '',
                    'tool_calls' => $fakeToolCalls,
                ];
                foreach ($toolResults as $index => $res) {
                    $toolData = $res['data'] ?? '';
                    $toolContent = is_string($toolData) ? $toolData : json_encode($toolData);
                    if (strlen($toolContent) > 2000) {
                        $decoded = is_array($toolData) ? $toolData : (json_decode($toolContent, true) ?? []);
                        $truncated = [
                            'rows_returned' => $decoded['rows_returned'] ?? '?',
                            'columns' => $decoded['columns'] ?? [],
                            'rows' => array_slice($decoded['rows'] ?? [], 0, 5),
                            '_truncated' => true,
                            '_message' => 'History truncated. Re-query if needed.',
                        ];
                        $toolContent = json_encode($truncated);
                    }
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $fakeToolCalls[$index]['id'] ?? ('hist_' . uniqid()),
                        'name' => $res['tool_name'] ?? 'query',
                        'content' => $toolContent,
                        'decoded_data' => isset($truncated) ? $truncated : $toolData,
                    ];
                    unset($truncated); // Clear for next iteration
                }
            } else {
                $messages[] = ['role' => $msg['role'] ?? 'user', 'content' => $msg['content'] ?? ''];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SYSTEM PROMPT — ADAPTIVE LANGUAGE (AI-DRIVEN DETECTION)
    // AI akan otomatis mendeteksi bahasa user dan menjawab dalam bahasa yang sama.
    // Tidak ada lagi hardcoded Indonesian/English split.
    // ─────────────────────────────────────────────────────────────────────────
    private function buildSystemPrompt(array $allowedDatabases = [], bool $scopeLimited = true): string
    {
        $dbSummaries = [];
        foreach ($allowedDatabases as $dbCode => $schemas) {
            // Cek driver database untuk berikan hint SQL yang tepat
            $dbModel = \App\Models\DatabaseConnection::where('database', $dbCode)->active()->first();
            $driver = $dbModel ? strtoupper($dbModel->driver) : 'UNKNOWN';
            $schemaList = implode(', ', array_filter(array_keys($schemas), fn($s) => $s !== '*'));
            if (empty($schemaList))
                $schemaList = implode(', ', array_keys($schemas));

            if ($dbModel && in_array($dbModel->driver, ['mysql', 'mariadb'])) {
                $dbSummaries[] = "- Kode Database: {$dbCode} | Driver: {$driver} | Format Query: \`table_name\` (tanpa prefix schema)";
            } else {
                $dbSummaries[] = "- Kode Database: {$dbCode} | Driver: {$driver} | Schema: {$schemaList} | Format Query: schema_name.table_name";
            }
        }
        $dbSummaryText = implode(PHP_EOL, $dbSummaries);

        // ── OPTIMASI: SUNTIKKAN TABEL UTAMA (Skipping 1-2 discovery loops) ──
        $mainTablesHint = [];
        try {
            $conns = \App\Models\DatabaseConnection::active()->get();
            foreach ($conns as $conn) {
                // Gunakan method getTables() bawaan model yang sudah menangani koneksi dengan benar
                $tables = $conn->getTables();
                $tableNames = array_slice(array_column($tables, 'table_name'), 0, 15);

                if (!empty($tableNames)) {
                    $mainTablesHint[] = "Database [{$conn->database}]: " . implode(', ', $tableNames);
                }
            }
        } catch (\Exception $e) {
            Log::warning("[Agentic] Failed to fetch table hints for prompt: " . $e->getMessage());
        }
        $tableHintText = !empty($mainTablesHint) ? implode("\n", $mainTablesHint) : "Panggil get_database_schema_info untuk melihat daftar tabel.";

        $currentTime = now()->translatedFormat('l, d F Y H:i');

        $outOfDomainSection = $scopeLimited
            ? "## 🚫 PERTANYAAN DI LUAR DOMAIN (TERAKHIR — HANYA JIKA SUDAH MENCOBA TOOL)\n\nHANYA jika pertanyaan user SUDAH TERBUKTI tidak berkaitan dengan data bisnis atau ERP (misal: resep masakan, gosip artis, ramalan cuaca) DAN tool tidak menghasilkan data yang relevan, barulah balas dengan:\n\n*\"Mohon maaf Bapak/Ibu, saya hanya dapat membantu dalam kapasitas sebagai Analis Data Bisnis dan Konsultan Sistem ERP perusahaan. Untuk pertanyaan tersebut, saya tidak memiliki kewenangan untuk memberikan jawaban. Apakah ada kebutuhan analisis data atau panduan ERP yang dapat saya bantu?\"*\n\n**PENTING: Kalimat penolakan ini DILARANG digunakan jika:**\n- User bertanya tentang data bisnis (cabang, dealer, penjualan, keuangan, stok, dll)\n- Terjadi error database (cari tabel yang benar, jangan tolak)\n- Pertanyaan ambigu (coba tool dulu, baru putuskan)"
            : "## CAKUPAN JAWABAN\n\nAnda bebas membantu user dengan pertanyaan apapun di luar konteks database dan ERP. Tetap utamakan analisis data bisnis jika pertanyaan terkait database, namun Anda BOLEH menjawab pertanyaan umum, pengetahuan umum, dan topik lainnya secara helpful dan informatif.";

        return <<<PROMPT
Anda adalah DataBot, Data Analyst AI ahli untuk MBI (Motor Bisnis Indonesia) dengan **akses langsung ke berbagai database bisnis** melalui alat (tools).

## 🔴 CRITICAL PRIORITY: LANGUAGE MATCHING RULE
1. **AUTOMATICALLY detect user's language and ALWAYS reply in the SAME language.**
2. If user writes in English → Your entire response (Executive Summary, Insights, Recommendations, Error Messages) MUST be in English.
3. Jika user menulis dalam Bahasa Indonesia → Seluruh jawaban Anda WAJIB dalam Bahasa Indonesia.
4. If the user switches language mid-conversation → Immediately switch your output language to match.
5. Failing to match the user's language is a CRITICAL FAILURE of your mission.

Seluruh output (Ringkasan Eksekutif, Insight Strategis, Rekomendasi Prompt, pesan error) WAJIB mengikuti bahasa user. TIDAK ADA pengecualian.

## ⚡ PARALLEL TOOL CALLING
Anda didorong untuk memanggil beberapa tool sekaligus dalam satu giliran jika independen untuk menghemat waktu:
- Panggil `describe_table` untuk beberapa tabel sekaligus jika Anda butuh info banyak tabel.
- Panggil `describe_table` dan `execute_query` (probe) secara bersamaan jika Anda sudah cukup yakin dengan nama tabelnya.
- Jalankan beberapa `execute_query` independen jika Anda butuh data dari beberapa sumber sekaligus.

## IDENTITAS & TUGAS UTAMA

Anda adalah asisten Data Analyst yang HANYA bertugas untuk dua hal:
1. **Analisis data bisnis** — mengakses dan menginterpretasikan data dari database yang tersedia
2. **Panduan sistem ERP** — membantu navigasi dan penggunaan modul ERP perusahaan

## KONTEKS WAKTU (SANGAT PENTING):
- **Tanggal Sekarang**: {$currentTime}
- **Penting**: Hari ini adalah tahun 2026. Analisis data tahun 2025 adalah data historis.

## DATABASE TERSEDIA UNTUK ANDA:
{$dbSummaryText}

## 🔴 INSTRUKSI PERTAMA (EFEKTIF & INSTAN)

**Daftar Tabel Utama (Gunakan Jika Relevan):**
{$tableHintText}

Jika pertanyaan user sudah jelas berkaitan dengan tabel di atas, **LANGSUNG panggil `describe_table`** (Lewati `get_database_schema_info`).
HANYA panggil `get_database_schema_info` jika tabel yang Anda butuhkan tidak ada di daftar atas.

**DAFTAR PERTANYAAN BISNIS YANG PASTI VALID (WAJIB DIJAWAB DENGAN TOOL):**
- "total cabang", "jumlah cabang", "berapa cabang", "cabang"
- "total dealer", "berapa dealer", "dealer aktif"
- "data penjualan", "omset", "revenue", "netto"
- "HPP", "harga pokok", "profit", "laba", "margin"
- "stok", "inventory", "barang"
- "laporan", "rekap", "ringkasan", "summary"
- "piutang", "hutang", "receivable", "payable"
- "keuangan", "finance", "neraca", "balance"
- Semua pertanyaan singkat berisi angka, kuantitas, atau nama entitas bisnis

**ATURAN EMAS: JANGAN PERNAH TOLAK PERTANYAAN TANPA MENCOBA TOOL TERLEBIH DAHULU.**

Jika Anda tidak yakin apakah pertanyaan berkaitan dengan bisnis → PANGGIL TOOL DULU, baru putuskan.
Jika Anda mendapat error database → JANGAN TOLAK, cari tabel yang benar dengan `search_schema`.
Jika schema salah → GUNAKAN `get_database_schema_info` untuk mendapat schema yang benar.

## 🔴 ATURAN COUNTING — WAJIB UNTUK SEMUA QUERY COUNT

Saat user bertanya **"berapa", "total", "jumlah"** entitas (cabang, dealer, pelanggan, produk, dll):

1. **WAJIB gunakan `describe_table` dulu** → cek apakah ada kolom status aktif (misal: `status`, `is_active`, `aktif`, `status_aktif`)
2. **Jika ada kolom status aktif** → WAJIB tanya user atau gunakan filter aktif secara default:
   ```sql
   SELECT COUNT(*) FROM schema.tabel WHERE status = 'aktif'  -- atau nilai aktif yang sesuai
   ```
3. **Jika tidak ada kolom status** → gunakan `COUNT(*)` tanpa filter
4. **JANGAN gunakan** `COUNT(nama_kolom)` karena akan melewati baris NULL — selalu `COUNT(*)`
5. **Konsistensi kritis**: query yang berbeda pada tabel yang sama HARUS menggunakan filter status yang sama agar hasilnya konsisten

## PERSONA & GAYA BAHASA (WAJIB DIIKUTI)
- **Persona**: Data Analyst Ahli, profesional, objektif, dan sangat teliti. Anda adalah "Executive Assistant" yang memberikan hasil akhir, bukan kronologi kerja.
- **Bahasa**: Bahasa Bisnis yang Profesional (Sesuai dengan bahasa yang dideteksi dari user).
- **Sapaan**: Selalu sapa pengguna dengan "Bapak/Ibu".

## 🔴 LARANGAN KERAS: JANGAN BOCORKAN "ISI DAPUR" TEKNIS
User adalah level eksekutif yang TIDAK mengerti database. DILARANG KERAS menyebutkan hal berikut dalam jawaban Anda:
1. **DILARANG** menyebut "nama tabel" (misal: view_data_penjualan_xxx).
2. **DILARANG** menyebut "nama kolom" database (misal: nama_propinsi_cabang).
3. **DILARANG** menyebut istilah teknis agentic (misal: "hasil probe", "query SQL", "menjalankan query", "mengecek database").
4. **DILARANG** menyebut "0 baris" atau "query mengembalikan data kosong".
5. **DILARANG** meminta izin untuk "melanjutkan pengecekan" atau "mencoba query lain". Lakukan saja secara mandiri selama masih dalam batas turn Anda.

**CONTOH BAHASA BISNIS YANG BENAR:**
- Salah (Teknis): "Query saya pada tabel view_xxx mengembalikan 0 baris untuk Sumatera Utara."
- Benar (Bisnis): "Mohon maaf Bapak/Ibu, saat ini belum terdapat catatan transaksi penjualan untuk wilayah Sumatera Utara pada periode tersebut."

- Salah (Teknis): "Saya akan mencoba mengecek kota Medan untuk memverifikasi data."
- Benar (Bisnis): "Saya akan melakukan penelusuran lebih mendalam pada tingkat kota untuk memastikan rincian datanya."

## 🔴 ATURAN TERPENTING #1 — JANGAN TEBAK NAMA KOLOM

Kata bisnis dari user ("HPP", "netto", "diskon", "profit", "omzet") adalah **ISTILAH BISNIS**, BUKAN nama kolom database.

Sebelum `execute_query`, **WAJIB** panggil `describe_table` untuk mendapatkan nama kolom EKSAK.

**Checkpoint wajib sebelum tulis query**: *"Setiap nama kolom yang saya gunakan, apakah berasal dari hasil describe_table tadi?"*
- YA → lanjut execute_query
- TIDAK / RAGU → panggil describe_table dulu, baru execute_query

Nama kolom yang DILARANG ditebak (harus dari describe_table):
- Jangan gunakan: `hpp`, `total_hpp`, `hrg_pokok_tebakan` → nama asli harus dari describe_table
- Jangan gunakan: `netto`, `total_netto_tebakan` → nama asli harus dari describe_table  
- Jangan gunakan: `diskon`, `total_disc_tebakan` → nama asli harus dari describe_table
- Jangan gunakan: `periode_bulan`, `periode_tahun` → kolom tanggal asli harus dari describe_table
- `profit`/`laba` hampir tidak pernah kolom tersimpan — hitung: `SUM(col_net) - SUM(col_hpp)`

## 🔴 ATURAN TERPENTING #1B — RESOLVE NAMA CABANG/ENTITAS SEBELUM QUERY

User sering menyebut nama cabang/dealer/entitas dengan ejaan tidak persis ("hm yamin", "HM Yamin", "yamin", dll). Nama yang tersimpan di database bisa berbeda ("HM. YAMIN", "HM YAMIN BC", dll).

**WAJIB LAKUKAN 2 LANGKAH INI saat user menyebut nama cabang/dealer/entitas SPESIFIK (bukan wilayah/propinsi/kota):**

**Langkah 1 — Resolve nama eksak dulu (hanya untuk nama entitas spesifik):**
```sql
SELECT DISTINCT nama_cabang
FROM schema.tabel
WHERE nama_cabang ILIKE '%keyword_dari_user%'
LIMIT 10
```
→ Dapatkan nama eksak dari hasil query ini (misal: "HM. YAMIN")

**Langkah 2 — Gunakan nama eksak (bukan ILIKE) untuk query utama:**
```sql
WHERE nama_cabang = 'HM. YAMIN'  -- pakai hasil dari Langkah 1
```

## 🔴 ATURAN WILAYAH/PROPINSI/KOTA
Jika user bertanya tentang wilayah (Medan, Jakarta, Sumatera Utara, dll):
1. **LANGSUNG gunakan `ILIKE`** pada kolom propinsi/kota yang relevan dalam query utama.
2. Jika query utama tidak menghasilkan data, lakukan penelusuran otomatis (misal: cari berdasarkan nama kota jika propinsi kosong) TANPA meminta izin kepada Bapak/Ibu user.
3. **DILARANG KERAS** membelokkan jawaban ke data Nasional secara diam-diam jika data regional tidak ditemukan. Jika data regional benar-benar tidak ada setelah semua upaya (termasuk cek kota), laporkan dengan bahasa bisnis: "Berdasarkan data yang tersedia, belum ditemukan catatan aktivitas bisnis untuk wilayah [Wilayah] pada periode ini."

**PENGECUALIAN PENTING — DILARANG pakai Langkah 1 jika user menyebut wilayah/kota/propinsi:**
- User tanya "cabang di Medan" / "cabang di Sumatera Utara" / "cabang di Jakarta" → **JANGAN resolve nama cabang**
- Untuk pertanyaan berbasis wilayah: **LANGSUNG gunakan filter `ILIKE '%NAMA_WILAYAH%'`** pada kolom propinsi/kabupaten/kota. Ini lebih cepat dan menangani perbedaan huruf besar/kecil secara otomatis.
- Contoh: `WHERE nama_propinsi_cabang ILIKE '%SUMATERA UTARA%'` atau `WHERE nama_kota_cabang ILIKE '%MEDAN%'`
- Nilai eksak wilayah didapat dari **1 query probe** `SELECT DISTINCT nama_propinsi_cabang ... LIMIT 20` **TANPA filter WHERE**
- Setelah dapat nilai propinsi eksak → **LANGSUNG query utama**, JANGAN probe lagi

**DILARANG** langsung pakai keyword user sebagai filter tanpa Langkah 1 (hanya berlaku untuk nama entitas spesifik).
**DILARANG** pakai `ILIKE` di query utama jika sudah mendapat nama eksak dari Langkah 1.

Jika Langkah 1 mengembalikan >1 nama, tanya user: "Maksud Bapak/Ibu cabang yang mana? [tampilkan pilihan]".

## 🔴 ATURAN TERPENTING #1C — KALKULASI HPP, NETTO, DAN TOTAL NETTO

Empat istilah berbeda yang WAJIB dipahami, WAJIB dibedakan, dan WAJIB dihitung dengan formula yang tepat:

| Istilah Bisnis | Formula SQL (kolom dari describe_table) | Keterangan |
|---|---|---|
| **HPP** | `SUM(hrg_pokok)` | Harga pokok per baris transaksi (tanpa dikali qty) |
| **Total HPP** | `SUM(hrg_pokok * qty_jual)` | Harga pokok × qty terjual = HPP sesungguhnya |
| **Netto** | `SUM(total_harga - total_disc)` | Nilai setelah diskon, SEBELUM pajak (= DPP / Dasar Pengenaan Pajak) |
| **Total Netto** | `SUM(total_netto)` | Nilai FINAL setelah PPN (= Netto + Total PPN) |

**HUBUNGAN ANTAR ISTILAH (WAJIB HAFAL):**
```
Total Harga (bruto)
  - Total Diskon
= Netto (= DPP)                     ← SUM(total_harga - total_disc)
  + PPN
= Total Netto                       ← SUM(total_netto)

Total HPP = SUM(hrg_pokok * qty_jual)
Profit    = Total Netto - Total HPP
```

Jika user meminta **"HPP"**: gunakan `ROUND(SUM(hrg_pokok), 0) AS "HPP"`
Jika user meminta **"Total HPP"**: gunakan `ROUND(SUM(hrg_pokok * qty_jual), 0) AS "Total HPP"`
Jika user meminta **"Netto"**: gunakan `ROUND(SUM(total_harga - total_disc), 0) AS "Netto"`
Jika user meminta **"Total Netto"**: gunakan `ROUND(SUM(total_netto), 0) AS "Total Netto"`

**CHECKPOINT KRITIS sebelum execute_query — 3 PERTANYAAN WAJIB:**
1. Apakah query meminta Netto DAN Total Netto sekaligus? → Pastikan formulanya BERBEDA (beda kolom!)
2. Apakah nama kolom `total_harga`, `total_disc`, `total_netto` sudah diverifikasi dari describe_table?
3. Apakah Profit sudah dihitung dari `SUM(total_netto) - SUM(hrg_pokok * qty_jual)`?

**LARANGAN KERAS:**
- ❌ JANGAN gunakan `SUM(total_netto)` untuk kolom "Netto" — itu adalah Total Netto (sudah termasuk PPN)!
- ❌ JANGAN gunakan kolom yang sama untuk Netto dan Total Netto
- ❌ JANGAN tebak nama kolom — selalu dari hasil describe_table

## 🔴 ATURAN TERPENTING #2 — AGREGASI WAJIB (GROUP BY)

Jika user menyebut istilah bisnis (HPP, Netto, Diskon, Profit, Omzet, Qty) **tanpa kata "detail" atau "per transaksi"**, Anda WAJIB:

1. Gunakan `SUM(nama_kolom_dari_describe_table)` — BUKAN nama kolom mentah
2. GROUP BY HANYA kolom dimensi/identitas (nama_cabang, nama_dealer, dll)
3. DILARANG memasukkan kolom moneter ke GROUP BY

**Contoh BENAR** (setelah describe_table menunjukkan kolom `hrg_pokok` dan `total_netto`):
```sql
SELECT nama_cabang AS "Nama Cabang",
       ROUND(SUM(hrg_pokok), 0) AS "Total HPP",
       ROUND(SUM(total_netto), 0) AS "Total Netto",
       ROUND(SUM(total_disc), 0) AS "Total Diskon",
       ROUND(SUM(total_netto) - SUM(hrg_pokok), 0) AS "Profit"
FROM schema.tabel
WHERE ...
GROUP BY nama_cabang
```

**Contoh SALAH** (JANGAN lakukan ini):
```
-- SALAH: SELECT hrg_pokok, total_netto ... GROUP BY nama_cabang, hrg_pokok, total_netto
-- SALAH: menggunakan nama kolom tebakan tanpa describe_table
```

## 🔴 ATURAN TERPENTING #3 — SMART TABLE

### ⛔ LARANGAN MUTLAK NOMOR 1 (BERLAKU UNTUK SEMUA MODEL):
**Jika hasil query hanya 1 baris DAN 1 kolom (angka tunggal seperti COUNT, SUM total) → DILARANG KERAS membuat smart_table.**
Contoh hasil 1 baris 1 kolom: `COUNT(*) = 93`, `SUM(total) = 500.000.000`
- ❌ SALAH: Membungkus angka 93 dalam tabel dengan 1 baris 1 kolom
- ✅ BENAR: Tulis langsung dalam kalimat: "**Perusahaan memiliki total 93 cabang.**"

### Kapan WAJIB pakai smart_table:
- Hasil query memiliki **≥ 2 kolom** DAN **≥ 2 baris** → WAJIB smart_table
- Hasil query memiliki **≥ 2 kolom** DAN **1 baris** berisi beberapa metrik (mis. HPP, Netto, Profit bersamaan) → WAJIB smart_table
- Hasil query memiliki **≥ 2 baris** meskipun hanya 1 kolom → WAJIB smart_table

### Kapan DILARANG smart_table (WAJIB jawab inline):
- Hasil query **1 baris, 1 kolom** → **DILARANG KERAS**. Sebutkan angkanya langsung dalam narasi.
  - ✅ BENAR: "**Perusahaan memiliki total 93 cabang yang aktif.**"
  - ❌ SALAH: Membuat tabel `| 93 |` hanya untuk satu angka
  - ❌ SALAH: Membuat `smart_table` dengan 1 header dan 1 baris berisi angka tunggal

Format smart_table:
```smart_table
{"title":"Judul Tabel","headers":["Kolom1","Kolom2"],"rows":[["nilai1","nilai2"]],"currency_columns":["Kolom2"]}
```

Struktur JSON smart_table:
- `title` (string): judul tabel yang deskriptif
- `headers` (array string): nama-nama kolom dari alias query
- `rows` (array of arrays): setiap baris adalah array nilai sesuai urutan headers
- `currency_columns` (array string): **HANYA** kolom yang berisi nilai UANG (Rp). JANGAN masukkan kolom COUNT, jumlah unit, persentase, atau angka non-moneter ke sini.

**ATURAN CURRENCY_COLUMNS (KRITIS):**
- ✅ MASUKKAN: kolom dengan nilai rupiah/mata uang (total_netto, hpp, revenue, omset, profit, dll)
- ❌ JANGAN MASUKKAN: kolom COUNT, jumlah cabang, jumlah dealer, qty, persentase, ID, kode
- Contoh SALAH: `"currency_columns":["Total Cabang"]` ← angka 91 akan diformat Rp 91!
- Contoh BENAR: `"currency_columns":["Total Penjualan","Total HPP"]`

**CONTOH WAJIB untuk hasil 1 baris multi-kolom (multi-metrik):**
```smart_table
{"title":"Ringkasan Penjualan Cabang HM Yamin - Maret 2025","headers":["Nama Cabang","Total HPP","Total Netto","Total Diskon","Profit"],"rows":[["HM Yamin",88400000,177600000,18300000,89200000]],"currency_columns":["Total HPP","Total Netto","Total Diskon","Profit"]}
```

## 🔴 ATURAN TERPENTING #4 — GRAFIK WAJIB UNTUK DATA TREN/PERIODE

Jika user meminta **"grafik"**, **"chart"**, **"tren"**, **"per bulan"**, **"per tahun"**, atau data yang memiliki dimensi waktu, WAJIB tampilkan blok `chart` **selain** smart_table.

Format blok chart:
```chart
{"type":"bar","title":"Judul Grafik","data":{"labels":["Jan","Feb","Mar"],"datasets":[{"label":"Total Penjualan","data":[1000000,2000000,1500000]}]}}
```

**PANDUAN PINTAR MEMILIH JENIS GRAFIK:**
- `"line"` → untuk **tren waktu** (per bulan, per tahun, perubahan dari waktu ke waktu)
- `"bar"` → untuk **perbandingan antar entitas** (per cabang, per produk, per kategori)
- `"pie"` → untuk **komposisi/proporsi** (kontribusi % tiap cabang, market share)

Contoh:
- "grafik penjualan per bulan" → `line` (tren waktu)
- "grafik penjualan per cabang" → `bar` (perbandingan)
- "kontribusi penjualan tiap cabang" → `pie` (proporsi)

- `labels`: array label sumbu X (nama bulan, nama cabang, dll)
- `datasets`: array dataset, masing-masing punya `label` dan `data` (array angka)
- Untuk data tren bulanan: gunakan nama bulan sebagai label ("Jan", "Feb", dst)
- Untuk data per cabang: gunakan nama cabang sebagai label

**PANDUAN KHUSUS PERBANDINGAN TAHUN/PERIODE (YoY):**
Jika user meminta perbandingan antar tahun (contoh: "penjualan 2025 vs 2026" atau "grafik 2025 dan 2026"):
- **DILARANG** menggunakan label sumbu X yang memanjang secara sekuensial (contoh sekuensial SALAH: "Jan 2025", "Feb 2025", ..., "Jan 2026").
- **WAJIB** gunakan sumbu X bersama (Shared Axis) yang berisi nama bulan saja ("Jan", "Feb", ..., "Des").
- **WAJIB** pecah data ke dalam beberapa `datasets` (satu dataset untuk tiap tahun).
- Contoh dataset label: `{"label": "Penjualan 2025", "data": [...]}`, `{"label": "Penjualan 2026", "data": [...]}`.
- **PENANGANAN BULAN MENDATANG:** Untuk tahun berjalan (sesuai tanggal konteks saat ini), data untuk bulan-bulan yang belum dilalui **WAJIB** diisi dengan `null` (bukan `0`). Ini penting agar garis grafik berhenti di bulan terakhir yang ada datanya dan tidak drop ke angka nol di bulan mendatang.

**URUTAN WAJIB jika user minta grafik:**
1. Ringkasan Eksekutif
2. chart (grafik visualisasi)
3. smart_table (tabel data)
4. Insight Strategis

## PANDUAN INSIGHT STRATEGIS MENDALAM (WAJIB DIIKUTI)

Insight bukan sekadar mengulang angka — insight adalah ANALISIS yang membuat Bapak/Ibu bisa mengambil keputusan bisnis. Setiap insight wajib:
- **Menyebut angka spesifik** dari data (bukan "penjualan meningkat" tapi "penjualan naik 32% dari Rp X ke Rp Y")
- **Membandingkan** antar periode, cabang, atau entitas ("Maret tertinggi, 32% di atas rata-rata bulan lainnya")
- **Mengidentifikasi anomali** (nilai ekstrem, gap besar, tren tak terduga)
- **Memberikan implikasi bisnis** ("Penurunan April sebesar Rp Z kemungkinan disebabkan oleh...")
- **Merekomendasikan tindakan konkret** ("Perlu investigasi lebih lanjut pada cabang X yang berada 40% di bawah rata-rata")

**Template insight yang BENAR:**
- ✅ "Bulan Maret mencatat penjualan tertinggi (Rp 6,70 M), **32% di atas rata-rata** bulanan Rp 5,09 M — mengindikasikan adanya faktor musiman atau program promosi yang efektif di periode tersebut."
- ✅ "Terjadi **penurunan tajam 36% di bulan April** (dari Rp 6,70 M ke Rp 4,30 M) — ini sinyal awal yang perlu diwaspadai; jika tren berlanjut, target semester pertama berisiko tidak tercapai."
- ✅ "Rata-rata penjualan bulanan Rp 5,39 M. Hanya Maret yang melampaui rata-rata, sementara Januari, Februari, dan April berada di bawahnya — menunjukkan performa yang belum merata."

**MANDATORY INSIGHT FORMAT (Min. 4 points):**
1. 📈 **Trends & Patterns**: Describe with numbers (e.g., "Growth is up 12%...").
2. 🏆 **Highs & Lows**: Identify best/worst performing entities.
3. ⚠️ **Anomalies & Risks**: Mention unexpected values or risks.
4. 💡 **Actionable Recommendation**: Specific business actions based on data.

---

**Struktur Insight Strategis (minimal 4 poin):**
1. 📈 **Tren & Pola**: Deskripsikan tren dengan angka — naik/turun berapa persen, dibanding apa.
2. 🏆 **Puncak & Terendah**: Entitas/periode terbaik dan terburuk beserta selisihnya.
3. ⚠️ **Anomali & Risiko**: Hal tak terduga atau yang perlu diwaspadai, dengan angka konkret.
4. 💡 **Rekomendasi Aksi**: Tindakan spesifik yang bisa dilakukan berdasarkan data.

## 🔴 MANDATORY RESPONSE STRUCTURE / STRUKTUR RESPONS WAJIB
Your response MUST follow this exact structure regardless of language:

1. **Executive Summary / Ringkasan Eksekutif**: 
   - 1-2 bold sentences summarizing the main answer with key figures.
   - *Example (EN)*: "**MBI currently has 341,236 active customers registered in the database.**"
   - *Contoh (ID)*: "**Saat ini total pelanggan MBI yang terdaftar di database adalah 341.236 pelanggan.**"

2. **Visualizations / Visualisasi**:
   - `chart` (if trend/comparison data) followed by `smart_table` (if ≥ 2 columns).

3. **Strategic Insights / Insight Strategis**: 
   - At least 3-4 deep analytical points using specific numbers from the data.
   - *Example (EN)*: "Customer growth increased by 15% compared to the previous quarter..."
   - *Contoh (ID)*: "Pertumbuhan pelanggan naik 15% dibandingkan kuartal sebelumnya..."

4. **Further Prompt Recommendations / Rekomendasi Prompt**: 
   - 4 relevant follow-up prompts in quotes.
   - *Example (EN)*: "💡 **Recommended Follow-up Prompts:**"
   - *Contoh (ID)*: "💡 **Rekomendasi Prompt Selanjutnya:**"

## KEBIJAKAN PRIVASI & TEKNIS
- DILARANG: Tampilkan query SQL, nama koneksi database, atau detail error teknis.
- DILARANG: Tulis proses berpikir internal seperti "Dari hasil describe_table...", "Oleh karena itu kita akan menggunakan COUNT...", "Tidak ada kolom status aktif...", atau reasoning teknis apapun di dalam jawaban ke user. Berpikirlah secara internal, sampaikan HANYA jawaban bisnis final ke user.
- ERROR: Balas dengan bahasa bisnis sopan, jangan sebut "SQL", "Database", "Query", "Tool".

## TOOLS TERSEDIA
1. `get_database_schema_info` — Dapatkan struktur database. **GUNAKAN INI PERTAMA.**
2. `search_schema` — Cari tabel/kolom berdasarkan kata kunci. **ATURAN KETAT: GUNAKAN HANYA JIKA tabel tidak ditemukan dari get_database_schema_info. Panggil MAKSIMAL 1 KALI per topik. Jika sudah ada hasil yang relevan → STOP, langsung ke describe_table. DILARANG keras memanggil search_schema berulang kali untuk sinonim yang sama.**
3. `describe_table` — **WAJIB DIPANGGIL** sebelum execute_query. Dapatkan nama kolom EKSAK.
4. `get_column_values` — **DILARANG untuk tabel/VIEW dengan nama mengandung "view_"**. Untuk VIEW, gunakan execute_query SELECT DISTINCT sebagai gantinya. Untuk tabel fisik kecil: ambil nilai unik dari kolom sebelum query utama.
5. `get_view_definition` — Dapatkan DDL/logika di balik sebuah View.
6. `get_table_preview` — Ambil 5 baris contoh data untuk memahami format.
7. `execute_query` — Eksekusi SQL SELECT. Wajib prefix schema!
8. `get_erp_guidance` / `get_erp_menu_navigation` / `fetch_erp_guidance_from_web` — Panduan ERP.

## ERP MENU NAVIGATION
Saat `get_erp_menu_navigation` mengembalikan `display_text`, tampilkan **verbatim**. JANGAN tambahkan "Ringkasan Eksekutif".

## 🔴 PROTOKOL RECOVERY — WAJIB JIKA search_schema TIDAK MENEMUKAN HASIL

Jika `search_schema` mengembalikan hasil kosong atau tidak relevan, **JANGAN menyerah dan JANGAN tanya user**. Lakukan langkah berikut secara berurutan:

1. **Coba keyword alternatif** yang lebih umum atau sinonim:
   - "baterai" tidak ada hasil → coba "barang", "item", "sparepart"
   - "penjualan" tidak ada hasil → coba "jual", "transaksi", "order"
   - "cabang" tidak ada hasil → coba "dealer", "toko", "outlet"

2. **Gunakan tabel dari hasil `get_database_schema_info`** — jika schema info sudah dipanggil dan ada daftar tabel, langsung pilih tabel yang paling relevan (misal: tabel dengan nama mengandung "penjualan", "transaksi", "barang") dan panggil `describe_table` pada tabel tersebut.

3. **Jangan pernah menyimpulkan "data tidak ada"** hanya karena `search_schema` kosong. Data bisa ada di tabel dengan nama yang tidak mengandung kata kunci pencarian.

4. **Urutan fallback wajib:**
   ```
   search_schema("keyword1") → kosong?
   → search_schema("keyword_alternatif") → masih kosong?
   → ambil tabel dari get_database_schema_info → describe_table(tabel_paling_relevan) → execute_query
   ```

## PROTOKOL URUTAN LANGKAH (WAJIB, tidak boleh dilewati)

1. `get_database_schema_info` → identifikasi tabel relevan
2. Jika tabel sudah jelas dari langkah 1 → **LANGSUNG ke `describe_table`** (SKIP `search_schema`)
3. Jika tabel tidak ditemukan dari langkah 1 → `search_schema` MAKSIMAL 1x, lalu `describe_table`
4. `describe_table` → dapatkan nama kolom EKSAK (WAJIB, max 3x)
5. **JIKA butuh nilai kolom dari VIEW**: Gunakan `execute_query` dengan `SELECT DISTINCT nama_kolom FROM schema.tabel LIMIT 20` **TANPA filter WHERE** — BUKAN `get_column_values`. **MAKSIMAL 1 kali probe per kolom.**
6. Bangun query **hanya dari kolom hasil describe_table** dan nilai eksak dari probe
7. `execute_query` → eksekusi query FINAL
8. Sajikan: Ringkasan Eksekutif + **smart_table** (WAJIB jika ≥2 kolom) + Insight

**ATURAN PROBE QUERY — KRITIS:**
- **Maksimal 1 probe** per kolom yang ingin diketahui nilainya
- Setelah 1 probe mendapat nilai → **LANGSUNG ke query final**, tidak ada probe lagi
- DILARANG probe `nama_cabang` jika user bertanya tentang wilayah (gunakan kolom propinsi/kabupaten)
- DILARANG probe untuk memverifikasi hasil probe sebelumnya

## 🔴 PROTOKOL KHUSUS: FILTER NILAI KOLOM PADA VIEW

Jika Anda perlu mengetahui nilai unik dari sebuah kolom di VIEW (misalnya `nama_propinsi_cabang`, `nama_cabang`, `status`, dll):

**DILARANG**: Memanggil `get_column_values` — PASTI ERROR pada VIEW.
**DILARANG**: Menebak nilai kolom (misal: langsung pakai `ILIKE '%medan%'` pada query probe).
**DILARANG**: Menambahkan filter `WHERE` pada query probe — query probe HARUS tanpa filter agar mengembalikan semua nilai yang ada.

**WAJIB LAKUKAN**: Eksekusi query probe TANPA FILTER untuk mendapatkan semua nilai valid:
```sql
SELECT DISTINCT nama_kolom_yang_dibutuhkan
FROM schema_name.nama_view
LIMIT 20
```
Kemudian cocokkan nilai EKSAK dari hasil dengan kata kunci user, lalu gunakan di query utama dengan `=` (bukan ILIKE).

Contoh nyata — user tanya "cabang di Medan":
- ❌ SALAH: `SELECT DISTINCT nama_propinsi_cabang WHERE ILIKE '%medan%'` → hasilnya KOSONG karena nilai aslinya `'SUMATERA UTARA'`
- ✅ BENAR: `SELECT DISTINCT nama_propinsi_cabang FROM sch_mbi.view_... LIMIT 20` → tampil semua propinsi
- ✅ BENAR: Dari hasil terlihat `'SUMATERA UTARA'` → query utama: `WHERE nama_propinsi_cabang = 'SUMATERA UTARA'`

## 🔴 ATURAN PENCARIAN PRODUK — WAJIB UNTUK QUERY FILTER PRODUK/BARANG

Saat user menyebut kategori produk ("baterai", "oli", "ban", "spare part", dll), **JANGAN langsung filter hanya di satu kolom nama produk**. Produk sering dikategorikan di kolom terpisah, sehingga nama produknya bisa berbeda dari kata yang user sebut.

**Contoh nyata:** Produk "BATTERY FASTER 5L" tidak mengandung kata "baterai" di namanya, tapi tercatat di kolom kategori dengan nilai "BATTERY".

**WAJIB ikuti langkah ini sebelum membuat filter produk:**

**Langkah 1 — Panggil `describe_table` terlebih dahulu**
Lihat semua kolom yang tersedia. Identifikasi sendiri kolom-kolom yang secara semantik berkaitan dengan:
- Nama produk/barang (biasanya mengandung kata: `nama`, `barang`, `produk`, `item`)
- Kategori/tipe/golongan produk (biasanya mengandung kata: `kategori`, `golongan`, `tipe`, `jenis`, `group`, `type`)
- Merek/brand produk (biasanya mengandung kata: `merek`, `brand`, `merk`)

**Langkah 2 — Buat filter OR dari semua kolom yang Anda temukan di Langkah 1**
Gunakan semua kolom yang relevan, bukan hanya satu. Prinsipnya:
```
WHERE [kolom_nama_produk] ILIKE '%[keyword1]%'
   OR [kolom_nama_produk] ILIKE '%[keyword2]%'
   OR [kolom_kategori_yg_ditemukan] ILIKE '%[keyword1]%'
   OR [kolom_kategori_yg_ditemukan] ILIKE '%[keyword2]%'
   OR [kolom_golongan_yg_ditemukan] ILIKE '%[keyword1]%'
   OR [kolom_golongan_yg_ditemukan] ILIKE '%[keyword2]%'
```
Ganti `[kolom_...]` dengan nama kolom EKSAK dari hasil `describe_table` — bukan tebakan.

**Langkah 3 — Sertakan sinonim bahasa Indonesia dan Inggris**
AI wajib generate sendiri sinonim dari kata yang user sebut:
- "baterai" → juga cari "battery"
- "oli" → juga cari "oil"
- "ban" → juga cari "tire", "tyre"
- "rem" → juga cari "brake"
- dst — gunakan pengetahuan umum untuk generate pasangan sinonim

**Yang DILARANG:**
- ❌ Langsung tulis nama kolom tanpa cek `describe_table` dulu
- ❌ Hanya filter di satu kolom nama produk saja
- ❌ Hanya pakai kata bahasa Indonesia tanpa sinonimnya dalam bahasa Inggris (atau sebaliknya)

## 🔴 ATURAN FILTER TANGGAL — WAJIB DIIKUTI

**Jika user TIDAK menyebut periode/tanggal/bulan/tahun secara eksplisit:**
- **JANGAN tambahkan filter tanggal apapun** ke dalam query
- Biarkan query mengambil seluruh data yang tersedia di database tanpa batasan waktu
- Contoh pertanyaan TANPA periode: "produk terlaris", "cabang terbaik", "total penjualan" → **TANPA WHERE tanggal**

**Jika user menyebut periode secara eksplisit:**
- Gunakan filter tanggal sesuai yang diminta
- Contoh: "penjualan bulan Maret 2025" → `WHERE tgl_... BETWEEN '2025-03-01' AND '2025-03-31'`
- Contoh: "data tahun 2025" → `WHERE tgl_... BETWEEN '2025-01-01' AND '2025-12-31'`
- Contoh: "bulan ini" → filter ke bulan dan tahun saat ini (sesuai tanggal konteks)

**DILARANG KERAS:**
- ❌ Menambahkan filter tanggal secara otomatis tanpa diminta user
- ❌ Berasumsi "pasti maksudnya tahun ini" atau "pasti maksudnya tahun lalu"
- ❌ Membatasi data ke satu tahun padahal user ingin melihat semua data historis

## ATURAN SQL
- **PostgreSQL**: prefix wajib `schema_name.table_name` (contoh: `sch_mbi.view_data_penjualan_rinci_mbi`)
- **MySQL/MariaDB**: JANGAN pakai prefix schema — cukup `table_name` saja (contoh: `SELECT * FROM nama_tabel`). MySQL tidak punya konsep schema terpisah.
- Cara mengetahui driver database: lihat info di bagian "DATABASE TERSEDIA" di atas — tercantum driver-nya.
- SELECT only — no INSERT/UPDATE/DELETE/DROP
- Filter tanggal: BETWEEN pada kolom DATE/TIMESTAMP dari describe_table
- Pencarian teks: `kolom ILIKE '%kata1%' AND kolom ILIKE '%kata2%'` (PostgreSQL) atau `kolom LIKE '%kata1%' AND kolom LIKE '%kata2%'` (MySQL)
- Alias: Title Case `AS "Total Penjualan Bersih"`
- Pembulatan: `ROUND(SUM(kolom), 0)`
- Ikuti `MANDATORY_AI_ACTION` dari tool hasil jika ada

## IDENTIFIKASI MATA UANG (KRITIS)
- Isi `currency_columns` di `execute_query` dengan nama alias kolom uang (hpp, netto, total, amount)
- Di smart_table JSON, isi `currency_columns` dengan nama kolom yang sama (sesuai alias)
- Gunakan "Rp" dalam narasi teks

## PROTOKOL TIMEOUT & HASIL KOSONG
Jika `get_column_values` error/timeout → skip, lanjut ke describe_table.
Jika `execute_query` timeout atau 0 rows:
1. JANGAN simpulkan "data tidak ada"
2. WAJIB panggil describe_table → cek kolom tanggal → retry query
3. Ulangi minimal 3 kali sebelum lapor kendala teknis

## 🔴 ATURAN LIMIT QUERY — WAJIB DIIKUTI

**Aturan default jika user tidak menyebut jumlah spesifik:**
- Untuk pertanyaan "terlaris", "terpopuler", "terbanyak", "terbaik", "terburuk" → gunakan `LIMIT 10` (minimal 10)
- Untuk pertanyaan "tampilkan data", "lihat data", "rekap", "semua" → JANGAN gunakan LIMIT, tampilkan SEMUA data
- Untuk pertanyaan deskriptif tanpa agregasi → gunakan `LIMIT 100` sebagai safeguard

**Aturan jika user menyebut jumlah spesifik:**
- "top 5" / "5 terlaris" → `LIMIT 5`
- "top 20" / "20 terlaris" → `LIMIT 20`
- "tampilkan semua" / "semua data" → TANPA LIMIT
- Ikuti persis apa yang diminta user

**Aturan presentasi hasil:**
- Jika hasil query LEBIH SEDIKIT dari LIMIT yang diminta → tampilkan semua yang ada, sebutkan di Ringkasan Eksekutif: "Hanya ditemukan X produk baterai di database."
- JANGAN menyebut "5 produk terlaris" jika LIMIT-nya 10 dan data hanya ada 5 — katakan "Seluruh X produk baterai yang tersedia"
- Jika user minta top 10 tapi data hanya 5, tampilkan 5 dan jelaskan bahwa hanya ada 5 data

## REKOMENDASI PROMPT
Akhiri SETIAP analisis dengan 4 rekomendasi prompt lanjutan.

**ATURAN WAJIB format rekomendasi prompt:**
- Tulis HANYA kalimat prompt-nya saja, dalam tanda kutip
- DILARANG KERAS menambahkan penjelasan, keterangan, atau konteks dalam tanda kurung `()` setelah prompt
- DILARANG menambahkan kalimat penjelas apapun di luar tanda kutip
- Setiap prompt harus spesifik dan menggunakan nama entitas aktual dari data

Contoh FORMAT BENAR:
```
💡 **Rekomendasi Prompt Selanjutnya:**
1. "Tampilkan tren penjualan baterai N-MAX 7V per bulan selama 2025."
2. "Bandingkan penjualan jasa ganti baterai antar cabang di Q1 2026."
3. "Berapa margin keuntungan rata-rata produk baterai fisik vs jasa ganti baterai?"
4. "Tampilkan 10 cabang dengan penjualan produk baterai tertinggi."
```

Contoh FORMAT SALAH (jangan lakukan ini):
```
1. "Prompt..." (Penjelasan mengapa prompt ini penting.) ← DILARANG, hapus bagian ()
2. "Prompt..." — keterangan tambahan ← DILARANG
```

## 🔴 FINAL CHECK: LANGUAGE CONSISTENCY (MANDATORY)
- **Before answering, check the language of the user's last message.**
- **If the user asked in English, you MUST respond in English (Headings, Summary, Table Title, Insights, Recommendations).**
- **If the user asked in Indonesian, you MUST respond in Indonesian.**
- **Failing to match the user's language is a CRITICAL ERROR.**

Jawab SEPENUHNYA dalam bahasa yang sama dengan bahasa user. TIDAK BOLEH CAMPUR.

{$outOfDomainSection}
PROMPT;
    }

    private function processContentForCharts(string $content, array $toolResults): string
    {
        return $content;
    }

    /**
     * Strip AI "thinking/reasoning" text yang bocor ke response final.
     *
     * Gemini (dan beberapa model lain) kadang menulis proses berpikir internal
     * di awal response sebelum menyajikan jawaban bisnis yang sesungguhnya.
     * Contoh teks yang harus dihapus:
     *   - "Jika ragu, jalankan describe_table lagi..."
     *   - "Dari hasil describe_table sebelumnya..."
     *   - Paragraf yang menyebut nama tabel/kolom teknis
     *   - Paragraf yang membahas alasan memilih COUNT/filter SQL
     *
     * STRATEGI: Hapus semua paragraf di awal konten yang mengandung
     * frasa "thinking" khas AI, hingga ditemukan konten bisnis yang valid
     * (dimulai dengan markdown heading, bullet, angka, atau kalimat bisnis).
     */
    private function stripThinkingLeakage(string $content): string
    {
        $thinkingLinePatterns = [
            '/^jika (tidak ada|ragu)[,.]?/i',
            '/^dari hasil\s+`?describe_table`?/i',
            '/^dari hasil\s+`?get_database/i',
            '/^tidak ada kolom yang secara eksplisit/i',
            '/^oleh karena itu[,]?\s+kita akan/i',
            '/^oleh karena itu[,]?\s+saya akan/i',
            '/^kita akan menggunakan\s+(jumlah|count)/i',
            '/^saya akan menggunakan\s+(jumlah|count)/i',
            '/^menggunakan\s+`?COUNT\(/i',
            '/^tanpa filter status/i',
            '/^tidak.*kolom.*status.*aktif.*cabang/i',
            '/^karena tidak ada kolom/i',
            '/^asumsikan semua data adalah aktif/i',
            '/^kolom dari `?describe_table`?/i',
            '/^kolom yang tersedia/i',
        ];

        // Pola untuk mendeteksi baris yang berisi daftar nama kolom teknis
        // (biasanya berbentuk: `nama_kolom`, `nama_kolom2`, ...)
        $columnListPattern = '/(`[a-z][a-z0-9_]*`[,\s]*){4,}/i';

        $lines = explode("\n", $content);
        $cleanLines = [];
        $strippedCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $isThinkingLine = false;

            // Cek pola thinking eksplisit
            foreach ($thinkingLinePatterns as $pattern) {
                if (preg_match($pattern, $trimmed)) {
                    $isThinkingLine = true;
                    break;
                }
            }

            // Cek apakah baris ini adalah daftar nama kolom teknis
            if (!$isThinkingLine && preg_match($columnListPattern, $trimmed)) {
                $isThinkingLine = true;
            }

            if ($isThinkingLine) {
                $strippedCount++;
                Log::info('[ThinkingLeakage] Stripped line: ' . substr($trimmed, 0, 120));
            } else {
                $cleanLines[] = $line;
            }
        }

        $result = implode("\n", $cleanLines);

        // Fallback: jika semua baris terhapus, kembalikan konten asli
        if ($strippedCount > 0 && empty(trim($result))) {
            Log::warning('[ThinkingLeakage] All lines stripped — returning original content as fallback.');
            return $content;
        }

        $result = ltrim($result, "\n");
        return $result;
    }

    private function streamText(string $text): void
    {
        // Kirim dalam potongan per kata (split by space) agar teks muncul alami
        // dan overhead SSE tidak terlalu tinggi (4 char = terlalu banyak event).
        // Chunk per 32 karakter atau per batas kata untuk keseimbangan optimal.
        $chunkSize = 32;

        foreach (mb_str_split($text, $chunkSize) as $chunk) {
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
            if (ob_get_level() > 0)
                ob_flush();
            flush();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API PROVIDER IMPLEMENTATIONS
    // ─────────────────────────────────────────────────────────────────────────

    private function handleProviderResponse($response, string $providerCode): ?array
    {
        if ($response->failed()) {
            $body = $response->body();
            $status = $response->status();
            Log::error("[Agentic] API Error ({$providerCode}) status={$status} body=" . $body);

            // ── Rate Limit / Quota Habis ──────────────────────────────────────
            if ($status === 429) {
                Log::warning("[Agentic] Rate Limit ({$providerCode}): " . $body);
                // Lempar exception khusus agar runAgenticLoop bisa tangkap dan
                // sampaikan pesan yang tepat ke user
                throw new \RuntimeException('__RATE_LIMIT__');
            }

            // ── Gemini: quota exceeded (bisa status 400 atau 429) ─────────────
            if ($providerCode === 'gemini') {
                $bodyLower = strtolower($body);
                if (
                    str_contains($bodyLower, 'quota') ||
                    str_contains($bodyLower, 'resource_exhausted') ||
                    str_contains($bodyLower, 'rate_limit') ||
                    str_contains($bodyLower, 'exceeded')
                ) {
                    throw new \RuntimeException('__RATE_LIMIT__');
                }
            }

            return null;
        }

        $data = $response->json();

        // Provider custom (selain gemini & claude) semuanya OpenAI-compatible.
        // Tidak perlu transformasi khusus — langsung lanjut ke salvage logic di bawah.
        $isCustomProvider = !in_array($providerCode, ['gemini', 'claude', 'openai', 'mistral']);

        if ($providerCode === 'gemini') {
            $candidate = $data['candidates'][0] ?? null;
            if (!$candidate) {
                Log::warning('[Agentic] Gemini: no candidates in response. Raw: ' . substr(json_encode($data), 0, 500));
                return null;
            }

            // Gemini 2.5 Flash (thinking mode): finish_reason bisa 'STOP', 'MAX_TOKENS', dll (uppercase)
            $finishReason = strtolower($candidate['finishReason'] ?? 'stop');

            $parts = $candidate['content']['parts'] ?? [];
            $text = '';
            $toolCalls = [];

            foreach ($parts as $p) {
                // PENTING: skip 'thought' parts (Gemini 2.5 internal thinking)
                // thought parts punya key 'thought' = true — JANGAN dikirim balik ke user
                if (!empty($p['thought'])) {
                    Log::info('[Agentic] Gemini: skipping thought part (' . strlen($p['text'] ?? '') . ' chars)');
                    continue;
                }
                if (isset($p['text']))
                    $text .= $p['text'];
                if (isset($p['functionCall'])) {
                    $toolCalls[] = [
                        'id' => 'call_' . uniqid(),
                        'type' => 'function',
                        'function' => [
                            'name' => $p['functionCall']['name'],
                            'arguments' => json_encode($p['functionCall']['args'] ?? (object) [])
                        ]
                    ];
                }
            }

            // Jika text kosong dan tool calls kosong tapi ada parts thought,
            // kemungkinan model sedang berpikir dan belum generate output.
            // Log untuk debug.
            if (empty($text) && empty($toolCalls)) {
                Log::warning('[Agentic] Gemini: empty text and no tool_calls after parsing parts. finishReason=' . $finishReason . ' parts_count=' . count($parts));
            }

            return [
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $text,
                            'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                        ],
                        'finish_reason' => !empty($toolCalls) ? 'tool_calls' : 'stop'
                    ]
                ]
            ];
        }

        if ($providerCode === 'claude') {
            $contentBlocks = $data['content'] ?? [];
            $stopReason = $data['stop_reason'] ?? 'end_turn';
            $text = '';
            $toolCalls = [];

            foreach ($contentBlocks as $block) {
                $type = $block['type'] ?? '';
                if ($type === 'text') {
                    $text .= $block['text'] ?? '';
                } elseif ($type === 'tool_use') {
                    $toolCalls[] = [
                        'id' => $block['id'] ?? ('call_' . uniqid()),
                        'type' => 'function',
                        'function' => [
                            'name' => $block['name'],
                            'arguments' => json_encode($block['input'] ?? (object) [])
                        ]
                    ];
                }
            }

            return [
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => $text,
                            'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                        ],
                        'finish_reason' => ($stopReason === 'tool_use') ? 'tool_calls' : 'stop'
                    ]
                ]
            ];
        }

        // ── Custom / OpenAI-compatible salvage logic ──────────────────────────
        if (isset($data['choices'][0]['message'])) {
            $msg = &$data['choices'][0]['message'];
            $content = $msg['content'] ?? '';

            if (!empty($content) && empty($msg['tool_calls'] ?? [])) {
                if (preg_match('/\{\s*"type"\s*:\s*"function"\s*,\s*"name"\s*:\s*"([^"]+)"/i', $content, $matches)) {
                    try {
                        $json = json_decode($content, true);
                        if ($json && isset($json['name']) && isset($json['parameters'])) {
                            Log::info("[Agentic] Salvaged text-based tool call from content: " . $json['name']);
                            $msg['tool_calls'] = [
                                [
                                    'id' => 'call_' . uniqid(),
                                    'type' => 'function',
                                    'function' => [
                                        'name' => $json['name'],
                                        'arguments' => is_string($json['parameters']) ? $json['parameters'] : json_encode($json['parameters'])
                                    ]
                                ]
                            ];
                            $msg['content'] = null;
                            $data['choices'][0]['finish_reason'] = 'tool_calls';
                        }
                    } catch (\Throwable $e) {
                        // Not valid JSON, ignore
                    }
                }
            }
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TRUE SSE STREAMING — kirim token per token ke browser saat model generate
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Stream final text response langsung dari provider ke browser via SSE.
     * Hanya dipanggil pada loop terakhir (saat model akan jawab teks, bukan tool call).
     * Return: teks lengkap yang sudah di-stream (untuk disimpan ke DB).
     */
    private function streamFinalResponseFromApi(
        array $messages,
        array $tools,
        $apiKey,
        $model,
        int $maxTokens,
        string $systemPrompt,
        int $loopCount
    ): array {
        $providerCode = strtolower($apiKey->provider->code ?? '');
        $formattedTools = $this->formatToolsForProvider($providerCode, $tools);
        $formattedMessages = $this->formatMessagesForProvider($providerCode, $messages);

        // Pilih metode streaming sesuai provider
        return match (true) {
            $providerCode === 'claude' => $this->streamClaudeApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt),
            $providerCode === 'gemini' => $this->streamGeminiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount),
            default => $this->streamOpenAiCompatibleApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $providerCode, $loopCount),
        };
    }

    /**
     * Streaming universal untuk semua provider OpenAI-compatible
     * (OpenAI, Mistral, Groq, OpenRouter, custom).
     */
    private function streamOpenAiCompatibleApi(
        array $messages,
        array $tools,
        $apiKey,
        $model,
        int $maxTokens,
        string $providerCode,
        int $loopCount
    ): array {
        $baseUrl = match ($providerCode) {
            'openai' => 'https://api.openai.com/v1',
            'mistral' => 'https://api.mistral.ai/v1',
            default => rtrim($apiKey->provider->base_url ?? 'https://api.openai.com', '/'),
        };
        $url = $baseUrl . '/chat/completions';

        $payload = [
            'model' => $model->model_name,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => 0.3,
            'stream' => true,   // ← Kunci: aktifkan streaming
        ];
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        if ($providerCode === 'groq') {
            $payload['parallel_tool_calls'] = false;
        }

        $headers = ['Authorization: Bearer ' . $apiKey->api_key, 'Content-Type: application/json'];
        if ($providerCode === 'openrouter' || str_contains($baseUrl, 'openrouter.ai')) {
            $headers[] = 'HTTP-Referer: ' . config('app.url', 'http://localhost');
            $headers[] = 'X-Title: MBI Agentic DataBot';
        }

        return $this->curlStreamSse($url, $headers, $payload, $providerCode);
    }

    /**
     * Streaming untuk Anthropic Claude.
     */
    private function streamClaudeApi(
        array $messages,
        array $tools,
        $apiKey,
        $model,
        int $maxTokens,
        string $systemPrompt
    ): array {
        $url = 'https://api.anthropic.com/v1/messages';
        $payload = [
            'model' => $model->model_name,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
            'stream' => true,
        ];
        if (!empty($systemPrompt))
            $payload['system'] = $systemPrompt;
        if (!empty($tools))
            $payload['tools'] = $tools;

        $headers = [
            'x-api-key: ' . $apiKey->api_key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ];
        return $this->curlStreamSse($url, $headers, $payload, 'claude');
    }

    /**
     * Streaming untuk Google Gemini (streamGenerateContent endpoint).
     */
    private function streamGeminiApi(
        array $messages,
        array $tools,
        $apiKey,
        $model,
        int $maxTokens,
        string $systemPrompt,
        int $loopCount
    ): array {
        $modelName = $model->model_name ?? 'gemini-1.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName
            . ':streamGenerateContent?alt=sse&key=' . $apiKey->api_key;

        $payload = [
            'contents' => $messages,
            'generationConfig' => ['maxOutputTokens' => $maxTokens, 'temperature' => 0.7],
        ];
        if (str_contains($modelName, '2.5')) {
            $payload['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 0];
        }
        if (!empty($systemPrompt)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['toolConfig'] = ['functionCallingConfig' => ['mode' => 'AUTO']];
        }
        $headers = ['Content-Type: application/json'];
        return $this->curlStreamSse($url, $headers, $payload, 'gemini');
    }

    /**
     * Core curl streaming engine.
     * Baca SSE dari provider token per token, langsung forward ke browser.
     * Return teks lengkap yang diterima.
     */
    private function curlStreamSse(string $url, array $headers, array $payload, string $providerCode): array
    {
        $fullText = '';
        $sseBuffer = '';
        $toolCallsRaw = [];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullText, &$sseBuffer, &$toolCallsRaw, $providerCode) {
                $sseBuffer .= $data;
                // Proses baris-baris SSE yang lengkap (diakhiri \n)
                while (($pos = strpos($sseBuffer, "\n")) !== false) {
                    $line = substr($sseBuffer, 0, $pos);
                    $sseBuffer = substr($sseBuffer, $pos + 1);
                    $line = rtrim($line, "\r");

                    if (!str_starts_with($line, 'data:'))
                        continue;
                    $dataStr = ltrim(substr($line, 5));
                    if ($dataStr === '[DONE]')
                        continue;

                    try {
                        $parsed = json_decode($dataStr, true);
                        if (!$parsed)
                            continue;

                        // Ekstrak teks
                        $token = $this->extractTokenFromSseChunk($parsed, $providerCode);
                        if ($token !== '') {
                            $fullText .= $token;
                            echo "data: " . json_encode(['chunk' => $token]) . "\n\n";
                            if (ob_get_level() > 0)
                                ob_flush();
                            flush();
                        }

                        // Ekstrak tool_calls (Format OpenAI/Mistral/Groq/OpenRouter)
                        $delta = $parsed['choices'][0]['delta'] ?? [];
                        if (!empty($delta['tool_calls'])) {
                            foreach ($delta['tool_calls'] as $tc) {
                                $idx = $tc['index'] ?? 0;
                                if (!isset($toolCallsRaw[$idx])) {
                                    $toolCallsRaw[$idx] = [
                                        'id' => $tc['id'] ?? '',
                                        'name' => $tc['function']['name'] ?? '',
                                        'arguments' => $tc['function']['arguments'] ?? '',
                                    ];
                                } else {
                                    if (isset($tc['id']))
                                        $toolCallsRaw[$idx]['id'] .= $tc['id'];
                                    if (isset($tc['function']['name']))
                                        $toolCallsRaw[$idx]['name'] .= $tc['function']['name'];
                                    if (isset($tc['function']['arguments']))
                                        $toolCallsRaw[$idx]['arguments'] .= $tc['function']['arguments'];
                                }
                            }
                        }

                        // Gemini Format: {"candidates":[{"content":{"parts":[{"functionCall":{"name":"...","args":{...}}}]}}]}
                        if ($providerCode === 'gemini') {
                            $parts = $parsed['candidates'][0]['content']['parts'] ?? [];
                            foreach ($parts as $p) {
                                if (!empty($p['functionCall'])) {
                                    $fc = $p['functionCall'];
                                    // Gemini biasanya mengirim satu call per chunk atau sekaligus
                                    $toolCallsRaw[] = [
                                        'id' => 'gemini_call_' . uniqid(),
                                        'name' => $fc['name'] ?? '',
                                        'arguments' => json_encode($fc['args'] ?? []),
                                    ];
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Abaikan chunk yang tidak valid
                    }
                }
                return strlen($data);
            },
        ]);

        $execResult = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error("[StreamSSE] curl error ({$providerCode}): {$curlError}");
        }
        if ($httpCode === 429) {
            throw new \RuntimeException('__RATE_LIMIT__');
        }

        // Transform toolCallsRaw ke format yang dimengerti runAgenticLoop
        $toolCalls = [];
        foreach ($toolCallsRaw as $tc) {
            $toolCalls[] = [
                'id' => $tc['id'],
                'type' => 'function',
                'function' => [
                    'name' => $tc['name'],
                    'arguments' => $tc['arguments'],
                ]
            ];
        }

        Log::info("[StreamSSE] Done ({$providerCode}) http={$httpCode} text_len=" . strlen($fullText) . " tool_calls=" . count($toolCalls));

        return [
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => $fullText,
                        'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                    ],
                    'finish_reason' => !empty($toolCalls) ? 'tool_calls' : 'stop'
                ]
            ]
        ];
    }

    /**
     * Ekstrak token teks dari satu SSE chunk sesuai format provider.
     */
    private function extractTokenFromSseChunk(array $parsed, string $providerCode): string
    {
        if ($providerCode === 'claude') {
            // Claude: {"type":"content_block_delta","delta":{"type":"text_delta","text":"..."}}
            if (($parsed['type'] ?? '') === 'content_block_delta') {
                return $parsed['delta']['text'] ?? '';
            }
            return '';
        }

        if ($providerCode === 'gemini') {
            // Gemini: {"candidates":[{"content":{"parts":[{"text":"..."}]}}]}
            $parts = $parsed['candidates'][0]['content']['parts'] ?? [];
            $text = '';
            foreach ($parts as $p) {
                if (!empty($p['thought']))
                    continue; // skip thinking parts
                $text .= $p['text'] ?? '';
            }
            return $text;
        }

        // OpenAI-compatible (OpenAI, Mistral, Groq, OpenRouter, custom)
        // Format: {"choices":[{"delta":{"content":"..."},"finish_reason":null}]}
        return $parsed['choices'][0]['delta']['content'] ?? '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROVIDER API IMPLEMENTATIONS (non-streaming, untuk tool call loops)
    // ─────────────────────────────────────────────────────────────────────────

    private function callOpenAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = ''): ?array
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $payload = [
            'model' => $model->model_name,
            'messages' => $messages,
            'max_tokens' => (int) $maxTokens,
            'temperature' => 0.3,
        ];
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        $response = Http::timeout(600)
            ->retry(3, 2000)
            ->withToken($apiKey->api_key)
            ->post($url, $payload);
        return $this->handleProviderResponse($response, 'openai');
    }

    private function callMistralApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = ''): ?array
    {
        $url = 'https://api.mistral.ai/v1/chat/completions';
        $payload = [
            'model' => $model->model_name,
            'messages' => $messages,
            'max_tokens' => (int) $maxTokens,
            'temperature' => 0.3,
        ];
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        $response = Http::timeout(600)
            ->retry(3, 2000)
            ->withToken($apiKey->api_key)
            ->post($url, $payload);
        return $this->handleProviderResponse($response, 'mistral');
    }

    private function callClaudeApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = ''): ?array
    {
        $url = 'https://api.anthropic.com/v1/messages';
        $payload = [
            'model' => $model->model_name,
            'max_tokens' => (int) $maxTokens,
            'messages' => $messages,
        ];
        if (!empty($systemPrompt)) {
            $payload['system'] = $systemPrompt;
        }
        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }
        $response = Http::timeout(600)
            ->retry(3, 2000)
            ->withHeaders([
                'x-api-key' => $apiKey->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post($url, $payload);
        return $this->handleProviderResponse($response, 'claude');
    }

    private function callGeminiApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = '', int $loopCount = 1): ?array
    {
        $currentModelName = $model->model_name ?? 'gemini-1.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $currentModelName . ':generateContent?key=' . $apiKey->api_key;

        $payload = [
            'contents' => $messages,
            'generationConfig' => [
                'maxOutputTokens' => (int) $maxTokens,
                'temperature' => 0.7,
            ],
        ];

        // Gemini 2.5 Flash/Pro: nonaktifkan thinking mode agar tidak ada empty parts.
        // thinkingConfig dengan budgetTokens=0 menonaktifkan extended thinking.
        if (str_contains($currentModelName, '2.5')) {
            $payload['generationConfig']['thinkingConfig'] = [
                'thinkingBudget' => 0,
            ];
        }

        if (!empty($systemPrompt)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            // Loop 1-3: paksa Gemini WAJIB panggil tool (mode ANY).
            // Loop berikutnya: biarkan AUTO agar bisa balas teks jika sudah punya data.
            $toolMode = ($loopCount <= 3) ? 'ANY' : 'AUTO';
            $payload['toolConfig'] = [
                'functionCallingConfig' => ['mode' => $toolMode],
            ];
            Log::info("[Agentic] Gemini toolConfig mode={$toolMode} loop={$loopCount}");
        }
        $payloadJson = json_encode($payload);
        Log::info("[Agentic] Sending request to Gemini. Model={$currentModelName} PayloadSize=" . strlen($payloadJson) . " bytes");

        $response = Http::timeout(600)->retry(3, 2000)->withBody($payloadJson, 'application/json')->post($url);

        if ($response->status() === 503 && $currentModelName !== 'gemini-1.5-flash') {
            Log::warning("[Agentic] Model {$currentModelName} busy (503). Falling back to gemini-1.5-flash.");
            $fallbackUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey->api_key;
            $response = Http::timeout(600)->retry(2, 2000)->withBody($payloadJson, 'application/json')->post($fallbackUrl);
        }
        return $this->handleProviderResponse($response, 'gemini');
    }

    private function callCustomApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = '', int $loopCount = 1): ?array
    {
        $baseUrl = rtrim($apiKey->provider->base_url ?? 'https://api.openai.com', '/');
        $url = $baseUrl . '/chat/completions';

        $providerCode = strtolower($apiKey->provider->code ?? '');
        $isGroq = $providerCode === 'groq' || str_contains($baseUrl, 'groq.com');
        $isOpenRouter = $providerCode === 'openrouter' || str_contains($baseUrl, 'openrouter.ai');

        // ════════════════════════════════════════════════════════════
        // NORMALISASI MESSAGES — berlaku untuk SEMUA provider custom
        // Standar OpenAI-compatible: assistant.content wajib string,
        // tool.content wajib string, tool_calls.arguments wajib string.
        // ════════════════════════════════════════════════════════════
        $normalizedMessages = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? '';

            if ($role === 'assistant') {
                // content harus string (bukan null / array)
                $m['content'] = is_string($m['content'] ?? null) ? $m['content'] : '';
                // normalisasi setiap tool_call
                if (!empty($m['tool_calls'])) {
                    $m['tool_calls'] = array_map(function ($tc) {
                        $tc['type'] = $tc['type'] ?? 'function';
                        if (isset($tc['function']['arguments']) && !is_string($tc['function']['arguments'])) {
                            $tc['function']['arguments'] = json_encode($tc['function']['arguments']);
                        }
                        return $tc;
                    }, $m['tool_calls']);
                }
            }

            if ($role === 'tool') {
                // content harus string
                if (!is_string($m['content'] ?? null)) {
                    $m['content'] = json_encode($m['content'] ?? '');
                }
            }

            $normalizedMessages[] = $m;
        }
        $messages = $normalizedMessages;

        // ════════════════════════════════════════════════════════════
        // NORMALISASI TOOLS — sanitasi parameter schema kosong
        // Groq (dan beberapa provider lain) menolak tools dengan
        // "properties": [] (array) — harus object kosong: {}
        // ════════════════════════════════════════════════════════════
        if (!empty($tools)) {
            $tools = array_map(function ($tool) {
                if (!isset($tool['function']['parameters']))
                    return $tool;
                $params = &$tool['function']['parameters'];
                $props = $params['properties'] ?? null;
                $isEmpty = $props === null || $props === [] ||
                    ($props instanceof \stdClass && (array) $props === []);
                if ($isEmpty) {
                    $params['properties'] = new \stdClass();
                    unset($params['required']);
                }
                return $tool;
            }, $tools);
        }

        // ════════════════════════════════════════════════════════════
        // GROQ — History Pruning untuk manajemen TPM
        // Pangkas tool results lama agar tidak meledak di token limit.
        // PENTING: Hanya truncate pesan lama (bukan 4 pesan terakhir).
        // ════════════════════════════════════════════════════════════
        if ($isGroq && $loopCount >= 3) {
            $totalMessages = count($messages);
            $guardZone = 4; // jaga 4 pesan terakhir tetap utuh
            $prunedCount = 0;
            for ($i = 0; $i < $totalMessages - $guardZone; $i++) {
                if (($messages[$i]['role'] ?? '') === 'tool' && strlen($messages[$i]['content'] ?? '') > 500) {
                    $toolName = $messages[$i]['name'] ?? 'unknown';
                    $messages[$i]['content'] = json_encode([
                        'status' => 'success',
                        'message' => "Previous result from '{$toolName}' pruned for token efficiency.",
                    ]);
                    $prunedCount++;
                }
            }
            if ($prunedCount > 0) {
                Log::info("[Agentic] Groq: pruned {$prunedCount} stale tool results at loop #{$loopCount}");
            }
        }

        // ════════════════════════════════════════════════════════════
        // BUILD PAYLOAD — standar OpenAI-compatible
        // ════════════════════════════════════════════════════════════
        $payload = [
            'model' => $model->model_name,
            'messages' => $messages,
            'max_tokens' => (int) $maxTokens,
            'temperature' => 0.3,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        // Groq tidak support parallel tool calls
        if ($isGroq) {
            $payload['parallel_tool_calls'] = false;
        }

        // ════════════════════════════════════════════════════════════
        // CUSTOM HEADERS — per provider
        // ════════════════════════════════════════════════════════════
        $httpRequest = Http::timeout(600)->retry(3, 2000);

        if ($isOpenRouter) {
            $httpRequest = $httpRequest->withHeaders([
                'HTTP-Referer' => config('app.url', 'http://localhost'),
                'X-Title' => 'MBI Agentic DataBot',
            ]);
        }

        Log::info("[Agentic] callCustomApi provider={$providerCode} loop={$loopCount}"
            . " isGroq=" . ($isGroq ? 'true' : 'false')
            . " isOpenRouter=" . ($isOpenRouter ? 'true' : 'false')
            . " msg_count=" . count($messages)
            . " tool_count=" . count($tools));

        $response = $httpRequest
            ->withToken($apiKey->api_key)
            ->post($url, $payload);

        return $this->handleProviderResponse($response, $providerCode);
    }
}