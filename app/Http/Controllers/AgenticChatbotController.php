<?php

namespace App\Http\Controllers;

use App\Exports\ChatTableExport;
use App\Services\ToolCallExecutor;
use App\Services\ApiKeyResolver;
use App\Services\Mcp\McpClientService;
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
    private $maxHistory = 15;

    private \App\Services\ToolCallExecutor $toolExecutor;
    private \App\Services\Core\QueryService $queryService;
    private McpClientService $mcpClient;

    public function __construct(\App\Services\ToolCallExecutor $toolExecutor, \App\Services\Core\QueryService $queryService)
    {
        $this->toolExecutor = $toolExecutor;
        $this->queryService = $queryService;
        $this->mcpClient = new McpClientService();
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

        $allApiKeys = ApiKeyResolver::getKeysForProvider($user, $selectedModel->provider_id);

        if ($allApiKeys->isEmpty()) {
            return response()->json(['error' => 'Mohon maaf, akses layanan analisis AI belum dikonfigurasi. Harap hubungi Administrator Sistem.'], 403);
        }

        $firstAvailableKey = ApiKeyResolver::pickAvailable($allApiKeys);
        if (!$firstAvailableKey) {
            return response()->json(['error' => 'Mohon maaf, semua kuota API untuk layanan ini telah habis. Silakan coba kembali besok atau hubungi Administrator Sistem.'], 429);
        }

        $allowedDatabases = [];
        if ($user->is_admin) {
            $conns = \App\Models\DatabaseConnection::active()->get();
            foreach ($conns as $c) {
                $tables = $c->getTables();
                if (empty($tables)) {
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
                    } else {
                        // Table is '*'
                        $allowedDatabases[$db][$schemaKey][] = '*';
                    }
                }
            }
        } else {
            Log::warning("[Agentic] User ID {$user->id} has no roleModel and is not admin. allowedDatabases will be empty.");
        }

        Log::info("[Agentic] Final allowedDatabases for User ID {$user->id}: " . json_encode($allowedDatabases));

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

        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_implicit_flush(true);

        return response()->stream(
            function () use ($messages, $allApiKeys, $selectedModel, $allowedDatabases, $chatSessionId, $maxTokens) {
                try {
                    $this->runAgenticLoop($messages, $allApiKeys, $selectedModel, $allowedDatabases, $chatSessionId, $maxTokens);
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

    private function runAgenticLoop(array $messages, \Illuminate\Support\Collection $apiKeys, $model, array $allowedDatabases = [], $chatSessionId = null, $maxTokens = null): void
    {
        $apiKey = ApiKeyResolver::pickAvailable($apiKeys);
        if (!$apiKey) {
            $this->streamText('Mohon maaf, semua kuota API untuk layanan ini telah habis. Silakan coba kembali besok.');
            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0)
                ob_flush();
            flush();
            return;
        }

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

        // ── MCP Client: update RBAC dan ambil tool definitions ──────────────
        $this->toolExecutor->setAllowedTables($allowedDatabases); // backward compat
        $this->mcpClient->setAllowedDbs($allowedDatabases);
        $tools = $this->mcpClient->listTools();
        $loopCount = 0;
        $allTurnToolResults = [];
        $textContent = '';

        $lastExecutedToolName = null;

        $terminalTools = [
            'execute_query',
            'get_erp_guidance',
            'get_erp_menu_navigation',
            'fetch_erp_guidance_from_web',
        ];

        $executeQueryCount = 0;
        $lastExecutedSql = '';

        $probeQueryCount = 0;
        $maxProbeQueries = 2;

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            $providerCode = strtolower($apiKey->provider->code ?? '');
            $isGroq = $providerCode === 'groq' || str_contains($apiKey->provider->base_url ?? '', 'groq.com');
            Log::info("[Agentic] Loop #{$loopCount} - Model: " . $model->model_name);

            $isProbeQuery = $executeQueryCount > 0
                && stripos($lastExecutedSql, 'SELECT DISTINCT') !== false
                && stripos($lastExecutedSql, 'GROUP BY') === false;

            // Gunakan streaming sejak loop pertama agar jawaban langsung (out-of-scope) tetap mengalir ke user
            $useStreaming = true;

            try {
                if ($useStreaming) {
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
                            ApiKeyResolver::markLimitReached($apiKey);
                            $nextKey = ApiKeyResolver::pickNextAvailable($apiKeys, $apiKey->id);
                            if ($nextKey) {
                                Log::info("[Agentic] Streaming rate limit on key_id={$apiKey->id}. Rotating ke key_id={$nextKey->id} ({$nextKey->key_name}).");
                                $apiKey = $nextKey;
                                $loopCount--;
                                continue;
                            }
                            $this->streamText('Mohon maaf, semua kuota API untuk layanan ini telah habis hari ini. Silakan coba kembali besok atau hubungi Administrator Sistem.');
                            echo "data: [DONE]\n\n";
                            if (ob_get_level() > 0)
                                ob_flush();
                            flush();
                            return;
                        }
                        throw $e;
                    }

                    $assistantMsg = $response['choices'][0]['message'] ?? [];
                    $finishReason = $response['choices'][0]['finish_reason'] ?? 'stop';
                    $toolCalls = $assistantMsg['tool_calls'] ?? [];
                    $textContent = $assistantMsg['content'] ?? '';

                    if (!empty(trim($textContent)) && empty($toolCalls)) {
                        $textContent = $this->stripThinkingLeakage($textContent);
                        $textContent = $this->processContentForCharts($textContent, $allTurnToolResults);

                        ApiKeyResolver::autoResetIfNeeded($apiKey);
                        $apiKey->recordUsage((int) ($response['_tokens'] ?? 0));

                        if ($chatSessionId) {
                            $textContent = $this->injectSmartTableDataIntoContent($textContent, $allTurnToolResults);
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

                    if (empty($textContent) && empty($toolCalls)) {
                        Log::warning("[Agentic] Streaming returned empty, falling back to non-streaming for loop #{$loopCount}");
                        $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount);
                    }
                } else {
                    $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount);
                }
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === '__RATE_LIMIT__') {
                    ApiKeyResolver::markLimitReached($apiKey);
                    $nextKey = ApiKeyResolver::pickNextAvailable($apiKeys, $apiKey->id);
                    if ($nextKey) {
                        Log::info("[Agentic] Non-streaming rate limit on key_id={$apiKey->id}. Rotating ke key_id={$nextKey->id} ({$nextKey->key_name}).");
                        $apiKey = $nextKey;
                        $loopCount--;
                        continue;
                    }
                    $this->streamText('Mohon maaf, semua kuota API untuk layanan ini telah habis hari ini. Silakan coba kembali besok atau hubungi Administrator Sistem.');
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

            if ($loopCount === 1) {
                ApiKeyResolver::autoResetIfNeeded($apiKey);
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
                $processedContent = $this->injectSmartTableDataIntoContent($processedContent, $allTurnToolResults);

                if ($chatSessionId) {
                    ChatMessage::create([
                        'chat_session_id' => $chatSessionId,
                        'role' => 'assistant',
                        'content' => $processedContent,
                        'tool_results' => !empty($allTurnToolResults) ? $allTurnToolResults : null
                    ]);
                }

                $apiKey->recordUsage((int) ($response['_tokens'] ?? 0));

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

                echo "data: " . json_encode([
                    'tool_call' => [
                        'id' => $toolCallId,
                        'name' => $toolName,
                        'arguments' => $arguments,
                        'status' => 'running',
                    ]
                ]) . "\n\n";
            }
            if (ob_get_level() > 0)
                ob_flush();
            flush();

            $executedResults = [];
            if ($useParallel) {
                Log::info("[Agentic] Parallel tool execution: {$toolCallCount} tools");
                $fibers = [];
                foreach ($processedCalls as $call) {
                    Log::info("[Agentic] Starting Fiber for tool: {$call['name']}");
                    $tName = $call['name'];
                    $tArgs = $call['arguments'];
                    $fiber = new \Fiber(function () use ($tName, $tArgs): string {
                        return $this->mcpClient->callTool($tName, $tArgs);
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
                    'result' => $this->mcpClient->callTool($call['name'], $call['arguments']),
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

                // ── HARD STOP: ACCESS_DENIED_FINAL ───────────────────────────
                // Jika RBAC mengembalikan error ini, putus loop SEKARANG dari sisi PHP.
                // Jangan kirim hasil ke AI untuk di-loop lagi — langsung stream pesan
                // bisnis ke user dan hentikan seluruh agentic loop.
                if (is_array($decodedRes) && ($decodedRes['error'] ?? '') === 'ACCESS_DENIED_FINAL') {
                    $aksesTolakMsg = 'Mohon maaf Bapak/Ibu, permintaan Anda tidak dapat kami proses karena data yang diminta bersifat terbatas dan hanya dapat diakses oleh pihak yang berwenang sesuai kebijakan keamanan data perusahaan. Untuk mendapatkan informasi ini, silakan menghubungi Administrator atau pihak yang memiliki kewenangan akses. Terima kasih atas pengertiannya.';
                    Log::warning("[Agentic] ACCESS_DENIED_FINAL detected on tool '{$toolName}' — breaking loop immediately for User " . \Illuminate\Support\Facades\Auth::id());

                    // Update badge tool menjadi 'denied'
                    echo "data: " . json_encode([
                        'tool_call' => [
                            'id' => $toolCallId,
                            'name' => $toolName,
                            'arguments' => $arguments,
                            'status' => 'denied',
                            'result' => ['message' => $aksesTolakMsg],
                        ]
                    ]) . "\n\n";
                    if (ob_get_level() > 0)
                        ob_flush();
                    flush();

                    // Stream kata per kata dengan delay agar terasa natural seperti AI mengetik
                    $words = explode(' ', $aksesTolakMsg);
                    foreach ($words as $i => $word) {
                        $chunk = ($i === 0 ? '' : ' ') . $word;
                        echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                        if (ob_get_level() > 0)
                            ob_flush();
                        flush();
                        usleep(30000); // 30ms per kata — terasa natural tanpa terlalu lambat
                    }

                    echo "data: " . json_encode(['done' => true]) . "\n\n";
                    if (ob_get_level() > 0)
                        ob_flush();
                    flush();
                    return; // Putus seluruh stream — tidak ada loop lagi
                }

                if (is_array($decodedRes) && $toolName === 'execute_query') {
                    $currencyCols = $decodedRes['currency_columns'] ?? [];
                }

                $aiContent = $toolResult;
                if (is_array($decodedRes) && isset($decodedRes['rows'])) {
                    $rowCount = count($decodedRes['rows']);
                    if ($rowCount > 100) {
                        $aiContent = json_encode([
                            'rows_returned' => $rowCount,
                            'columns' => $decodedRes['columns'] ?? [],
                            'currency_columns' => $decodedRes['currency_columns'] ?? [],
                            'rows' => array_slice($decodedRes['rows'], 0, 100),
                            'instruction' => "ANALYST NOTE: Results are truncated to 100 rows for prompt efficiency, but the UI will show the full list of " . $rowCount . " rows to the user. DO NOT run another query with LIMIT/OFFSET to fetch the remaining rows. The frontend already has the full data. ALWAYS use the columns and sample data from THIS detailed query to build your 'smart_table'. DO NOT use a 1-row COUNT result to build a 'smart_table'—put the count in your text summary instead."
                        ]);
                    } elseif ($rowCount === 0 && $toolName === 'execute_query') {
                        $aiContent = json_encode([
                            'rows_returned' => 0,
                            'columns' => $decodedRes['columns'] ?? [],
                            'rows' => [],
                            'instruction' => "ANALYST NOTE: Query mengembalikan 0 baris (kosong). ⚠️ PERINGATAN KERAS: Jika Anda baru saja mencari data untuk tanggal/periode tertentu (kemarin/hari ini/dsb), ANDA DILARANG mengeksekusi query lagi untuk mencari MAX() date atau tanggal lain. LANGSUNG laporkan ke user bahwa data untuk tanggal tersebut belum tersedia dan BERHENTI memanggil tool execute_query."
                        ]);
                    }
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
                        Log::info("[Agentic] Probe query #{$probeQueryCount}: " . substr($lastExecutedSql, 0, 150));
                        $isProbeLimitReached = ($currentIsProbe && $probeQueryCount >= $maxProbeQueries);
                        if ($isProbeLimitReached) {
                            Log::warning("[Agentic] PROBE LIMIT reached ({$probeQueryCount}/{$maxProbeQueries}). Injecting force-execute reminder.");
                            if (!empty($executedResults)) {
                                $lastIdx = count($executedResults) - 1;
                                $executedResults[$lastIdx]['result'] .= "\n\n**MANDATORY_AI_ACTION**: Limit query probe (SELECT DISTINCT) tercapai ({$probeQueryCount}/{$maxProbeQueries}). Anda DILARANG KERAS melakukan probe lagi. Ambil nilai eksak dari hasil probe di atas dan gunakan langsung untuk query utama dengan WHERE nama_kolom = 'NILAI_EKSAK'. Jangan ILIKE — pakai = (equals) dengan nilai persis dari hasil probe.";
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
                    '_is_live_gemini_response' => true,
                ];

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
            }
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
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '600');

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

        if (count($normalizedRows) > 1500) {
            return response()->json([
                'error' => 'Data terlalu besar untuk format PDF (' . count($normalizedRows) . ' baris). Maksimal 1.500 baris. Silakan gunakan Export Excel untuk mengunduh data sebesar ini.'
            ], 400);
        }

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
            'fontSize' => 10,
            'chartImage' => $chartImage,
            'columnTypes' => $columnTypes,
        ]);

        $paperWidth = max(842, count($headers) * 130);
        $pdf->setPaper([0, 0, $paperWidth, 595]);

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
            $geminiMessages = [];
            $prevRole = null;

            foreach ($messages as $m) {
                if ($m['role'] === 'system')
                    continue;

                $role = $m['role'];

                if ($role === 'tool') {
                    $rawContent = $m['content'] ?? '';
                    if (is_array($rawContent)) {
                        $rawContent = json_encode($rawContent);
                    }

                    if (!empty($m['decoded_data']) && is_array($m['decoded_data'])) {
                        $parsedContent = $m['decoded_data'];
                    } else {
                        $decoded = is_string($rawContent) ? json_decode($rawContent, true) : null;
                        $parsedContent = is_array($decoded) ? $decoded : ['result' => (string) $rawContent];
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
                    continue;
                }

                if ($role === 'assistant') {
                    $isLive = !empty($m['_is_live_gemini_response']);
                    $parts = [];

                    if (!empty($m['content'])) {
                        $parts[] = ['text' => (string) $m['content']];
                    }

                    if ($isLive && !empty($m['tool_calls'])) {
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
                    } elseif (!$isLive && !empty($m['tool_calls'])) {
                        // For history assistant messages, also include functionCalls so tool responses have context
                        foreach ($m['tool_calls'] as $tc) {
                            $args = $tc['function']['arguments'] ?? '{}';
                            $argsArr = is_string($args) ? json_decode($args, false) : $args;
                            if (!$argsArr || $argsArr === [])
                                $argsArr = new \stdClass();

                            $parts[] = [
                                'functionCall' => [
                                    'name' => $tc['function']['name'] ?? 'tool',
                                    'args' => $argsArr,
                                ]
                            ];
                        }
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
                $fakeToolCalls = [];
                foreach ($toolResults as $res) {
                    $fakeToolCalls[] = [
                        'id' => 'hist_' . md5($res['tool_name'] . json_encode($res['data'] ?? '')),
                        'type' => 'function',
                        'function' => [
                            'name' => $res['tool_name'] ?? 'query',
                            'arguments' => '{}',
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
                    unset($truncated);
                }
            } else {
                $messages[] = ['role' => $msg['role'] ?? 'user', 'content' => $msg['content'] ?? ''];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SYSTEM PROMPT
    // ─────────────────────────────────────────────────────────────────────────
    private function buildSystemPrompt(array $allowedDatabases = [], bool $scopeLimited = true): string
    {
        $dbSummaries = [];
        foreach ($allowedDatabases as $dbCode => $schemas) {
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

        $mainTablesHint = [];
        try {
            $conns = \App\Models\DatabaseConnection::active()->get();
            foreach ($conns as $conn) {
                $tables = $conn->getTables();
                $tableNames = array_column($tables, 'table_name');

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
- ⚠️ **ATURAN TANGGAL KOSONG**: Jika user meminta data untuk tanggal tertentu (seperti "hari ini" atau "kemarin"), jalankan query HANYA untuk tanggal tersebut. Jika hasilnya kosong, **DILARANG KERAS** mencari tanggal terakhir (MAX date) dan mengubah definisi waktu. Langsung beritahu user bahwa data untuk tanggal tersebut belum tersedia.

## DATABASE TERSEDIA UNTUK ANDA:
{$dbSummaryText}

## 🔴 INSTRUKSI PERTAMA (EFEKTIF & INSTAN)

**Daftar Tabel Utama (Gunakan Jika Relevan):**
{$tableHintText}

Jika pertanyaan user sudah jelas berkaitan dengan tabel di atas, **LANGSUNG panggil `describe_table`** (Lewati `get_database_schema_info`).
HANYA panggil `get_database_schema_info` jika tabel yang Anda butuhkan tidak ada di daftar atas.

**DAFTAR PERTANYAAN BISNIS YANG PASTI VALID (WAJIB DIJAWAB DENGAN TOOL):**
- **Agregasi (Total/Jumlah):** "total cabang", "jumlah cabang", "berapa cabang", "total dealer", "berapa dealer", "total omset", "total penjualan"
- **Detail (Daftar/List):** "cabang", "daftar cabang", "tampilkan cabang", "rincian cabang", "dealer aktif", "daftar dealer", "list produk"
- **Analisis:** "data penjualan", "omset", "revenue", "netto", "HPP", "harga pokok", "profit", "laba", "margin", "stok", "inventory", "barang"
- **Administrasi:** "laporan", "rekap", "ringkasan", "summary", "piutang", "hutang", "receivable", "payable", "keuangan", "finance", "neraca", "balance"
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

## 🔴 ATURAN DAFTAR & RINCIAN — WAJIB UNTUK "TAMPILKAN", "DAFTAR", "LIST"

Jika user menggunakan kata kerja **"tampilkan"**, **"daftar"**, **"list"**, atau **"rincian"** [entitas] (contoh: "tampilkan cabang", "daftar dealer", "rincian penjualan"):

1. **WAJIB** sajikan data dalam bentuk **smart_table** yang berisi baris detail (BUKAN agregasi).
2. **DILARANG** melakukan `GROUP BY` atau agregasi summary jika user meminta detail/daftar.
3. **BATASAN QUERY**: **DILARANG** menjalankan lebih dari 1 query utama. JANGAN jalankan query tambahan untuk distribusi (per regional/provinsi) atau statistik kecuali user memintanya secara eksplisit.
4. **CHART PROHIBITION (MUTLAK)**: **DILARANG KERAS** menampilkan blok `chart` untuk permintaan daftar/tampilkan. User ingin melihat data, bukan grafik.
5. **PEMILIHAN KOLOM**: Jika user tidak menyebutkan kolom, pilih 5-7 kolom paling relevan (ID/Kode, Nama, Alamat, Kota, Status, dll).
6. **FORMAT OUTPUT**: Langsung sajikan tabel setelah Ringkasan Eksekutif. Jangan tambahkan teks pengantar teknis seperti "Grafik sedang disiapkan" atau sejenisnya.
7. ⚠️ **LARANGAN LIMIT/OFFSET**: **DILARANG KERAS** menggunakan klausa `LIMIT` atau `OFFSET` dalam query (contoh: `LIMIT 50`) kecuali user secara eksplisit memintanya (seperti "top 10" atau "5 terbaik"). Biarkan query mengembalikan SELURUH baris secara utuh. Sistem kami sudah dirancang untuk menangani ribuan baris dengan aman.

## 🔴 ATURAN PEMILIHAN DATA UNTUK TABEL (PENTING)

Jika Anda memanggil beberapa tool dan mendapatkan beberapa hasil (misal: satu hasil LIST detail dan satu hasil COUNT total):
1. **WAJIB** gunakan data dari query LIST detail untuk membuat blok `smart_table`.
2. Jika Anda hanya memiliki data rekapitulasi/summary (walaupun hanya 1 baris berisi banyak metrik) dan ingin menampilkannya sebagai tabel, Anda **WAJIB** menampilkannya sebagai blok `smart_table`. **DILARANG KERAS** menggunakan tabel Markdown biasa (`| Kolom | Kolom |`) dengan melakukan pivot data! Jika tidak pakai smart_table, angka rekap harus masuk dalam narasi paragraf biasa.
3. **KONSISTENSI**: Jika data detail transaksi/faktur sudah diambil, **DILARANG KERAS** menggantinya dengan data rekap/summary (seperti rekap per cabang). Meskipun data detail terpotong (truncated) di log Anda, sistem frontend sudah memilikinya secara utuh. Tetap gunakan hasil query detail untuk `smart_table`!
4. **BATASAN EKSEKUSI**: Jika user meminta rincian/transaksi, jalankan **SATU** query detail saja. Jangan jalankan query rekap/summary tambahan kecuali diminta eksplisit.

## PERSONA & GAYA BAHASA (WAJIB DIIKUTI)
- **Persona**: Data Analyst Ahli, profesional, objektif, dan sangat teliti. Anda adalah "Executive Assistant" yang memberikan hasil akhir, bukan kronologi kerja.
- **Bahasa**: Bahasa Bisnis yang Profesional (Sesuai dengan bahasa yang dideteksi dari user).
- **Sapaan**: Selalu sapa pengguna dengan "Bapak/Ibu".

## 🔴 LARANGAN KERAS: JANGAN BOCORKAN "ISI DAPUR" TEKNIS
User adalah level eksekutif yang TIDAK mengerti database. DILARANG KERAS menyebutkan hal berikut dalam jawaban Anda:
1. **DILARANG** menyebut "nama tabel" (misal: view_data_penjualan_xxx).
2. **DILARANG** menyebut "nama kolom" database (misal: nama_propinsi_cabang atau tgl_fak_jl).
3. **DILARANG** menyebut istilah teknis agentic (misal: "hasil probe", "query SQL", "menjalankan query", "mengecek database").
4. **DILARANG** menyebut "0 baris" atau "query mengembalikan data kosong".
5. **DILARANG** meminta izin untuk "melanjutkan pengecekan" atau "mencoba query lain". Lakukan saja secara mandiri selama masih dalam batas turn Anda.
6. **DILARANG** menyebutkan kegagalan teknis atau proses coba-coba (retry) saat Anda sedang memperbaiki query. Jika satu query gagal dan Anda mencoba query lain, JANGAN beritahu user tentang kegagalan tersebut. Cukup berikan hasil akhir yang sukses.
7. **BAHASA BISNIS UNTUK KENDALA TEKNIS**: Jika setelah semua upaya (retry) data tetap tidak ditemukan karena error sistem (timeout/bug), gunakan bahasa bisnis yang sangat sopan:
   - "Mohon maaf Bapak/Ibu, terjadi kendala teknis saat mencoba mengambil data. Saya sedang berkoordinasi dengan sistem untuk memastikan rincian data tersebut dapat ditampilkan kembali."
   - "Mohon maaf Bapak/Ibu, data yang diminta belum dapat kami sajikan saat ini karena terdapat pembaruan pada struktur informasi database. Kami akan segera memperbaikinya."
8. **AKSES TERBATAS (RBAC — PENTING)**: Jika Anda mendapatkan error **'ACCESS_DENIED_FINAL'**, **'TABLE_ACCESS_DENIED'**, **'COLUMN_ACCESS_DENIED'**, atau **'DEEP_RBAC_DENIED'**, ini adalah **KEBIJAKAN KEAMANAN DATA**, bukan error teknis.
   - **WAJIB**: BERHENTI TOTAL — jangan panggil tool apapun lagi (termasuk describe_table, search_schema, get_database_schema_info).
   - **WAJIB**: Sampaikan LANGSUNG kepada Bapak/Ibu user pesan berikut tanpa modifikasi: *"Mohon maaf Bapak/Ibu, permintaan Anda tidak dapat kami proses karena data yang diminta bersifat terbatas dan hanya dapat diakses oleh pihak yang berwenang sesuai kebijakan keamanan data perusahaan. Untuk mendapatkan informasi ini, silakan menghubungi Administrator atau pihak yang memiliki kewenangan akses. Terima kasih atas pengertiannya."*
   - **DILARANG MUTLAK**: Mencari data yang sama di tabel/view lain, mengganti nama kolom, atau mencoba workaround apapun setelah error ini muncul.

**CONTOH BAHASA BISNIS YANG BENAR:**
- Salah (Teknis): "Query saya pada tabel view_xxx mengembalikan 0 baris untuk Sumatera Utara."
- Benar (Bisnis): "Mohon maaf Bapak/Ibu, saat ini belum terdapat catatan transaksi penjualan untuk wilayah Sumatera Utara pada periode tersebut."

- Salah (Teknis): "Saya akan mencoba mengecek kota Medan untuk memverifikasi data karena kolom tgl_fak_jl tidak ditemukan."
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
- `profit`/`laba` hampir tidak pernah kolom tersimpan — hitung: `SUM(col_dpp) - SUM(col_hpp * col_qty)`

## 🔴 ATURAN TERPENTING #1B — RESOLVE NAMA CABANG/ENTITAS SEBELUM QUERY

User sering menyebut nama cabang/dealer/entitas dengan ejaan tidak persis. Nama di database bisa berbeda: "hm yamin" → "HM. YAMIN", "kapt muslim" → "KAPT. MUSLIM 1", dll.

**WAJIB LAKUKAN 2 LANGKAH INI saat user menyebut nama cabang/dealer/entitas SPESIFIK (bukan wilayah/propinsi/kota):**

**Langkah 1 — Resolve nama eksak dengan MULTI-KEYWORD OR (wajib dilakukan SEKALI saja):**

Jika nama user terdiri dari beberapa kata (misal: "hm yamin"), buat filter yang mencari SETIAP kata secara terpisah menggunakan OR:
```sql
SELECT DISTINCT nama_cabang
FROM schema.tabel
WHERE nama_cabang ILIKE '%hm%'
   OR nama_cabang ILIKE '%yamin%'
LIMIT 10
```
**MENGAPA:** "hm yamin" tidak akan match `%hm yamin%` jika di database ada titik ("HM. YAMIN") atau spasi berbeda. Memecah per kata memastikan hasil selalu ditemukan.

**Aturan pemecahan kata kunci:**
- Input 1 kata ("yamin") → 1 kondisi ILIKE: `ILIKE '%yamin%'`
- Input 2 kata ("hm yamin") → 2 kondisi OR: `ILIKE '%hm%' OR ILIKE '%yamin%'`
- Input 3 kata ("kapt muslim barat") → 3 kondisi OR: `ILIKE '%kapt%' OR ILIKE '%muslim%' OR ILIKE '%barat%'`
- **DILARANG KERAS** menggabungkan seluruh input sebagai 1 frase: `ILIKE '%hm yamin%'` ← **ini penyebab 0 hasil!**
- **DILARANG KERAS** menjalankan probe kedua jika probe pertama sudah mengembalikan hasil. Jika probe pertama 0 hasil BARULAH coba variasi lain — HANYA 1 KALI.
- **KRITIS — TIDAK BOLEH PROBE ULANG:** Jika probe pertama berhasil (ada hasil), LANGSUNG gunakan nama eksak itu untuk query utama. JANGAN probe lagi meski hasilnya 1 baris.

**Langkah 2 — Gunakan nama eksak (bukan ILIKE) untuk query utama:**
```sql
WHERE nama_cabang = 'HM. YAMIN'  -- pakai hasil dari Langkah 1
```

Jika Langkah 1 mengembalikan >1 nama, tanya user: "Maksud Bapak/Ibu cabang yang mana? [tampilkan pilihan]".

## 🔴 ATURAN WILAYAH/PROPINSI/KOTA
Jika user bertanya tentang wilayah (Medan, Jakarta, Sumatera Utara, dll):
1. **LANGSUNG gunakan `ILIKE`** pada kolom propinsi/kota yang relevan dalam query utama.
2. Jika query utama tidak menghasilkan data, lakukan penelusuran otomatis (misal: cari berdasarkan nama kota jika propinsi kosong) TANPA meminta izin kepada Bapak/Ibu user.
3. **DILARANG KERAS** membelokkan jawaban ke data Nasional secara diam-diam jika data regional tidak ditemukan. Jika data regional benar-benar tidak ada setelah semua upaya (termasuk cek kota), laporkan dengan bahasa bisnis: "Berdasarkan data yang tersedia, belum ditemukan catatan aktivitas bisnis untuk wilayah [Wilayah] pada periode ini."

- User tanya "cabang di Medan" / "cabang di Sumatera Utara" / "cabang di Jakarta" → **JANGAN resolve nama cabang**
- Untuk pertanyaan berbasis wilayah: **LANGSUNG gunakan filter `ILIKE '%NAMA_WILAYAH%'`** pada kolom propinsi/kabupaten/kota.
- Contoh: `WHERE nama_propinsi_cabang ILIKE '%SUMATERA UTARA%'` atau `WHERE nama_kota_cabang ILIKE '%MEDAN%'`
- Nilai eksak wilayah didapat dari **1 query probe** `SELECT DISTINCT nama_propinsi_cabang ... LIMIT 20` **TANPA filter WHERE**
- Setelah dapat nilai propinsi eksak → **LANGSUNG query utama**, JANGAN probe lagi

**DILARANG** pakai `ILIKE` di query utama jika sudah mendapat nama eksak dari Langkah 1.

### **1. Protokol Penemuan & Pemetaan Dinamis (Dynamic Discovery Protocol)**
Sebagai Data Analyst yang cerdas, Anda memiliki kemampuan untuk memahami struktur database apa pun secara intuitif. Saat user meminta metrik tertentu (misal: "tampilkan netto dan total netto"), ikuti langkah strategis berikut:

1. **Prioritaskan Kolom Fisik**: Periksalah hasil `describe_table` dengan teliti. Jika Anda menemukan kolom yang secara eksplisit mewakili permintaan user (misal: terdapat kolom bernama `netto` atau `total_netto`), gunakanlah kolom tersebut secara langsung untuk menjaga keakuratan data asli.
2. **Interpretasi Semantik**: Jika nama kolom tidak sesuai secara harfiah, gunakan intuisi bisnis Anda untuk memetakan kolom yang tersedia ke dalam peran metrik yang dibutuhkan (seperti metrik penjualan, biaya/HPP, potongan, atau kuantitas).
3. **Penerapan Fallback**: Jika metrik yang diminta benar-benar tidak tersedia sebagai kolom fisik, jangan berhenti. Segera gunakan kecerdasan Anda untuk merakit metrik tersebut melalui kalkulasi matematis antar kolom yang tersedia (sebagaimana dijelaskan pada **Poin 2**).

**TUJUAN**: Memastikan apapun yang diminta user tetap tersaji dengan cara yang paling akurat, baik melalui pemanggilan kolom langsung maupun melalui derivasi cerdas.

### **2. Logika Intuisi Bisnis & Akuntansi (Strategic Reasoning)**
Anda adalah AI Data Scientist yang ahli dalam akuntansi. Jangan menunggu instruksi rumus kaku. Jika user meminta sebuah metrik (misal: "Profit", "Margin", "Netto", "HPP"), Anda **WAJIB** menggunakan logika akuntansi standar untuk merakit rumusnya secara mandiri berdasarkan kolom yang Anda temukan:

1.  **Analisis Hubungan Kolom**: Setelah `describe_table`, identifikasi hubungan antar kolom secara matematis. Misalnya, jika ada kolom `harga_beli` dan `harga_jual`, Anda harus menyimpulkan sendiri bagaimana cara menghitung keuntungan.
2.  **Kreativitas SQL**: Gunakan operasi matematika (`+`, `-`, `*`, `/`) dan fungsi agregasi (`SUM`, `AVG`, `COALESCE`) untuk **menciptakan** kolom yang diminta user.
3.  **DILARANG MENYERAH**: Apapun metrik yang diminta user, Anda **WAJIB** menampilkannya di hasil akhir. Jika kolom fisiknya tidak ada, "ciptakan" kolom tersebut di SQL sebagai kolom kalkulasi dengan alias yang sesuai (contoh: `AS "Profit"`).

**PRINSIP UTAMA**: Anda tidak butuh panduan rumus dari kami. Gunakan kecerdasan Anda untuk merumuskan kalkulasi yang paling logis dan akurat bagi user berdasarkan struktur data yang Anda hadapi.

### **3. Aturan Eksekusi & Output**
- **Wajib Tampil**: Semua metrik yang diminta user **HARUS** ada di tabel hasil, baik berasal dari kolom asli maupun hasil "pemikiran" kalkulasi Anda.
- **Identifikasi Sumber**: Jika data pendukung (misal: harga modal) tidak ada di tabel utama, lakukan `JOIN` ke tabel master yang relevan secara mandiri.
- **Format Profesional**: Sajikan data dalam format horizontal yang bersih. Gunakan alias kolom yang mencerminkan istilah bisnis yang diminta user.

- **FORMAT TABEL LEBAR (WIDE TABLE)**: Anda **WAJIB** menghasilkan data dalam format horizontal (1 baris banyak kolom). Letakkan dimensi (Nama Cabang/Kategori/dll) di kolom pertama, lalu metrik-metrik di kolom-kolom berikutnya. **DILARANG KERAS** memutar (pivot) hasil menjadi format vertikal/baris kecuali diminta per-barang/per-tanggal.

## 🔴 ATURAN TERPENTING #2 — AGREGASI WAJIB (GROUP BY)

Jika user menyebut istilah bisnis (HPP, Netto, Diskon, Profit, Omzet, Qty) **tanpa kata "detail" atau "per transaksi"**, Anda WAJIB:

1. Gunakan `SUM(nama_kolom_dari_describe_table)` — BUKAN nama kolom mentah
2. GROUP BY HANYA kolom dimensi/identitas (nama_cabang, nama_dealer, dll)
3. DILARANG memasukkan kolom moneter ke GROUP BY

**Contoh Pola Berpikir Dinamis (Dynamic Reasoning):**
```sql
-- User: "Berapa profit dan margin di cabang Medan?"
-- AI: *Menjalankan describe_table, menemukan kolom 'total_bayar' dan 'modal_satuan'*
-- AI: *Berpikir: Profit adalah (total_bayar) dikurangi (modal_satuan * qty)*
SELECT nama_cabang AS "Nama Cabang",
       SUM(total_bayar) - SUM(modal_satuan * qty) AS "Profit",
       (SUM(total_bayar) - SUM(modal_satuan * qty)) / NULLIF(SUM(total_bayar), 0) * 100 AS "Margin (%)"
FROM schema.penjualan
WHERE kota = 'MEDAN'
GROUP BY nama_cabang
```
**PENTING**: Contoh di atas hanyalah ilustrasi. Gunakan nama kolom EKSAK dari `describe_table` dan rakit rumus yang paling akurat sesuai intuisi akuntansi Anda.
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
- ⚠️ **ATURAN MUTLAK TABEL**: **DILARANG KERAS menggunakan tabel Markdown biasa (`| Kolom | Kolom |`)**. 
  - **UNTUK HASIL DATABASE (execute_query)**: Anda **WAJIB** mencantumkan blok ```smart_table``` singkat (berisi `title` dan `currency_columns` saja) tepat setelah Ringkasan Eksekutif. Ini sangat penting agar sistem frontend dapat memicu penampilan tabel data secara profesional.
  - **KAPAN PAKAI JSON LENGKAP?**: Anda **WAJIB** menggunakan JSON lengkap (`headers` dan `rows`) jika data yang ditampilkan berasal dari pengetahuan internal Anda (data global, informasi umum, data non-database).
  - ⚠️ **ATURAN MUTLAK DATA GLOBAL**: Jika Anda memberikan data yang TIDAK berasal dari database (misal: "Penjualan Mobil Global 2024"), Anda **TETAP WAJIB** menyajikannya dalam format `smart_table`. **DILARANG KERAS** menggunakan tabel Markdown biasa (`| Col | Col |`).
  - Contoh format untuk data global/internal:
    ```smart_table
    {
      "title": "Data Penjualan Global 2024",
      "headers": ["Wilayah", "Unit Terjual", "Market Share"],
      "rows": [
        ["Asia", "15.000.000", "45%"],
        ["Eropa", "8.000.000", "24%"]
      ],
      "currency_columns": []
    }
    ```
    JANGAN gunakan Markdown table!
- Hasil query **1 baris, 1 kolom** (angka tunggal, e.g. `COUNT(*)` saja) → **DILARANG KERAS**. Sebutkan angkanya langsung dalam narasi.
  - ✅ BENAR: "**Perusahaan memiliki total 93 cabang yang aktif.**"
  - ❌ SALAH: Membuat tabel `| 93 |` hanya untuk satu angka
  - ❌ SALAH: Membuat `smart_table` dengan 1 header dan 1 baris berisi angka tunggal

Struktur JSON smart_table:
- `title` (string): **WAJIB**. Berikan judul tabel yang sangat deskriptif dan profesional.
- **KOLOM PERTAMA**: Kolom pertama dalam `rows` **WAJIB** berisi identitas baris (Nama Cabang, Nama Barang, Periode, dll).
- `currency_columns` (array string): **HANYA** kolom yang berisi nilai UANG (Rp).
- ⚠️ **DILARANG KERAS** menyertakan array `headers` atau `rows` di dalam JSON. Sistem frontend akan memetakan dan menyuntikkan data baris dari kueri secara otomatis!
- ⚠️ **DILARANG KERAS** mengetik ulang isi data dalam bentuk teks Markdown biasa (seperti list atau tabel `| Kolom |`) di bawah blok `smart_table`. Cukup berikan blok JSON `smart_table` singkat saja, dan data akan divisualisasikan sepenuhnya oleh sistem!

**ATURAN CURRENCY_COLUMNS (KRITIS):**
- ✅ MASUKKAN: kolom dengan nilai rupiah/mata uang (total_netto, hpp, revenue, omset, profit, dll)
- ❌ JANGAN MASUKKAN: kolom COUNT, jumlah cabang, jumlah dealer, qty, persentase, ID, kode
- Contoh SALAH: `"currency_columns":["Total Cabang"]` ← angka 91 akan diformat Rp 91!
- Contoh BENAR: `"currency_columns":["Netto","Total Netto","HPP","Total HPP","Diskon","Profit"]`

**⚠️ ATURAN NOMINAL (SANGAT KRITIS):**
Untuk semua nilai uang/mata uang (baik dari database maupun data eksternal/global), Anda **WAJIB** menuliskan angka nominal LENGKAP sebagai integer murni tanpa pemisah ribuan dan tanpa singkatan (K/jt/M/rb).
- ✅ BENAR: 150000, 2750000
- ❌ SALAH: 150 (maksudnya 150rb), 150K, 200k, 2.75 (maksudnya 2.75jt)
- **RENTANG HARGA**: Jika menyajikan rentang harga dalam tabel, WAJIB gunakan angka penuh dipisahkan tanda hubung. Contoh: "200000-300000" (BUKAN "200-300").
- **DATA GLOBAL**: Aturan ini berlaku mutlak untuk data yang Anda berikan dari pengetahuan internal Anda. Jangan pernah gunakan singkatan harga.

**⚠️ ATURAN TABEL RINGKASAN MANUAL (KRITIS — WAJIB DIBACA):**
Jika Anda membuat tabel ringkasan per kunjungan/faktur (misalnya "Rincian Transaksi Kunjungan 1"), nilai yang Anda tulis di dalam tabel WAJIB diambil langsung dari data SQL yang sudah Anda ambil — BUKAN dari hasil kalkulasi di kepala Anda.
- ✅ BENAR: Nilai dari baris data asli, misalnya `Rp 517.000` jika kolom `hrg_jual` bernilai 517000.
- ❌ SALAH: Menulis `Rp 517` karena Anda "pikir" satuannya ribuan — INI KESALAHAN FATAL.
- **WAJIB**: Nilai rupiah di tabel ringkasan manual HARUS identik dengan nilai di smart_table utama yang dihasilkan dari data SQL.
- **DILARANG**: Mempersingkat, mengubah skala, atau menebak nilai numerik. Jika nilai di database adalah 517000, tulis 517000 di tabel.

## 🔴 ATURAN FORMATTING — KODE BLOK (PENTING)
Setiap blok `smart_table` atau `chart` **WAJIB** dibuka dengan triple backtick (```) diikuti langsung oleh identifier (smart_table atau chart), lalu isi JSON, dan ditutup dengan triple backtick.
- ✅ BENAR: \` \` \`smart_table\n{"title":...}\n\` \` \`
- ❌ SALAH: Menambahkan teks pengantar seperti "Berikut tabelnya:" atau "📊 [Sedang disiapkan]" di antara pembuka backtick dan JSON.
- **DILARANG** menambahkan karakter apapun (seperti ikon 📊 atau 📈) sebelum atau sesudah blok di baris yang sama.

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

**URUTAN WAJIB jika user minta grafik/analisis:**
1. Ringkasan Eksekutif
2. chart (grafik visualisasi)
3. smart_table (tabel data)
4. Insight Strategis

**URUTAN WAJIB jika user minta daftar/tampilkan:**
1. Ringkasan Eksekutif
2. smart_table (tabel data detail)
3. Insight Strategis (singkat, tanpa grafik tambahan)

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
2. `search_schema` — Cari tabel/kolom berdasarkan kata kunci. **ATURAN KETAT: GUNAKAN HANYA JIKA tabel tidak ditemukan dari daftar tabel di prompt atau get_database_schema_info. Panggil MAKSIMAL 1 KALI per topik. Gunakan kata kunci yang luas (misal: "penjualan" atau "barang") daripada spesifik (misal: "ban"). Jika sudah ada tabel yang terlihat relevan (mengandung 'penjualan', 'transaksi', 'barang', 'item') → STOP, langsung ke describe_table.**
3. `describe_table` — **WAJIB DIPANGGIL** sebelum execute_query. Dapatkan nama kolom EKSAK.
4. `get_column_values` — **DILARANG untuk tabel/VIEW dengan nama mengandung "view_"**. Untuk VIEW, gunakan execute_query SELECT DISTINCT sebagai gantinya. Untuk tabel fisik kecil: ambil nilai unik dari kolom sebelum query utama.
5. `get_view_definition` — Dapatkan DDL/logika di balik sebuah View.
6. `get_table_preview` — Ambil 5 baris contoh data untuk memahami format.
7. `execute_query` — Eksekusi SQL SELECT. Wajib prefix schema!
8. `get_erp_guidance` / `get_erp_menu_navigation` / `fetch_erp_guidance_from_web` — Panduan ERP.

## ERP GUIDANCE & NAVIGATION
1. Saat `get_erp_menu_navigation` mengembalikan `display_text`, tampilkan **verbatim**. JANGAN tambahkan "Ringkasan Eksekutif".
2. **PANDUAN ERP (Inline Images)**: Saat menyajikan panduan dari `get_erp_guidance`, Anda **WAJIB** menyertakan seluruh konten dari field `detail_panduan_lengkap` secara utuh.
3. **PENTING**: Anda **DILARANG** merangkum atau menghilangkan tag gambar Markdown (`![alt](url)`) yang ada di dalam `detail_panduan_lengkap`. Sertakan gambar tersebut tepat di lokasi aslinya di dalam teks agar muncul secara inline (teks -> gambar -> teks) sesuai format aslinya.
4. Gunakan gaya bahasa profesional Bapak/Ibu saat memberikan pengantar sebelum konten panduan tersebut.

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

## 🔴 ATURAN OPTIMASI QUERY (UNTUK KECEPATAN)
Untuk memastikan respon yang cepat dan hemat resource, Anda **WAJIB** menerapkan prinsip optimasi berikut:
1. **Penyaringan Seawal Mungkin**: Selalu gunakan klausa `WHERE` yang spesifik (tanggal, cabang, atau kategori) untuk membatasi jumlah data yang diproses database.
2. **Pilih Kolom Spesifik**: Hindari `SELECT *`. Hanya ambil kolom yang benar-benar dibutuhkan untuk `smart_table`.
3. **Optimasi View**: Jika sebuah View terasa lambat, gunakan `get_view_definition` untuk menganalisis logikanya. Jika Anda menemukan join yang berat atau tidak efisien, Anda **BOLEH** menyarankan optimasi skema/view kepada Bapak/Ibu user di bagian Insight.
4. **Hindari ILIKE Berlebihan**: Gunakan operator `=` (sama dengan) jika Anda sudah mendapatkan nilai eksak dari hasil probe. `ILIKE` jauh lebih lambat daripada `=`.
5. **Gunakan Agregasi Database**: Biarkan database yang menghitung (SUM, COUNT, AVG) daripada menarik data detail lalu menghitungnya sendiri.

## ATURAN SQL
- **PostgreSQL**: prefix wajib `schema_name.table_name` (contoh: `sch_mbi.view_data_penjualan_rinci_mbi`)
- **MySQL/MariaDB**: JANGAN pakai prefix schema — cukup `table_name` saja (contoh: `SELECT * FROM nama_tabel`). MySQL tidak punya konsep schema terpisah.
- **POSTGRESQL STRUCTURE (CRITICAL)**: Hanya gunakan format 2 level: `schema.table` (contoh: `sch_mbi.view_penjualan`). **DILARANG KERAS** menggunakan format 3 level seperti `schema.table.column` di dalam klausa `FROM` atau `JOIN`. Kolom hanya boleh diletakkan di `SELECT`, `WHERE`, `GROUP BY`, dll.
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
Jika `execute_query` timeout, 0 rows, atau error database/kolom:
1. JANGAN simpulkan "data tidak ada" dan JANGAN beritahu user tentang detail error teknis (seperti "column does not exist") di awal.
2. WAJIB panggil `describe_table` → perbaiki query (pastikan nama kolom benar, filter tanggal tepat, dan alias sesuai) → retry query.
3. Ulangi proses ini (debug & retry) minimal 3 kali dengan strategi berbeda sebelum akhirnya melapor kendala teknis kepada user.

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

    /**
     * Inject actual rows+headers data into smart_table blocks before saving to DB.
     *
     * Problem: AI emits ```smart_table {"tool_index": 0, "title": "..."}``` blocks.
     * At stream-time the frontend JS has data in RAM (currentToolResults).
     * But when history is reloaded, that RAM is gone. The DB-stored content still
     * has {"tool_index": 0} with NO rows/headers, so the table can't render.
     *
     * Solution: Before saving to DB, replace every tool_index reference with the
     * actual headers+rows data embedded inline inside the JSON block.
     * On reload, initSmartTablesInBubble reads data-headers-b64 / data-rows-b64
     * directly from DOM attributes — no RAM dependency.
     */
    private function injectSmartTableDataIntoContent(string $content, array $toolResults): string
    {
        if (empty($toolResults) || strpos($content, 'smart_table') === false) {
            return $content;
        }

        $content = preg_replace_callback(
            '/```smart_table\s*([\s\S]*?)```/m',
            function (array $matches) use ($toolResults) {
                $rawJson = trim($matches[1]);
                if (empty($rawJson))
                    return $matches[0];

                try {
                    $params = json_decode($rawJson, true);
                    if (!is_array($params))
                        return $matches[0];

                    // Skip if already has inline data
                    if (!empty($params['headers']) && !empty($params['rows'])) {
                        return $matches[0];
                    }

                    $toolIdx = isset($params['tool_index']) ? (int) $params['tool_index'] : -1;

                    $toolRes = null;
                    if ($toolIdx >= 0 && !empty($toolResults[$toolIdx])) {
                        $toolRes = $toolResults[$toolIdx];
                    } else {
                        foreach (array_reverse($toolResults) as $tr) {
                            $d = $tr['data'] ?? null;
                            if (is_array($d) && !empty($d['rows']) && !empty($d['columns'])) {
                                $toolRes = $tr;
                                break;
                            }
                        }
                    }

                    if (!$toolRes)
                        return $matches[0];

                    $tableData = $toolRes['data'] ?? null;
                    if (is_string($tableData)) {
                        $tableData = json_decode($tableData, true) ?: null;
                    }
                    if (!is_array($tableData))
                        return $matches[0];

                    $rawRows = $tableData['rows'] ?? [];
                    $columns = $tableData['columns'] ?? [];

                    if (empty($rawRows) || empty($columns))
                        return $matches[0];

                    $normalizedRows = array_map(function ($row) use ($columns) {
                        if (is_array($row) && array_values($row) === $row)
                            return $row;
                        if (is_array($row))
                            return array_map(fn($c) => $row[$c] ?? null, $columns);
                        return [$row];
                    }, $rawRows);

                    $newParams = $params;
                    $newParams['headers'] = $columns;
                    $newParams['rows'] = $normalizedRows;
                    if (!isset($newParams['currency_columns']) && !empty($toolRes['currency_columns'])) {
                        $newParams['currency_columns'] = $toolRes['currency_columns'];
                    }
                    if (empty($newParams['title']) && !empty($toolRes['label'])) {
                        $newParams['title'] = $toolRes['label'];
                    }

                    $newJson = json_encode($newParams, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    Log::info('[SmartTableInject] Injected ' . count($normalizedRows) . ' rows (tool_index=' . $toolIdx . ')');
                    return "```smart_table\n" . $newJson . "\n```";

                } catch (\Throwable $e) {
                    Log::warning('[SmartTableInject] Failed: ' . $e->getMessage());
                    return $matches[0];
                }
            },
            $content
        );

        return $content;
    }

    private function processContentForCharts(string $content, array $toolResults): string
    {
        // 1. Convert Markdown tables to smart_table JSON blocks
        if (strpos($content, '|') !== false) {
            $lines = explode("\n", $content);
            $newLines = [];
            $currentTable = [];
            $isInsideTable = false;

            foreach ($lines as $line) {
                $trimmed = trim($line);
                // Simple regex for markdown table row
                if (preg_match('/^\|.*\|$/', $trimmed)) {
                    $isInsideTable = true;
                    $currentTable[] = $trimmed;
                } else {
                    if ($isInsideTable) {
                        // Process the collected table
                        $newLines[] = $this->convertMarkdownToSmartTable($currentTable);
                        $currentTable = [];
                        $isInsideTable = false;
                    }
                    $newLines[] = $line;
                }
            }
            if ($isInsideTable) {
                $newLines[] = $this->convertMarkdownToSmartTable($currentTable);
            }
            $content = implode("\n", $newLines);
        }

        // 2. Fix missing backticks for smart_table JSON if AI output raw JSON
        if (strpos($content, '"headers"') !== false && strpos($content, '"rows"') !== false) {
            if (strpos($content, '```smart_table') === false) {
                // Try to find raw JSON and wrap it
                $content = preg_replace_callback('/\{\s*"title":\s*".*?"\s*,\s*"headers":\s*\[.*?\].*?\}/s', function ($matches) {
                    return "```smart_table\n" . $matches[0] . "\n```";
                }, $content);
            }
        }

        return $content;
    }

    private function convertMarkdownToSmartTable(array $tableLines): string
    {
        // Need at least header and separator or header and data
        if (count($tableLines) < 2)
            return implode("\n", $tableLines);

        $headers = [];
        $dataRows = [];
        $foundSeparator = false;

        foreach ($tableLines as $idx => $line) {
            $cols = explode('|', $line);
            // Remove empty first/last elements from the | split
            if (empty(trim($cols[0])))
                array_shift($cols);
            if (!empty($cols) && empty(trim($cols[count($cols) - 1])))
                array_pop($cols);

            $cols = array_map('trim', $cols);

            if ($idx === 0) {
                $headers = $cols;
            } elseif ($idx === 1 && preg_match('/^[:\-\s|]+$/', $line)) {
                $foundSeparator = true;
                continue;
            } else {
                // Ensure row has same number of columns as header
                if (count($cols) >= count($headers)) {
                    $dataRows[] = array_slice($cols, 0, count($headers));
                }
            }
        }

        // If it wasn't really a table (no separator found and only 2 lines), return original
        if (!$foundSeparator && count($tableLines) < 3)
            return implode("\n", $tableLines);
        if (empty($headers) || empty($dataRows))
            return implode("\n", $tableLines);

        $json = [
            'title' => 'Ringkasan Data',
            'headers' => $headers,
            'rows' => $dataRows,
            'currency_columns' => []
        ];

        return "\n```smart_table\n" . json_encode($json, JSON_PRETTY_PRINT) . "\n```\n";
    }

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

        $columnListPattern = '/(`[a-z][a-z0-9_]*`[,\s]*){4,}/i';

        $lines = explode("\n", $content);
        $cleanLines = [];
        $strippedCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $isThinkingLine = false;

            foreach ($thinkingLinePatterns as $pattern) {
                if (preg_match($pattern, $trimmed)) {
                    $isThinkingLine = true;
                    break;
                }
            }

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

        if ($strippedCount > 0 && empty(trim($result))) {
            Log::warning('[ThinkingLeakage] All lines stripped — returning original content as fallback.');
            return $content;
        }

        $result = ltrim($result, "\n");
        return $result;
    }

    private function streamText(string $text): void
    {
        $chunkSize = 32;

        foreach (mb_str_split($text, $chunkSize) as $chunk) {
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
            if (ob_get_level() > 0)
                ob_flush();
            flush();
        }
    }

    private function handleProviderResponse($response, string $providerCode): ?array
    {
        if ($response->failed()) {
            $body = $response->body();
            $status = $response->status();
            Log::error("[Agentic] API Error ({$providerCode}) status={$status} body=" . $body);

            if ($status === 429) {
                Log::warning("[Agentic] Rate Limit ({$providerCode}): " . $body);
                throw new \RuntimeException('__RATE_LIMIT__');
            }

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

        $isCustomProvider = !in_array($providerCode, ['gemini', 'claude', 'openai', 'mistral']);

        if ($providerCode === 'gemini') {
            $candidate = $data['candidates'][0] ?? null;
            if (!$candidate) {
                Log::warning('[Agentic] Gemini: no candidates in response. Raw: ' . substr(json_encode($data), 0, 500));
                return null;
            }

            $finishReason = strtolower($candidate['finishReason'] ?? 'stop');

            $parts = $candidate['content']['parts'] ?? [];
            $text = '';
            $toolCalls = [];

            foreach ($parts as $p) {
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

            if (empty($text) && empty($toolCalls)) {
                Log::warning('[Agentic] Gemini: empty text and no tool_calls after parsing parts. finishReason=' . $finishReason . ' parts_count=' . count($parts));
            }

            $geminiUsage = $data['usageMetadata'] ?? [];
            $geminiTokens = (int) ($geminiUsage['totalTokenCount']
                ?? (($geminiUsage['promptTokenCount'] ?? 0) + ($geminiUsage['candidatesTokenCount'] ?? 0)));

            return [
                '_tokens' => $geminiTokens,
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

            $claudeUsage = $data['usage'] ?? [];
            $claudeTokens = (int) (($claudeUsage['input_tokens'] ?? 0) + ($claudeUsage['output_tokens'] ?? 0));

            return [
                '_tokens' => $claudeTokens,
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

        if (!isset($data['_tokens'])) {
            $oaiUsage = $data['usage'] ?? [];
            $data['_tokens'] = (int) ($oaiUsage['total_tokens']
                ?? (($oaiUsage['prompt_tokens'] ?? 0) + ($oaiUsage['completion_tokens'] ?? 0)));
        }

        return $data;
    }

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

        return match (true) {
            $providerCode === 'claude' => $this->streamClaudeApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt),
            $providerCode === 'gemini' => $this->streamGeminiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount),
            default => $this->streamOpenAiCompatibleApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $providerCode, $loopCount),
        };
    }

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
            'stream' => true,
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

    private function curlStreamSse(string $url, array $headers, array $payload, string $providerCode): array
    {
        $fullText = '';
        $sseBuffer = '';
        $toolCallsRaw = [];
        $totalTokens = 0;

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
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullText, &$sseBuffer, &$toolCallsRaw, $providerCode) {
                $sseBuffer .= $data;
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

                        $token = $this->extractTokenFromSseChunk($parsed, $providerCode);
                        if ($token !== '') {
                            $fullText .= $token;
                            echo "data: " . json_encode(['chunk' => $token]) . "\n\n";
                            if (ob_get_level() > 0)
                                ob_flush();
                            flush();
                        }

                        if (isset($parsed['usage']['total_tokens'])) {
                            $totalTokens = (int) $parsed['usage']['total_tokens'];
                        } elseif (isset($parsed['usage']['prompt_tokens'])) {
                            $totalTokens = (int) (($parsed['usage']['prompt_tokens'] ?? 0) + ($parsed['usage']['completion_tokens'] ?? 0));
                        }
                        if (isset($parsed['usageMetadata']['totalTokenCount'])) {
                            $totalTokens = (int) $parsed['usageMetadata']['totalTokenCount'];
                        } elseif (isset($parsed['usageMetadata']['promptTokenCount'])) {
                            $totalTokens = (int) (($parsed['usageMetadata']['promptTokenCount'] ?? 0) + ($parsed['usageMetadata']['candidatesTokenCount'] ?? 0));
                        }
                        if (($parsed['type'] ?? '') === 'message_start') {
                            $totalTokens += (int) ($parsed['message']['usage']['input_tokens'] ?? 0);
                        }
                        if (($parsed['type'] ?? '') === 'message_delta') {
                            $totalTokens += (int) ($parsed['usage']['output_tokens'] ?? 0);
                        }

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

                        if ($providerCode === 'gemini') {
                            $parts = $parsed['candidates'][0]['content']['parts'] ?? [];
                            foreach ($parts as $p) {
                                if (!empty($p['functionCall'])) {
                                    $fc = $p['functionCall'];
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

        if ($totalTokens === 0 && strlen($fullText) > 0) {
            $totalTokens = (int) ceil(strlen($fullText) / 4);
        }

        Log::info("[StreamSSE] Done ({$providerCode}) http={$httpCode} text_len=" . strlen($fullText) . " tool_calls=" . count($toolCalls) . " tokens={$totalTokens}");

        return [
            '_tokens' => $totalTokens,
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

    private function extractTokenFromSseChunk(array $parsed, string $providerCode): string
    {
        if ($providerCode === 'claude') {
            if (($parsed['type'] ?? '') === 'content_block_delta') {
                return $parsed['delta']['text'] ?? '';
            }
            return '';
        }

        if ($providerCode === 'gemini') {
            $parts = $parsed['candidates'][0]['content']['parts'] ?? [];
            $text = '';
            foreach ($parts as $p) {
                if (!empty($p['thought']))
                    continue;
                $text .= $p['text'] ?? '';
            }
            return $text;
        }

        return $parsed['choices'][0]['delta']['content'] ?? '';
    }

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
            // Hanya paksa tool call di loop pertama (ANY), loop selanjutnya biarkan AI memilih (AUTO)
            $toolMode = ($loopCount === 1) ? 'ANY' : 'AUTO';
            $payload['toolConfig'] = [
                'functionCallingConfig' => ['mode' => $toolMode],
            ];
            Log::info("[Agentic] Gemini toolConfig mode={$toolMode} loop={$loopCount}");
        }
        $payloadJson = json_encode($payload);
        Log::info("[Agentic] Sending request to Gemini. Model={$currentModelName} PayloadSize=" . strlen($payloadJson) . " bytes");

        $response = Http::timeout(600)->retry(3, 2000, null, false)->withBody($payloadJson, 'application/json')->post($url);

        if ($response->status() === 503 && $currentModelName !== 'gemini-1.5-flash') {
            Log::warning("[Agentic] Model {$currentModelName} busy (503). Falling back to gemini-1.5-flash.");
            $fallbackUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey->api_key;
            $response = Http::timeout(600)->retry(2, 2000, null, false)->withBody($payloadJson, 'application/json')->post($fallbackUrl);
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

        $normalizedMessages = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? '';

            if ($role === 'assistant') {
                $m['content'] = is_string($m['content'] ?? null) ? $m['content'] : '';
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
                if (!is_string($m['content'] ?? null)) {
                    $m['content'] = json_encode($m['content'] ?? '');
                }
            }

            $normalizedMessages[] = $m;
        }
        $messages = $normalizedMessages;

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

        if ($isGroq && $loopCount >= 3) {
            $totalMessages = count($messages);
            $guardZone = 4;
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

        if ($isGroq) {
            $payload['parallel_tool_calls'] = false;
        }

        $httpRequest = Http::timeout(600)->retry(3, 2000, null, false);

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