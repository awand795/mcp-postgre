<?php

namespace App\Http\Controllers;

use App\Exports\ChatTableExport;
use App\Helpers\LanguageDetector;
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

    private LanguageDetector $langDetector;
    private \App\Services\ToolCallExecutor $toolExecutor;
    private \App\Services\Core\QueryService $queryService;

    public function __construct(\App\Services\ToolCallExecutor $toolExecutor, \App\Services\Core\QueryService $queryService)
    {
        $this->langDetector = new LanguageDetector();
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
            'message'  => 'required|string',
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

        $detectedLang = $this->langDetector->detect($message);

        if (!$apiKey) {
            $errorMsg = $detectedLang === 'en'
                ? 'Apologies, AI analysis access is not yet configured. Please contact Administrator.'
                : 'Mohon maaf, akses layanan analisis AI belum dikonfigurasi. Harap hubungi Administrator Sistem.';
            return response()->json(['error' => $errorMsg], 403);
        }

        $allowedDatabases = [];
        if ($user->is_admin) {
            $conns = \App\Models\DatabaseConnection::active()->get();
            foreach ($conns as $c) {
                $allowedDatabases[$c->database] = ['*' => ['*']];
            }
        } elseif ($user->roleModel) {
            if (method_exists($user->roleModel, 'getAllowedDatabases')) {
                $allowedDatabases = $user->roleModel->getAllowedDatabases();
            } else {
                foreach ($user->roleModel->permissions ?? [] as $perm) {
                    $db     = $perm->database_code;
                    $schema = $perm->schema_name;
                    $tbl    = $perm->table_name;

                    if ($db === '*') {
                        $conns = \App\Models\DatabaseConnection::active()->get();
                        foreach ($conns as $c) {
                            $allowedDatabases[$c->database] = ['*' => ['*']];
                        }
                        continue;
                    }
                    if (!$db) continue;

                    if (!isset($allowedDatabases[$db])) $allowedDatabases[$db] = [];
                    $schemaKey = ($schema && $schema !== '*') ? $schema : '*';

                    if (!isset($allowedDatabases[$db][$schemaKey])) $allowedDatabases[$db][$schemaKey] = [];

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
                'title'   => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
            ]);
            $chatSessionId = $session->id;
            $history = [];
        }

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role'            => 'user',
            'content'         => $message,
            'tool_results'    => null,
        ]);

        if (!empty($history) && $session->title === 'New Chat') {
            $session->update(['title' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')]);
        }

        $systemPrompt = $detectedLang === 'en'
            ? $this->buildSystemPrompt($allowedDatabases)
            : $this->buildSystemPromptId($allowedDatabases);

        $messages  = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);
        $maxTokens = $user->max_tokens ?? 32768;

        session_write_close();

        return response()->stream(
            function () use ($messages, $apiKey, $selectedModel, $detectedLang, $allowedDatabases, $chatSessionId, $maxTokens) {
                try {
                    $this->runAgenticLoop($messages, $apiKey, $detectedLang, $selectedModel, $allowedDatabases, $chatSessionId, $maxTokens);
                } catch (\Throwable $e) {
                    Log::error("[Agentic] Fatal Stream Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                    $this->streamText("⚠️ Maaf, terjadi masalah internal saat mengeksekusi AI: " . $e->getMessage());
                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0) ob_flush(); flush();
                }
            },
            200,
            [
                'Content-Type'      => 'text/event-stream',
                'Cache-Control'     => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Connection'        => 'keep-alive',
            ]
        );
    }

    private function runAgenticLoop(array $messages, $apiKey, string $lang, $model, array $allowedDatabases = [], $chatSessionId = null, $maxTokens = null): void
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
        if (ob_get_level() > 0) ob_flush(); flush();

        $this->toolExecutor->setAllowedTables($allowedDatabases);
        $tools       = ToolCallExecutor::getToolDefinitions();
        $loopCount   = 0;
        $allTurnToolResults = [];

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            $providerCode = strtolower($apiKey->provider->code ?? '');
            $isGroq = $providerCode === 'groq' || str_contains($apiKey->provider->base_url ?? '', 'groq.com');
            Log::info("[Agentic] Loop #{$loopCount} - Model: " . $model->model_name);

            try {
                $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === '__RATE_LIMIT__') {
                    $rateLimitMsg = $lang === 'en'
                        ? "We apologize, the AI analysis service has reached its usage limit for this period. Please contact your System Administrator to renew the service quota, or try again later."
                        : "Mohon maaf Bapak/Ibu, layanan analisis AI telah mencapai batas kuota penggunaan untuk periode ini. Silakan hubungi Administrator Sistem untuk memperbarui kuota layanan, atau coba kembali beberapa saat lagi.";
                    $this->streamText($rateLimitMsg);
                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0) ob_flush(); flush();
                    return;
                }
                Log::error("[Agentic] Critical Exception in callAiApi: " . $e->getMessage());
                $response = null;
            } catch (\Throwable $e) {
                Log::error("[Agentic] Critical Exception in callAiApi: " . $e->getMessage());
                $response = null;
            }

            if (!$response || !isset($response['choices'][0]['message'])) {
                $errMsg = $lang === 'en'
                    ? "Analytical infrastructure experiencing high traffic. Contact Administrator."
                    : "Infrastruktur analisis sedang mengalami kepadatan tinggi. Harap hubungi Administrator.";
                $this->streamText($errMsg);
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) ob_flush(); flush();
                return;
            }

            $assistantMsg = $response['choices'][0]['message'];
            $finishReason = $response['choices'][0]['finish_reason'] ?? 'stop';
            $toolCalls    = $assistantMsg['tool_calls'] ?? [];
            $textContent  = $assistantMsg['content'] ?? '';

            // Tandai sebagai live response Gemini agar formatMessagesForProvider
            // tahu bahwa functionCall boleh dikirim (bukan rekonstruksi history DB).
            $providerCodeLive = strtolower($apiKey->provider->code ?? '');
            if ($providerCodeLive === 'gemini' && !empty($toolCalls)) {
                $assistantMsg['_is_live_gemini_response'] = true;
            }

            $messages[] = $assistantMsg;

            // ── PROTEKSI 1: Intersepsi Raw SQL di Teks ────────────────────────
            // Jika AI mengirim SQL mentah di teks tanpa tool call, jangan stream ke user.
            if (empty($toolCalls) && preg_match('/SELECT\s+.*\s+FROM\s+/i', $textContent)) {
                Log::warning("[Agentic] Detected raw SQL in text content. Intercepting and retrying...");
                $messages[] = [
                    'role'    => 'user',
                    'content' => "[SYSTEM REMINDER]: Anda baru saja mengirimkan query SQL mentah ke dalam teks jawaban. Ini DILARANG. Jangan pernah tunjukkan query SQL kepada Bapak/Ibu user. Gunakan tool 'execute_query' jika Anda ingin mengambil data, lalu sajikan hasilnya dalam Bahasa Indonesia bisnis yang sopan menggunakan 'smart_table'. Silakan perbaiki respon Anda sekarang."
                ];
                continue;
            }

            // ── FIX #3: Intersepsi "Di Luar Domain" False-Positive ───────────
            // Llama/OpenRouter kadang menyimpulkan "pertanyaan di luar domain" padahal
            // sebenarnya hanya gagal menemukan tabel karena schema salah. Jika AI belum
            // berhasil memanggil execute_query sama sekali, intercept dan paksa retry
            // dengan schema correction hint yang eksplisit.
            if (empty($toolCalls) && (empty($allTurnToolResults) || $loopCount <= 3) && $loopCount <= 8) {
                $outOfDomainPhrases = [
                    // Bahasa Indonesia — kalimat penolakan
                    'tidak memiliki kewenangan',
                    'di luar kapasitas',
                    'tidak dapat membantu',
                    'bukan dalam kapasitas',
                    'saya hanya dapat membantu',
                    'tidak memiliki akses',
                    'tidak berwenang',
                    'diluar kemampuan',
                    'di luar kemampuan',
                    'tidak memiliki data',
                    'tidak tersedia dalam kapasitas',
                    'mohon maaf bapak/ibu, saya hanya',
                    // English
                    'not authorized',
                    'outside my scope',
                    'outside this scope',
                    'i am not authorized',
                    'i cannot help with',
                    'strictly limited to',
                    'beyond my capabilities',
                    'not within my domain',
                    'i only assist with',
                    'i am only able to assist',
                ];
                $isOutOfDomain = false;
                foreach ($outOfDomainPhrases as $phrase) {
                    if (stripos($textContent, $phrase) !== false) {
                        $isOutOfDomain = true;
                        break;
                    }
                }

                if ($isOutOfDomain) {
                    Log::warning("[Agentic] FIX#3 — False 'out-of-domain' detected at loop #{$loopCount} before any successful tool call. Injecting schema recovery.");

                    // Buat hint schema eksak dari allowedDatabases agar model tahu persis
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
                        'role'    => 'user',
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
                // ── FIX OpenRouter: jika respons singkat padahal sudah ada data,
                // inject format reminder SEKALI dan retry (tanpa mengubah callCustomApi)
                $providerCodeFmt = strtolower($apiKey->provider->code ?? '');
                $baseUrlFmt      = $apiKey->provider->base_url ?? '';
                $isOpenRouterFmt = $providerCodeFmt === 'openrouter' || str_contains($baseUrlFmt, 'openrouter.ai');

                if ($isOpenRouterFmt && !empty($allTurnToolResults) && strlen(trim($textContent)) < 250 && $loopCount <= $this->maxToolLoops - 2) {
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
                        'role'    => 'user',
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

                $finalContent = trim($textContent);
                if (empty($finalContent)) {
                    $finalContent = "Mohon maaf, sistem tidak memberikan respon. Silakan coba pertanyaan lain.";
                }

                $processedContent = $this->processContentForCharts($finalContent, $allTurnToolResults);

                if ($chatSessionId) {
                    ChatMessage::create([
                        'chat_session_id' => $chatSessionId,
                        'role'            => 'assistant',
                        'content'         => $processedContent,
                        'tool_results'    => !empty($allTurnToolResults) ? $allTurnToolResults : null
                    ]);
                }

                $this->streamText($processedContent);
                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) ob_flush(); flush();
                return;
            }

            foreach ($toolCalls as $toolCall) {
                $toolCallId = $toolCall['id'] ?? ('call_' . uniqid());
                $toolName   = $toolCall['function']['name'] ?? '';
                $argsRaw    = $toolCall['function']['arguments'] ?? '{}';
                $arguments  = is_string($argsRaw) ? (json_decode($argsRaw, true) ?? []) : $argsRaw;

                Log::info("[Agentic] Executing Tool: {$toolName}");
                $toolResult = $this->toolExecutor->execute($toolName, $arguments, $isGroq);

                $decodedRes = json_decode($toolResult, true);

                if (is_array($decodedRes) && $toolName === 'execute_query') {
                    $currencyCols = $decodedRes['currency_columns'] ?? [];

                    // ── GUARD: Hapus kolom non-moneter yang mungkin salah dimasukkan AI ──
                    // Kolom COUNT/jumlah/persentase/kode tidak boleh di-format sebagai Rp.
                    $nonMonetaryPattern = '/^(total_?(?:cabang|dealer|unit|qty|jumlah|count|record|row|data)|jumlah_?(?:cabang|dealer|unit)|count|qty|persentase|persen|percentage|id|kode|code|no|nomor)/i';
                    if (!empty($currencyCols)) {
                        $currencyCols = array_values(array_filter($currencyCols, function ($col) use ($nonMonetaryPattern) {
                            return !preg_match($nonMonetaryPattern, $col);
                        }));
                        if ($currencyCols !== ($decodedRes['currency_columns'] ?? [])) {
                            Log::info("[Agentic] Removed non-monetary columns from currency_columns. Remaining: " . implode(', ', $currencyCols));
                            $decodedRes['currency_columns'] = $currencyCols;
                            $toolResult = json_encode($decodedRes);
                        }
                    }

                    // ── FALLBACK: Jika AI tidak mengisi currency_columns sama sekali ──
                    // Deteksi berdasarkan nama kolom alias (dari hasil query)
                    if (empty($currencyCols) && !empty($decodedRes['columns'])) {
                        $currencyKeywords = '/(sales|amount|harga|netto|dpp|gpn|cogs|hpp|saldo|realisasi|target|pencapaian|omset|revenue|pendapatan|penjualan|laba|profit|nilai|total_(?!cabang|dealer|unit|qty|count|jumlah|record))/i';
                        foreach ($decodedRes['columns'] as $col) {
                            if (preg_match($currencyKeywords, $col) && !preg_match($nonMonetaryPattern, $col)) {
                                $currencyCols[] = $col;
                            }
                        }
                        if (!empty($currencyCols)) {
                            Log::info("[Agentic] currency_columns fallback detected: " . implode(', ', $currencyCols));
                            $decodedRes['currency_columns'] = $currencyCols;
                            $toolResult = json_encode($decodedRes);
                        }
                    }
                }

                $aiContent = $toolResult;
                if (is_array($decodedRes) && isset($decodedRes['rows']) && count($decodedRes['rows']) > 50) {
                    $aiContent = json_encode([
                        'rows_returned'    => count($decodedRes['rows']),
                        'columns'          => $decodedRes['columns'] ?? [],
                        'currency_columns' => $decodedRes['currency_columns'] ?? [],
                        'rows'             => array_slice($decodedRes['rows'], 0, 50),
                        'instruction'      => "ANALYST NOTE: Results are truncated for display. If the user asked for a 'total' or 'summary', you MUST ensure your SQL uses SUM() and GROUP BY only on identity columns (like branch name) to avoid seeing individual rows. NEVER repeat technical 'truncated' strings to the user."
                    ]);
                }

                $frontendResult = [
                    'tool_name'        => $toolName,
                    'data'             => $decodedRes ?: $toolResult,
                    'currency_columns' => is_array($decodedRes) ? ($decodedRes['currency_columns'] ?? []) : [],
                    'label'            => is_array($decodedRes) ? ($decodedRes['label'] ?? '') : '',
                ];

                echo "data: " . json_encode([
                    'tool_call' => [
                        'name'      => $toolName,
                        'arguments' => $arguments,
                        'status'    => 'success',
                        'result'    => $frontendResult,
                    ]
                ]) . "\n\n";
                if (ob_get_level() > 0) ob_flush(); flush();

                $allTurnToolResults[] = $frontendResult;

                $messages[] = [
                    'role'                       => 'tool',
                    'tool_call_id'               => $toolCallId,
                    'name'                       => $toolName,
                    'content'                    => $aiContent,
                    'decoded_data'               => $decodedRes,
                    '_is_live_gemini_response'   => true, // tandai sebagai live agar Gemini kirim functionResponse
                ];
            }
            if (ob_get_level() > 0) ob_flush(); flush();
        }
    }

    public function getSessions(Request $request)
    {
        return ChatSession::where('user_id', $request->user()->id)->orderBy('updated_at', 'desc')->get(['id', 'title', 'updated_at']);
    }

    public function getSession($id)
    {
        $session = ChatSession::where('user_id', Auth::user()->id)->findOrFail($id);

        $limit  = (int) request('limit', 50);
        $before = request('before');

        $query = ChatMessage::where('chat_session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit + 1);

        if ($before) {
            $query->where('created_at', '<', $before);
        }

        $messages     = $query->get();
        $hasMore      = $messages->count() > $limit;
        $messages     = $messages->take($limit)->sortBy('created_at')->values();
        $oldestCursor = $hasMore ? ($messages->first()?->created_at?->toISOString() ?? null) : null;

        return response()->json([
            'session'    => $session,
            'history'    => $messages,
            'pagination' => [
                'has_more'       => $hasMore,
                'oldest_cursor'  => $oldestCursor,
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
            'headers'         => 'required|array',
            'rows'            => 'required|array',
            'title'           => 'nullable|string|max:100',
            'currencyColumns' => 'nullable|array',
        ]);

        $headers         = $request->input('headers', []);
        $rows            = $request->input('rows', []);
        $title           = $request->input('title', 'Data Export');
        $currencyColumns = $request->input('currencyColumns', []);
        $filename        = $request->input('filename', 'export-' . now()->format('Ymd-His') . '.xlsx');

        $normalizedRows = array_map(function ($row) {
            return is_array($row) ? array_values($row) : (array) $row;
        }, $rows);

        $export = new \App\Exports\ChatTableExport($headers, $normalizedRows, $title, null, $currencyColumns);

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }

    public function exportPdf(Request $request)
    {
        $request->validate([
            'headers'         => 'required|array',
            'rows'            => 'required|array',
            'title'           => 'nullable|string|max:100',
            'currencyColumns' => 'nullable|array',
        ]);

        $headers         = $request->input('headers', []);
        $rows            = $request->input('rows', []);
        $title           = $request->input('title', 'Data Export');
        $currencyColumns = $request->input('currencyColumns', []);
        $filename        = $request->input('filename', 'export-' . now()->format('Ymd-His') . '.pdf');

        $normalizedRows = array_map(function ($row) {
            return is_array($row) ? array_values($row) : (array) $row;
        }, $rows);

        $normalizeForMatch = function(string $s): string {
            return trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9_]/', '', strtolower(preg_replace('/[\s]+/', '_', $s)))), '_');
        };

        $normalizedCurrencyCols = array_map($normalizeForMatch, $currencyColumns);
        $isCurrencyHeader = function(string $header) use ($normalizedCurrencyCols, $normalizeForMatch, $currencyColumns): bool {
            if (!empty($currencyColumns)) {
                $normalizedHeader = $normalizeForMatch($header);
                foreach ($normalizedCurrencyCols as $col) {
                    if (!empty($col) && ($col === $normalizedHeader || str_contains($normalizedHeader, $col) || str_contains($col, $normalizedHeader))) {
                        return true;
                    }
                }
                return false;
            }
            return (bool) preg_match('/(sales|amount|harga|netto|dpp|gpn|cogs|hpp|saldo|realisasi|target|pencapaian|omset|revenue|pendapatan|penjualan|laba|profit|nilai|total)/i', $header);
        };

        $columnTypes = array_map(function($header) use ($isCurrencyHeader) {
            if ($isCurrencyHeader($header)) return 'currency';
            if (preg_match('/(^qty$|^jumlah$|^count$|^no$|^no\.$)/i', $header)) return 'number';
            return 'text';
        }, $headers);

        $chartImage = $request->input('chartImage', null);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.pdf-table', [
            'title'           => $title,
            'headers'         => $headers,
            'rows'            => $normalizedRows,
            'currencyColumns' => $currencyColumns,
            'generatedAt'     => now()->format('d M Y H:i'),
            'colCount'        => count($headers),
            'fontSize'        => count($headers) > 10 ? 7 : (count($headers) > 7 ? 8 : 9),
            'chartImage'      => $chartImage,
            'columnTypes'     => $columnTypes,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    private function callAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens = 32768, string $systemPrompt = '', int $loopCount = 1): ?array
    {
        $providerCode = $apiKey->provider->code;
        $maxTokens    = $maxTokens ?? 32768;

        $formattedTools    = $this->formatToolsForProvider($providerCode, $tools);
        $formattedMessages = $this->formatMessagesForProvider($providerCode, $messages);

        if ($providerCode === 'gemini')  return $this->callGeminiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        if ($providerCode === 'claude')  return $this->callClaudeApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        if ($providerCode === 'mistral') return $this->callMistralApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        if ($providerCode === 'openai')  return $this->callOpenAiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);

        return $this->callCustomApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt, $loopCount);
    }

    private function formatToolsForProvider(string $providerCode, array $tools): array
    {
        if (empty($tools)) return [];

        $normalized = [];
        foreach ($tools as $t) {
            if (isset($t['function'])) {
                $normalized[] = [
                    'name'        => $t['function']['name'],
                    'description' => $t['function']['description'] ?? '',
                    'parameters'  => $t['function']['parameters'] ?? (object)[],
                ];
            } else {
                $normalized[] = [
                    'name'        => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters'  => $t['parameters'] ?? (object)[],
                ];
            }
        }

        if ($providerCode === 'gemini') {
            $geminiTools = [];
            foreach ($normalized as $f) {
                $geminiTools[] = [
                    'name'        => $f['name'],
                    'description' => $f['description'],
                    'parameters'  => $f['parameters'],
                ];
            }
            return [['function_declarations' => $geminiTools]];
        }

        if ($providerCode === 'claude') {
            $claudeTools = [];
            foreach ($normalized as $f) {
                $claudeTools[] = [
                    'name'         => $f['name'],
                    'description'  => $f['description'],
                    'input_schema' => $f['parameters'],
                ];
            }
            return $claudeTools;
        }

        $standardTools = [];
        foreach ($normalized as $f) {
            $standardTools[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $f['name'],
                    'description' => $f['description'],
                    'parameters'  => $f['parameters'],
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
            $prevRole       = null;

            foreach ($messages as $m) {
                if ($m['role'] === 'system') continue;

                $role = $m['role'];

                // ── TOOL RESULT ──────────────────────────────────────────────
                if ($role === 'tool') {
                    $isHistoryTool = empty($m['_is_live_gemini_response'] ?? null);

                    if ($isHistoryTool) {
                        // History tool result: kirim sebagai text ringkasan biasa
                        // supaya Gemini tidak bingung dengan functionResponse tanpa matching functionCall
                        $toolName    = $m['name'] ?? 'tool';
                        $rawContent  = $m['content'] ?? '';

                        // Pastikan rawContent adalah string sebelum di-decode
                        if (is_array($rawContent)) {
                            $rawContent = json_encode($rawContent);
                        }
                        $decoded = is_string($rawContent) ? (json_decode($rawContent, true) ?? []) : [];

                        // Buat ringkasan singkat — pastikan semua nilai adalah string
                        if (is_array($decoded) && !empty($decoded)) {
                            $rowCount = (int) ($decoded['rows_returned'] ?? count($decoded['rows'] ?? []));
                            // Pastikan columns adalah array of strings, bukan nested arrays
                            $rawCols  = $decoded['columns'] ?? [];
                            $cols     = implode(', ', array_map(
                                fn($c) => is_string($c) ? $c : (is_array($c) ? json_encode($c) : (string)$c),
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
                            $prevRole         = 'user';
                        }
                    } else {
                        // Live tool result: kirim sebagai functionResponse (normal flow)
                        $rawContent = $m['content'] ?? '';
                        if (!empty($m['decoded_data']) && is_array($m['decoded_data'])) {
                            $parsedContent = $m['decoded_data'];
                        } elseif (is_string($rawContent)) {
                            $decoded       = json_decode($rawContent, true);
                            $parsedContent = is_array($decoded) ? $decoded : ['result' => $rawContent];
                        } else {
                            $parsedContent = is_array($rawContent) ? $rawContent : ['result' => (string)$rawContent];
                        }

                        $parts = [[
                            'functionResponse' => [
                                'name'     => $m['name'] ?? 'tool',
                                'response' => $parsedContent,
                            ]
                        ]];
                        if ($prevRole === 'user' && !empty($geminiMessages)) {
                            $last = &$geminiMessages[count($geminiMessages) - 1];
                            $last['parts'] = array_merge($last['parts'], $parts);
                        } else {
                            $geminiMessages[] = ['role' => 'user', 'parts' => $parts];
                            $prevRole         = 'user';
                        }
                    }
                    continue;
                }

                // ── ASSISTANT MESSAGE ─────────────────────────────────────────
                if ($role === 'assistant') {
                    $isLive = !empty($m['_is_live_gemini_response']);
                    $parts  = [];

                    if (!empty($m['content'])) {
                        $parts[] = ['text' => (string) $m['content']];
                    }

                    if ($isLive && !empty($m['tool_calls'])) {
                        // Live response: kirim functionCall
                        foreach ($m['tool_calls'] as $tc) {
                            $args    = $tc['function']['arguments'] ?? '{}';
                            $argsArr = is_string($args) ? json_decode($args, false) : $args;
                            if (!$argsArr || $argsArr === []) $argsArr = new \stdClass();
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

                    if (empty($parts)) continue;

                    if ($prevRole === 'model' && !empty($geminiMessages)) {
                        $last = &$geminiMessages[count($geminiMessages) - 1];
                        $last['parts'] = array_merge($last['parts'], $parts);
                    } else {
                        $geminiMessages[] = ['role' => 'model', 'parts' => $parts];
                        $prevRole         = 'model';
                    }
                    continue;
                }

                // ── USER MESSAGE ──────────────────────────────────────────────
                if ($role === 'user') {
                    $parts = [];
                    if (!empty($m['content'])) {
                        $parts[] = ['text' => (string) $m['content']];
                    }
                    if (empty($parts)) continue;

                    if ($prevRole === 'user' && !empty($geminiMessages)) {
                        $last = &$geminiMessages[count($geminiMessages) - 1];
                        $last['parts'] = array_merge($last['parts'], $parts);
                    } else {
                        $geminiMessages[] = ['role' => 'user', 'parts' => $parts];
                        $prevRole         = 'user';
                    }
                }
            }

            return $geminiMessages;
        }

        if ($providerCode === 'claude') {
            $claudeMessages = [];
            foreach ($messages as $m) {
                if ($m['role'] === 'system') continue;

                if ($m['role'] === 'tool') {
                    $claudeMessages[] = [
                        'role'    => 'user',
                        'content' => [[
                            'type'        => 'tool_result',
                            'tool_use_id' => $m['tool_call_id'] ?? ('hist_' . uniqid()),
                            'content'     => $m['content'] ?? ''
                        ]]
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
                            'type'  => 'tool_use',
                            'id'    => $tc['id'] ?? ('hist_' . uniqid()),
                            'name'  => $tc['function']['name'],
                            'input' => is_string($args) ? (json_decode($args, true) ?? (object)[]) : $args
                        ];
                    }
                    $claudeMessages[] = ['role' => 'assistant', 'content' => $content];
                    continue;
                }

                $claudeMessages[] = ['role' => $m['role'], 'content' => $m['content'] ?? ''];
            }
            return $claudeMessages;
        }

        return $messages;
    }

    private function buildMessages(string $systemPrompt, array $history, string $userMessage, string $lang): array
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
                        'id'       => 'hist_' . md5($res['tool_name'] . json_encode($res['data'] ?? '')),
                        'type'     => 'function',
                        'function' => [
                            'name'      => $res['tool_name'] ?? 'query',
                            'arguments' => '{}', // stdClass setelah decode, bukan array
                        ],
                    ];
                }
                $messages[] = [
                    'role'        => 'assistant',
                    'content'     => $msg['content'] ?? '',
                    'tool_calls'  => $fakeToolCalls,
                ];
                foreach ($toolResults as $index => $res) {
                    $toolData    = $res['data'] ?? '';
                    $toolContent = is_string($toolData) ? $toolData : json_encode($toolData);
                    if (strlen($toolContent) > 2000) {
                        $decoded = is_array($toolData) ? $toolData : (json_decode($toolContent, true) ?? []);
                        $truncated = [
                            'rows_returned' => $decoded['rows_returned'] ?? '?',
                            'columns'       => $decoded['columns'] ?? [],
                            'rows'          => array_slice($decoded['rows'] ?? [], 0, 5),
                            '_truncated'    => true,
                            '_message'      => 'History truncated. Re-query if needed.',
                        ];
                        $toolContent = json_encode($truncated);
                    }
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $fakeToolCalls[$index]['id'] ?? ('hist_' . uniqid()),
                        'name'         => $res['tool_name'] ?? 'query',
                        'content'      => $toolContent,
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
    // SYSTEM PROMPT — BAHASA INDONESIA
    // ─────────────────────────────────────────────────────────────────────────
    private function buildSystemPromptId(array $allowedDatabases = []): string
    {
        $dbSummaries = [];
        foreach ($allowedDatabases as $dbCode => $schemas) {
            $schemaList    = implode(', ', array_keys($schemas));
            $dbSummaries[] = "- Kode Database: {$dbCode} (Schema: {$schemaList})";
        }
        $dbSummaryText = implode(PHP_EOL, $dbSummaries);

        $currentTime = now()->translatedFormat('l, d F Y H:i');

        return <<<PROMPT
Anda adalah DataBot, Data Analyst AI ahli untuk MBI (Motor Bisnis Indonesia) dengan **akses langsung ke berbagai database bisnis** melalui alat (tools).

## IDENTITAS & TUGAS UTAMA

Anda adalah asisten Data Analyst yang HANYA bertugas untuk dua hal:
1. **Analisis data bisnis** — mengakses dan menginterpretasikan data dari database yang tersedia
2. **Panduan sistem ERP** — membantu navigasi dan penggunaan modul ERP perusahaan

## KONTEKS WAKTU (SANGAT PENTING):
- **Tanggal Sekarang**: {$currentTime}
- **Penting**: Hari ini adalah tahun 2026. Analisis data tahun 2025 adalah data historis.

## DATABASE TERSEDIA UNTUK ANDA:
{$dbSummaryText}

## 🔴 INSTRUKSI PERTAMA YANG WAJIB DIEKSEKUSI (SEBELUM APAPUN)

**SETIAP PERTANYAAN** dari user — tanpa kecuali — harus direspons dengan memanggil tool `get_database_schema_info` TERLEBIH DAHULU.

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

## PERSONA & GAYA BAHASA
- **Persona**: Data Analyst Ahli, profesional, objektif, dan sangat teliti.
- **Bahasa**: Bahasa Indonesia Bisnis yang Profesional.
- **Nada**: Sopan, eksekutif, dan informatif. Selalu sapa pengguna dengan "Bapak/Ibu".

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

**WAJIB LAKUKAN 2 LANGKAH INI saat user menyebut nama cabang/dealer/entitas:**

**Langkah 1 — Resolve nama eksak dulu:**
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

**DILARANG** langsung pakai keyword user sebagai filter tanpa Langkah 1.
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

**Kapan WAJIB pakai smart_table:**
- Hasil query memiliki **≥ 2 kolom** DAN **≥ 2 baris** → WAJIB smart_table
- Hasil query memiliki **≥ 2 kolom** DAN **1 baris** berisi beberapa metrik (mis. HPP, Netto, Profit bersamaan) → WAJIB smart_table
- Hasil query memiliki **≥ 2 baris** meskipun hanya 1 kolom → WAJIB smart_table

**Kapan TIDAK perlu smart_table (cukup jawab inline):**
- Hasil query hanya **1 baris 1 kolom** (contoh: `COUNT(*) = 91`, `SUM(total) = 5.000.000`) → JANGAN buat smart_table, cukup sebutkan angkanya langsung di narasi.
  - Contoh BENAR: "**Perusahaan memiliki total 91 cabang yang aktif.**"
  - Contoh SALAH: membuat tabel 1 baris 1 kolom hanya untuk angka tunggal.

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

**URUTAN WAJIB jika user minta grafik:**
1. Ringkasan Eksekutif
2. chart (grafik visualisasi)
3. smart_table (tabel data)
4. Insight Strategis

## STRUKTUR RESPONS WAJIB (tanpa grafik)

1. **Ringkasan Eksekutif**: 1-2 kalimat cetak tebal, sebutkan angka kunci.
2. **Smart Table**: WAJIB jika hasil query memiliki ≥ 2 kolom (blok `smart_table`).
3. **Insight Strategis**: 2-3 insight singkat yang menjelaskan "MENGAPA".
4. **Rekomendasi Prompt**: 3-4 prompt lanjutan yang relevan.

## KEBIJAKAN PRIVASI & TEKNIS
- DILARANG: Tampilkan query SQL, nama koneksi database, atau detail error teknis.
- ERROR: Balas dengan bahasa bisnis sopan, jangan sebut "SQL", "Database", "Query", "Tool".

## TOOLS TERSEDIA
1. `get_database_schema_info` — Dapatkan struktur database. **GUNAKAN INI PERTAMA.**
2. `search_schema` — Cari tabel/kolom berdasarkan kata kunci (maks 1x per pertanyaan).
3. `describe_table` — **WAJIB DIPANGGIL** sebelum execute_query. Dapatkan nama kolom EKSAK.
4. `get_column_values` — Ambil nilai unik dari kolom. Skip jika timeout/error VIEW.
5. `get_view_definition` — Dapatkan DDL/logika di balik sebuah View.
6. `get_table_preview` — Ambil 5 baris contoh data untuk memahami format.
7. `execute_query` — Eksekusi SQL SELECT. Wajib prefix schema!
8. `get_erp_guidance` / `get_erp_menu_navigation` / `fetch_erp_guidance_from_web` — Panduan ERP.

## ERP MENU NAVIGATION
Saat `get_erp_menu_navigation` mengembalikan `display_text`, tampilkan **verbatim**. JANGAN tambahkan "Ringkasan Eksekutif".

## PROTOKOL URUTAN LANGKAH (WAJIB, tidak boleh dilewati)

1. `get_database_schema_info` → identifikasi tabel relevan
2. `describe_table` → dapatkan nama kolom EKSAK (WAJIB, max 3x)
3. `get_column_values` jika perlu → skip jika error/timeout VIEW
4. Bangun query **hanya dari kolom hasil describe_table**
5. `execute_query` → eksekusi
6. Sajikan: Ringkasan Eksekutif + **smart_table** (WAJIB jika ≥2 kolom) + Insight

## ATURAN SQL
- Prefix wajib: `schema_name.table_name`
- SELECT only — no INSERT/UPDATE/DELETE/DROP
- Filter tanggal: BETWEEN pada kolom DATE/TIMESTAMP dari describe_table
- Pencarian teks: `kolom ILIKE '%kata1%' AND kolom ILIKE '%kata2%'`
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

## REKOMENDASI PROMPT
Akhiri SETIAP analisis dengan:
```
💡 **Rekomendasi Prompt Selanjutnya:**
1. "[prompt spesifik dengan nama entitas aktual]"
2. "[prompt insight lebih dalam]"
3. "[prompt tren atau risiko]"
4. "[prompt cross-analysis]"
```

Jawab SEPENUHNYA dalam BAHASA INDONESIA yang FORMAL dan PROFESIONAL.

## 🚫 PERTANYAAN DI LUAR DOMAIN (TERAKHIR — HANYA JIKA SUDAH MENCOBA TOOL)

HANYA jika pertanyaan user SUDAH TERBUKTI tidak berkaitan dengan data bisnis atau ERP (misal: resep masakan, gosip artis, ramalan cuaca) DAN tool tidak menghasilkan data yang relevan, barulah balas dengan:

*"Mohon maaf Bapak/Ibu, saya hanya dapat membantu dalam kapasitas sebagai Analis Data Bisnis dan Konsultan Sistem ERP perusahaan. Untuk pertanyaan tersebut, saya tidak memiliki kewenangan untuk memberikan jawaban. Apakah ada kebutuhan analisis data atau panduan ERP yang dapat saya bantu?"*

**PENTING: Kalimat penolakan ini DILARANG digunakan jika:**
- User bertanya tentang data bisnis (cabang, dealer, penjualan, keuangan, stok, dll)
- Terjadi error database (cari tabel yang benar, jangan tolak)
- Pertanyaan ambigu (coba tool dulu, baru putuskan)
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SYSTEM PROMPT — ENGLISH
    // ─────────────────────────────────────────────────────────────────────────
    private function buildSystemPrompt(array $allowedDatabases = []): string
    {
        $dbSummaries = [];
        foreach ($allowedDatabases as $dbCode => $schemas) {
            $schemaList    = implode(', ', array_keys($schemas));
            $dbSummaries[] = "- Database Code: {$dbCode} (Schemas: {$schemaList})";
        }
        $dbSummaryText = implode(PHP_EOL, $dbSummaries);

        $currentTime = now()->format('l, F d, Y H:i');

        return <<<PROMPT
You are DataBot, an expert AI Data Analyst for MBI (Motor Bisnis Indonesia) with **direct access to multiple business databases** via tools.

## YOUR ROLE & PRIMARY MISSION

You are a Business Data Analyst assistant with two designated functions:
1. **Business data analysis** — accessing and interpreting data from available databases
2. **ERP system guidance** — assisting with navigation and usage of company ERP modules

## 🔴 FIRST MANDATORY ACTION — EXECUTE BEFORE ANYTHING ELSE

**FOR EVERY USER MESSAGE** — no exceptions — your very first action MUST be to call `get_database_schema_info`.

**ALWAYS VALID BUSINESS QUESTIONS (MUST BE ANSWERED WITH TOOLS):**
- "total branches", "how many branches", "branch count"
- "total dealers", "dealer list", "active dealers"
- "sales data", "revenue", "netto", "net sales"
- "HPP", "COGS", "profit", "margin"
- "stock", "inventory"
- "report", "summary", "recap"
- "receivable", "payable", "finance", "balance sheet"
- Any short question mentioning numbers, quantities, or business entity names

**GOLDEN RULE: NEVER REJECT A QUESTION WITHOUT TRYING A TOOL FIRST.**

If unsure whether a question relates to business data → CALL TOOL FIRST, then decide.
If you get a database error → DO NOT REJECT, find the correct table with `search_schema`.
If schema is wrong → USE `get_database_schema_info` to find the correct schema.

## TIME CONTEXT (CRITICAL):
- **Current Date**: {$currentTime}
- **Important**: Be aware that today is in the year 2026. Analyzing data from 2025 is historical data, not future data.

## AVAILABLE DATABASES FOR THIS USER:
{$dbSummaryText}

## PERSONA & STYLE
- **Persona**: Expert Data Analyst, professional, objective, and highly meticulous.
- **Language**: Professional Business English.
- **Tone**: Polite, executive, and informative. Always address the user as "Mr./Ms.".

## ⛔ GOLDEN RULE: AGGREGATION & GROUP BY (CRITICAL)
You are a Business Analyst. Mr./Ms. seeks summaries, not raw transaction lines.
1. **AUTOMATIC ASSUMPTION**: If Mr./Ms. mentions business terms (HPP/COGS, Net Sales, Profit, Discount, Qty) without the words "detail" or "per transaction", you **MUST** use the `SUM()` function and **ONLY** group by dimensions (Branch Name, Dealer Name, Month, Year).
2. **STRICT PROHIBITION ON RAW COLUMNS**: If a `GROUP BY` is present, it is strictly forbidden to include monetary/value columns (e.g., price, cost, netto, discount) in either the `SELECT` or `GROUP BY` clause.
3. **GROUP BY PURITY**: The `GROUP BY` clause **ONLY** should contain identity columns. NEVER put monetary figures in it.
4. **BUSINESS TO SQL MAPPING**:
   - "hpp" -> `SUM(actual_hpp_col)`
   - "netto" -> `SUM(actual_netto_col)`
   - "profit" -> `(SUM(net_col) - SUM(cost_col))`
5. **CONSEQUENCE**: Violating this will result in fragmented reports which are UNACCEPTABLE to Mr./Ms.
- **Response Structure (MANDATORY)**:
    1. **Executive Summary**: 1-2 bold sentences summarizing the core finding directly.
    2. **Visualization/Data (Optional)**: Use Smart Table or Chart. **ALWAYS** use Smart Table if the result has multiple columns (metrics), even if it has only one row, for a premium professional look.
    3. **Strategic Insight & Recommendations**: 2-3 brief insights explaining "WHY" and potential actions.

## PRIVACY & TECHNICAL POLICY (STRICT)
- **STRICTLY FORBIDDEN**: Showing SQL queries, internal database connection names, or technical error details in the final response.
- **ERROR MASKING**: If technical errors occur, reply with polite business language only.
- Never mention terms like "Database", "Query", "Tool", or "SQL" to the user.

## TOOLS AVAILABLE
1. `get_database_schema_info` — Get all tables and columns. Call this FIRST.
2. `search_schema` — Search tables/columns by keyword.
3. `describe_table` — Get exact column names, types, indexes for a table. ALWAYS call this before execute_query.
4. `get_column_values` — Get DISTINCT values from a column (skip if it fails/times out).
5. `get_view_definition` — Get DDL behind a View.
6. `get_table_preview` — Get 5 sample rows to understand data format.
7. `execute_query` — Run SQL SELECT. Always prefix table with schema!
8. `get_erp_guidance` — Search ERP guides.
9. `get_erp_menu_navigation` — Get ERP menu path.
10. `fetch_erp_guidance_from_web` — Fetch ERP guide from a URL.

## 🔴 ABSOLUTE PROHIBITION — NEVER GUESS COLUMN NAMES (MOST CRITICAL RULE)

Business terms spoken by the user ("HPP", "netto", "discount", "profit", "revenue") are **BUSINESS TERMS**, not database column names.

**NEVER** write a query using guessed column names such as:
- `hpp`, `total_hpp`, `cost_of_goods` — the real column might be `val_cost`, `amount_cogs`, or something entirely different
- `netto`, `net_sales`, `total_netto` — the real column might be `val_netto`, `amount_net`, etc.
- `diskon`, `discount`, `total_disc` — the real column might be `val_disc`, `potongan`, `rebate`, etc.
- `profit`, `laba`, `margin` — usually not a stored column; must be calculated from two other columns
- `periode_bulan`, `periode_tahun`, `month`, `year` — the actual date column name must come from describe_table

**MANDATORY CHECKPOINT BEFORE execute_query:**
Ask yourself: *"Does EVERY column name I am using in this query come from the describe_table result I called in this loop?"*
- If YES → proceed with execute_query
- If NO or UNSURE → call describe_table first

## 🔴 MANDATORY — RESOLVE ENTITY NAME BEFORE QUERYING

Users often mention branch/dealer/entity names with imprecise spelling ("hm yamin", "yamin", "HM Yamin"). The actual stored name may differ ("HM. YAMIN", "YAMIN BC", etc.).

**ALWAYS PERFORM THESE 2 STEPS when user mentions a branch/dealer/entity name:**

**Step 1 — Resolve the exact name first:**
```sql
SELECT DISTINCT branch_col
FROM schema.table
WHERE branch_col ILIKE '%user_keyword%'
LIMIT 10
```
→ Get the exact name from result (e.g.: "HM. YAMIN")

**Step 2 — Use exact name (NOT ILIKE) in the main query:**
```sql
WHERE branch_col = 'HM. YAMIN'  -- use result from Step 1
```

**FORBIDDEN**: Directly using user keyword as filter without Step 1.
**FORBIDDEN**: Using `ILIKE` in the main query once you have the exact name from Step 1.

If Step 1 returns >1 name, ask user: "Which branch did you mean? [show options]"

## 🔴 MANDATORY — HPP, NETTO, AND TOTAL NETTO CALCULATION

Four different concepts that MUST be understood, MUST be differentiated, and MUST be calculated with the correct formula:

| Business Term | SQL Formula (columns from describe_table) | Description |
|---|---|---|
| **HPP (COGS)** | `SUM(hrg_pokok)` | Unit cost per transaction row (without multiplying by qty) |
| **Total HPP (Total COGS)** | `SUM(hrg_pokok * qty_jual)` | Unit cost × qty sold = true total COGS |
| **Netto (Net)** | `SUM(total_harga - total_disc)` | After discount, BEFORE tax (= DPP / Tax Base) |
| **Total Netto** | `SUM(total_netto)` | FINAL value after VAT/PPN (= Netto + Total VAT) |

**RELATIONSHIP BETWEEN TERMS (MUST MEMORIZE):**
```
Gross Price (total_harga)
  - Total Discount (total_disc)
= Netto (= DPP)                     ← SUM(total_harga - total_disc)
  + VAT/PPN
= Total Netto                       ← SUM(total_netto)

Total HPP = SUM(hrg_pokok * qty_jual)
Profit    = Total Netto - Total HPP
```

If user requests **"HPP"**: use `ROUND(SUM(hrg_pokok), 0) AS "HPP"`
If user requests **"Total HPP"**: use `ROUND(SUM(hrg_pokok * qty_jual), 0) AS "Total HPP"`
If user requests **"Netto"**: use `ROUND(SUM(total_harga - total_disc), 0) AS "Netto"`
If user requests **"Total Netto"**: use `ROUND(SUM(total_netto), 0) AS "Total Netto"`

**CRITICAL CHECKPOINT before execute_query — 3 MANDATORY QUESTIONS:**
1. Does the query request both Netto AND Total Netto? → Ensure formulas are DIFFERENT (different columns!)
2. Are column names `total_harga`, `total_disc`, `total_netto` verified from describe_table?
3. Is Profit calculated from `SUM(total_netto) - SUM(hrg_pokok * qty_jual)`?

**STRICT PROHIBITIONS:**
- ❌ NEVER use `SUM(total_netto)` for the "Netto" column — that is Total Netto (already includes VAT)!
- ❌ NEVER use the same column for both Netto and Total Netto
- ❌ NEVER guess column names — always use results from describe_table

- **⛔ GROUP BY CLARIFICATION**:
  - The `GROUP BY` clause is for dimensions, **NOT** for transaction values.
  - Even if the user asks for "HPP and Total HPP", do **NOT** show both. You **MUST** only show `SUM(hpp)` as "Total HPP". Prioritize aggregate summaries for executive clarity.

- **🚫 TECHNICAL LANGUAGE BAN**: You are strictly forbidden from repeating technical system messages like "Data truncated", "Showing 50 rows", or "Tool results" to Mr./Ms. Use professional analyst language, such as: *"Showing the top 50 sample transactions for you..."* or *"The summary below highlights the key performance indicators..."*.

## SQL RULES
- Always prefix: `schema_name.table_name`
- SELECT only — no INSERT/UPDATE/DELETE/DROP
- **⛔ NEVER GUESS COLUMN NAMES** — only use names from `describe_table` results
- **PROFIT CALCULATION**: Never SELECT a column named "profit" directly. Always identify Net Sales and HPP columns from describe_table, then compute: SUM(net_col) - SUM(hpp_col)
- **DATE FILTERS**: Always use BETWEEN on an actual DATE/TIMESTAMP column from describe_table — NEVER use guessed names like `periode_bulan` or `periode_tahun`
- **TEXT SEARCH**: Split keywords: `column ILIKE '%word1%' AND column ILIKE '%word2%'`
- **ALIASES**: Use Title Case: `AS "Total Net Sales"`
- **ROUNDING**: `ROUND(SUM(column), 0)`
- **SELF-CORRECTION**: If any tool returns `MANDATORY_AI_ACTION`, follow it precisely. NEVER give up.
- **⛔ GROUP BY RULE (CRITICAL)**: When the user asks for "totals" or a summary PER entity (per branch, per dealer, per month):
  - GROUP BY must only contain IDENTITY/DIMENSION columns (e.g. branch_name, dealer_name, month)
  - NEVER include value/transaction columns in GROUP BY (e.g. hpp, total_netto, price)
  - CORRECT: `GROUP BY branch_name`
  - WRONG: `GROUP BY branch_name, hpp, total_netto, disc` ← produces hundreds of duplicate rows
  - One total per branch: `SELECT branch_col, SUM(hpp_col) AS "Total HPP", SUM(netto_col) AS "Total Netto" ... GROUP BY branch_col`

## 🚨 TIMEOUT & EMPTY RESULT PROTOCOL — MANDATORY

If `get_column_values` returns a `warning` or `MANDATORY_AI_ACTION`:
- Immediately skip it. Call `describe_table` instead to get verified column names.

If `execute_query` returns `QUERY_TIMEOUT` or `rows: []` with `MANDATORY_AI_ACTION`:
1. **NEVER** conclude "data not available" or suggest the user try another month.
2. **MUST** call `describe_table` to get the correct DATE/TIMESTAMP column name.
3. **MUST** rebuild `execute_query` with columns verified from describe_table.
4. Retry at least **3 times** before reporting a technical issue to the user.

## CURRENCY IDENTIFICATION
- Identify all monetary columns in `currency_columns` parameter when calling `execute_query`.
- Use "Rp" prefix in natural language responses.

## SMART TABLE & CHART FORMAT

**When to use smart_table:**
- Query result has **≥ 2 columns** AND **≥ 2 rows** → MUST use smart_table
- Query result has **≥ 2 columns** AND **1 row** with multiple metrics (e.g. HPP, Netto, Profit together) → MUST use smart_table
- Query result has **≥ 2 rows** even if only 1 column → MUST use smart_table

**When NOT to use smart_table (answer inline instead):**
- Result is only **1 row, 1 column** (e.g. `COUNT(*) = 91`, `SUM(total) = 5,000,000`) → DO NOT create a smart_table. Just state the number directly in the narrative.
  - CORRECT: "**The company has a total of 91 active branches.**"
  - WRONG: Creating a 1-row, 1-column table just for a single number.

**CURRENCY_COLUMNS RULE (CRITICAL):**
- ✅ INCLUDE: columns with monetary/Rupiah values (total_netto, hpp, revenue, profit, etc.)
- ❌ DO NOT INCLUDE: COUNT columns, branch counts, dealer counts, qty, percentages, IDs, or codes
- WRONG example: `"currency_columns":["Total Branches"]` ← the number 91 will be displayed as Rp 91!
- CORRECT example: `"currency_columns":["Total Sales","Total HPP"]`

```smart_table
{}
```
```chart
{"type": "bar", "data": {"labels":["A"],"datasets":[{"label":"Data","data":[10]}]}}
```

## PROMPT RECOMMENDATIONS
End EVERY analysis with 3-4 specific next prompt suggestions relevant to the current data.

Respond ENTIRELY in ENGLISH.

## 🚫 OUT-OF-DOMAIN RESPONSES (LAST RESORT — ONLY AFTER TRYING TOOLS)

ONLY if the user's question has been PROVEN unrelated to business data or ERP (e.g. cooking recipes, celebrity gossip, weather forecast) AND tools returned no relevant data, THEN reply with:

*"I appreciate your inquiry. However, my role is strictly limited to Business Data Analysis and ERP System Guidance for this organization. I am not authorized to provide responses on topics outside this scope. Is there any data analysis or ERP-related matter I can assist you with?"*

**IMPORTANT: This rejection message is FORBIDDEN if:**
- User asks about business data (branches, dealers, sales, finance, stock, etc.)
- A database error occurred (find the correct table, do not reject)
- The question is ambiguous (try tools first, then decide)
PROMPT;
    }

    private function processContentForCharts(string $content, array $toolResults): string
    {
        return $content;
    }

    private function streamText(string $text): void
    {
        foreach (mb_str_split($text, 50) as $chunk) {
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
            if (ob_get_level() > 0) ob_flush(); flush();
            usleep(10000);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API PROVIDER IMPLEMENTATIONS
    // ─────────────────────────────────────────────────────────────────────────

    private function handleProviderResponse($response, string $providerCode): ?array
    {
        if ($response->failed()) {
            $body   = $response->body();
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
            if (!$candidate) return null;

            $parts = $candidate['content']['parts'] ?? [];
            $text  = '';
            $toolCalls = [];
            foreach ($parts as $p) {
                if (isset($p['text'])) $text .= $p['text'];
                if (isset($p['functionCall'])) {
                    $toolCalls[] = [
                        'id'   => 'call_' . uniqid(),
                        'type' => 'function',
                        'function' => [
                            'name'      => $p['functionCall']['name'],
                            'arguments' => json_encode($p['functionCall']['args'] ?? (object)[])
                        ]
                    ];
                }
            }
            return [
                'choices' => [[
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => $text,
                        'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                    ],
                    'finish_reason' => !empty($toolCalls) ? 'tool_calls' : 'stop'
                ]]
            ];
        }

        if ($providerCode === 'claude') {
            $contentBlocks = $data['content'] ?? [];
            $stopReason    = $data['stop_reason'] ?? 'end_turn';
            $text          = '';
            $toolCalls     = [];

            foreach ($contentBlocks as $block) {
                $type = $block['type'] ?? '';
                if ($type === 'text') {
                    $text .= $block['text'] ?? '';
                } elseif ($type === 'tool_use') {
                    $toolCalls[] = [
                        'id'   => $block['id'] ?? ('call_' . uniqid()),
                        'type' => 'function',
                        'function' => [
                            'name'      => $block['name'],
                            'arguments' => json_encode($block['input'] ?? (object)[])
                        ]
                    ];
                }
            }

            return [
                'choices' => [[
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => $text,
                        'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                    ],
                    'finish_reason' => ($stopReason === 'tool_use') ? 'tool_calls' : 'stop'
                ]]
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
                            $msg['tool_calls'] = [[
                                'id'       => 'call_' . uniqid(),
                                'type'     => 'function',
                                'function' => [
                                    'name'      => $json['name'],
                                    'arguments' => is_string($json['parameters']) ? $json['parameters'] : json_encode($json['parameters'])
                                ]
                            ]];
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

    private function callOpenAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = ''): ?array
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $payload = [
            'model'       => $model->model_name,
            'messages'    => $messages,
            'max_tokens'  => (int) $maxTokens,
            'temperature' => 0.3,
        ];
        if (!empty($tools)) {
            $payload['tools']       = $tools;
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
            'model'       => $model->model_name,
            'messages'    => $messages,
            'max_tokens'  => (int) $maxTokens,
            'temperature' => 0.3,
        ];
        if (!empty($tools)) {
            $payload['tools']       = $tools;
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
            'model'      => $model->model_name,
            'max_tokens' => (int) $maxTokens,
            'messages'   => $messages,
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
                'x-api-key'         => $apiKey->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
            ->post($url, $payload);
        return $this->handleProviderResponse($response, 'claude');
    }

    private function callGeminiApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = ''): ?array
    {
        $currentModelName = $model->model_name ?? 'gemini-1.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $currentModelName . ':generateContent?key=' . $apiKey->api_key;

        $payload = [
            'contents'         => $messages,
            'generationConfig' => ['maxOutputTokens' => (int) $maxTokens, 'temperature' => 0.7],
        ];
        if (!empty($systemPrompt)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }
        if (!empty($tools)) {
            $payload['tools'] = $tools;
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
        $url     = $baseUrl . '/chat/completions';

        $providerCode = strtolower($apiKey->provider->code ?? '');
        $isGroq       = $providerCode === 'groq' || str_contains($baseUrl, 'groq.com');
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
                if (!isset($tool['function']['parameters'])) return $tool;
                $params  = &$tool['function']['parameters'];
                $props   = $params['properties'] ?? null;
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
            $guardZone     = 4; // jaga 4 pesan terakhir tetap utuh
            $prunedCount   = 0;
            for ($i = 0; $i < $totalMessages - $guardZone; $i++) {
                if (($messages[$i]['role'] ?? '') === 'tool' && strlen($messages[$i]['content'] ?? '') > 500) {
                    $toolName = $messages[$i]['name'] ?? 'unknown';
                    $messages[$i]['content'] = json_encode([
                        'status'  => 'success',
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
            'model'       => $model->model_name,
            'messages'    => $messages,
            'max_tokens'  => (int) $maxTokens,
            'temperature' => 0.3,
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
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
                'X-Title'      => 'MBI Agentic DataBot',
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