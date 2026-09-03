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
    private $maxHistory = 8;

    // Batas jumlah nama tabel yang didaftarkan di system prompt per database.
    // Nilai ini menjaga input token tetap kecil — tabel lain tetap bisa ditemukan
    // model lewat tool get_database_schema_info / search_schema.
    private const MAX_TABLES_HINT = 60;

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
                return response()->json(['error' => __('Tidak ada model AI yang aktif. Silakan aktifkan di Pengaturan.')], 400);
            }
            $selectedModelId = $selectedModel->id;
        } else {
            $selectedModel = $user->aiModels()->with('provider')->findOrFail($selectedModelId);
        }

        $chatSessionId = $request->chat_session_id;
        if ($chatSessionId && !is_numeric($chatSessionId)) {
            $chatSessionId = \App\Helpers\HashidsHelper::decode($chatSessionId) ?? null;
        }

        $allApiKeys = ApiKeyResolver::getKeysForProvider($user, $selectedModel->provider_id);

        if ($allApiKeys->isEmpty()) {
            return response()->json(['error' => __('Mohon maaf, akses layanan analisis AI belum dikonfigurasi. Harap hubungi Administrator Sistem.')], 403);
        }

        $firstAvailableKey = ApiKeyResolver::pickAvailable($allApiKeys);
        if (!$firstAvailableKey) {
            return response()->json(['error' => __('Mohon maaf, semua kuota API untuk layanan ini telah habis. Silakan coba kembali besok atau hubungi Administrator Sistem.')], 429);
        }

        $allowedDatabases = [];
        // Only Super Admin sees all databases. Regular admin follows RBAC.
        if ($user->is_super_admin) {
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
            $cleanTitle = trim(preg_replace('/\s+/', ' ', $message));
            $sessionTitle = mb_substr($cleanTitle, 0, 250);
            if (empty($sessionTitle)) {
                $sessionTitle = 'New Chat';
            }
            $session = ChatSession::create([
                'user_id' => $user->id,
                'title' => $sessionTitle
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
            $cleanTitle = trim(preg_replace('/\s+/', ' ', $message));
            $sessionTitle = mb_substr($cleanTitle, 0, 250);
            if (!empty($sessionTitle)) {
                $session->update(['title' => $sessionTitle]);
            }
        }

        $scopeLimited = (bool) ($user->analysis_scope_limited ?? true);

        $detectedLanguage = $this->detectLanguage($message);
        $systemPrompt = $this->buildSystemPrompt($allowedDatabases, $scopeLimited, $detectedLanguage, $message);

        $messages = $this->buildMessages($systemPrompt, $history, $message);
        $maxTokens = $user->max_tokens ?? 32768;

        session_write_close();

        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_implicit_flush(true);

        return response()->stream(
            function () use ($messages, $allApiKeys, $selectedModel, $allowedDatabases, $chatSessionId, $maxTokens, $scopeLimited, $detectedLanguage) {
                try {
                    $this->runAgenticLoop($messages, $allApiKeys, $selectedModel, $allowedDatabases, $chatSessionId, $maxTokens, $scopeLimited, $detectedLanguage);
                } catch (\Throwable $e) {
                    Log::error("[Agentic] Fatal Stream Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                    $this->streamText("⚠️ " . __('Maaf, terjadi masalah internal saat mengeksekusi AI:') . " " . $e->getMessage());
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

    private function runAgenticLoop(array $messages, \Illuminate\Support\Collection $apiKeys, $model, array $allowedDatabases = [], $chatSessionId = null, $maxTokens = null, bool $scopeLimited = true, string $detectedLanguage = 'id'): void
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
        $originalUserMessage = $this->extractOriginalUserMessage($messages);
        if ($chatSessionId) {
            echo "data: " . json_encode([
                'chat_session_id' => \App\Helpers\HashidsHelper::encode($chatSessionId),
                'detected_language' => $detectedLanguage
            ]) . "\n\n";
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
            'web_search',
        ];

        $terminalToolCallsCount = []; // Track calls to terminal tools

        $executeQueryCount = 0;
        $lastExecutedSql = '';

        $probeQueryCount = 0;
        $maxProbeQueries = 2;
        $searchSchemaCallsCount = 0;
        $maxSearchSchemaCalls = 2;
        $hasExecutedTerminalTool = false;

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            $providerCode = strtolower($apiKey->provider->code ?? '');
            $isGroq = $providerCode === 'groq' || str_contains($apiKey->provider->base_url ?? '', 'groq.com');
            Log::info("[Agentic] Loop #{$loopCount} - Model: " . $model->model_name);

            $isProbeQuery = $executeQueryCount > 0
                && stripos($lastExecutedSql, 'SELECT DISTINCT') !== false
                && stripos($lastExecutedSql, 'GROUP BY') === false;

            $activeSystemPrompt = $systemPrompt;
            $activeTools = $tools;

            // ── STRATEGY 2: Slim Summarization Prompt for Call #2+ ───────────
            // Jika terminal tool (execute_query, web_search, dsb) sudah selesai dieksekusi,
            // fase berikutnya HANYA merangkum hasil dan menyajikan smart_table/insight bisnis.
            // Gunakan Summarization Prompt ramping (~650 token) dan kosongkan tools (~1.060 token)
            // untuk memangkas ~18.000 token input secara drastis!
            if ($loopCount > 1 && $hasExecutedTerminalTool) {
                Log::info("[Agentic] Loop #{$loopCount} - Switching to Slim Summarization Prompt (~650 tokens) for final response.");
                $activeSystemPrompt = $this->buildSummarizationSystemPrompt($detectedLanguage, $scopeLimited, $originalUserMessage);
                if (isset($messages[0]) && $messages[0]['role'] === 'system') {
                    $messages[0]['content'] = $activeSystemPrompt;
                }
                $activeTools = [];
            }

            // Gunakan streaming sejak loop pertama agar jawaban langsung (out-of-scope) tetap mengalir ke user
            $useStreaming = true;

            try {
                if ($useStreaming) {
                    Log::info("[Agentic] Loop #{$loopCount} using STREAMING mode (tool results available)");
                    try {
                        $response = $this->streamFinalResponseFromApi(
                            $messages,
                            $activeTools,
                            $apiKey,
                            $model,
                            $maxTokens,
                            $activeSystemPrompt,
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
                        $textContent = $this->synchronizeExecutiveSummaryWithTableData($textContent, $allTurnToolResults);
                        $textContent = $this->formatRupiahNumbersInText($textContent);

                        ApiKeyResolver::autoResetIfNeeded($apiKey);
                        $apiKey->recordUsage((int) ($response['_tokens'] ?? 0));

                        if ($chatSessionId) {
                            $textContent = $this->injectSmartTableDataIntoContent($textContent, $allTurnToolResults);
                            $textContent = $this->injectChartDataIntoContent($textContent, $allTurnToolResults);
                            ChatMessage::create([
                                    'chat_session_id' => $chatSessionId,
                                    'role' => 'assistant',
                                    'content' => $textContent,
                                    'tool_results' => !empty($allTurnToolResults) ? $allTurnToolResults : null
                                ]);
                        }
                        
                        // Kirim text yang bersih ke user untuk me-overwrite teks thinking sebelumnya
                        $this->streamText($textContent);
                        echo "data: [DONE]\n\n";
                        if (ob_get_level() > 0)
                            ob_flush();
                        flush();
                        return;
                    }

                    if (empty($textContent) && empty($toolCalls)) {
                        Log::warning("[Agentic] Streaming returned empty, falling back to non-streaming for loop #{$loopCount}");
                        $response = $this->callAiApi($messages, $activeTools, $apiKey, $model, $maxTokens, $activeSystemPrompt, $loopCount);
                    }
                } else {
                    $response = $this->callAiApi($messages, $activeTools, $apiKey, $model, $maxTokens, $activeSystemPrompt, $loopCount);
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
                    'role' => 'system',
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

                $isAccessDeniedPhrase = (
                    stripos($textContent, 'hak akses') !== false ||
                    stripos($textContent, 'tidak memiliki akses') !== false ||
                    stripos($textContent, 'tidak memiliki izin') !== false ||
                    stripos($textContent, 'izin akses') !== false ||
                    stripos($textContent, 'kewenangan') !== false ||
                    stripos($textContent, 'dibatasi') !== false ||
                    stripos($textContent, 'access denied') !== false ||
                    stripos($textContent, 'not authorized') !== false
                );

                // Cek apakah user memiliki hak akses terbatas (RBAC)
                $hasWildcard = false;
                foreach ($allowedDatabases as $dbCode => $schemas) {
                    if ($dbCode === '*') { $hasWildcard = true; break; }
                    foreach ($schemas as $s => $tbls) {
                        if ($s === '*') {
                            foreach ($tbls as $t) {
                                $n = is_array($t) ? ($t['name'] ?? '') : (string) $t;
                                if ($n === '*') { $hasWildcard = true; break 2; }
                            }
                        }
                        foreach ($tbls as $t) {
                            $n = is_array($t) ? ($t['name'] ?? '') : (string) $t;
                            if ($n === '*') { $hasWildcard = true; break 2; }
                        }
                    }
                }
                $isRestrictedUser = !$hasWildcard && !empty($allowedDatabases);

                if ($isOutOfDomain && $scopeLimited && !$isAccessDeniedPhrase && !$isRestrictedUser) {
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
                                'role' => 'system',
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
                            'role' => 'system',
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

                $processedContent = $this->stripThinkingLeakage($finalContent);
                $processedContent = $this->processContentForCharts($processedContent, $allTurnToolResults);
                $processedContent = $this->injectSmartTableDataIntoContent($processedContent, $allTurnToolResults);
                $processedContent = $this->injectChartDataIntoContent($processedContent, $allTurnToolResults);
                $processedContent = $this->synchronizeExecutiveSummaryWithTableData($processedContent, $allTurnToolResults);
                $processedContent = $this->formatRupiahNumbersInText($processedContent);

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
                $arguments = $this->enforceNumberedBranchIntent($originalUserMessage, $toolName, $arguments);

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
                    $tName = $call['name'];
                    $tArgs = $call['arguments'];
                    if ($tName === 'search_schema') {
                        $searchSchemaCallsCount++;
                        if ($searchSchemaCallsCount > $maxSearchSchemaCalls) {
                            Log::warning("[Agentic] search_schema circuit breaker tripped (>{$maxSearchSchemaCalls} calls). Returning early stop.");
                            $executedResults[] = [
                                'call' => $call,
                                'result' => json_encode([
                                    'keyword' => $tArgs['keyword'] ?? '',
                                    'matches' => [],
                                    'count' => 0,
                                    'instruction' => 'BATAS MAKSIMAL PENCARIAN SKEMA TERCAPAI. Tidak ditemukan tabel yang cocok. DILARANG memanggil search_schema lagi. Segera hentikan pemanggilan tool dan jelaskan bahwa data tidak ditemukan atau tidak termasuk dalam hak akses akun user.'
                                ])
                            ];
                            continue;
                        }
                    }
                    Log::info("[Agentic] Starting Fiber for tool: {$call['name']}");
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
                if ($call['name'] === 'search_schema') {
                    $searchSchemaCallsCount++;
                    if ($searchSchemaCallsCount > $maxSearchSchemaCalls) {
                        Log::warning("[Agentic] search_schema circuit breaker tripped (>{$maxSearchSchemaCalls} calls). Returning early stop.");
                        $executedResults[] = [
                            'call' => $call,
                            'result' => json_encode([
                                'keyword' => $call['arguments']['keyword'] ?? '',
                                'matches' => [],
                                'count' => 0,
                                'instruction' => 'BATAS MAKSIMAL PENCARIAN SKEMA TERCAPAI. Tidak ditemukan tabel yang cocok. DILARANG memanggil search_schema lagi. Segera hentikan pemanggilan tool dan jelaskan bahwa data tidak ditemukan atau tidak termasuk dalam hak akses akun user.'
                            ])
                        ];
                    } else {
                        Log::info("[Agentic] Executing Tool: {$call['name']}");
                        $executedResults[] = [
                            'call' => $call,
                            'result' => $this->mcpClient->callTool($call['name'], $call['arguments']),
                        ];
                    }
                } else {
                    Log::info("[Agentic] Executing Tool: {$call['name']}");
                    $executedResults[] = [
                        'call' => $call,
                        'result' => $this->mcpClient->callTool($call['name'], $call['arguments']),
                    ];
                }
            }

            $hasTerminalToolThisTurn = false;
            foreach ($executedResults as $execItem) {
                $call = $execItem['call'];
                $toolCallId = $call['id'];
                $toolName = $call['name'];
                $arguments = $call['arguments'];

                $countWithoutWhereWarning = $call['countWithoutWhereWarning'];
                $toolResult = $execItem['result'];

                $decodedRes = json_decode($toolResult, true);

                // ── HARD STOP: ACCESS_DENIED_FINAL ───────────────────────────
                if (is_array($decodedRes) && ($decodedRes['error'] ?? '') === 'ACCESS_DENIED_FINAL') {
                    $aksesTolakMsg = 'Mohon maaf Bapak/Ibu, permintaan Anda tidak dapat kami proses karena data yang diminta bersifat terbatas dan hanya dapat diakses oleh pihak yang berwenang sesuai kebijakan keamanan data perusahaan. Untuk mendapatkan informasi ini, silakan menghubungi Administrator atau pihak yang memiliki kewenangan akses. Terima kasih atas pengertiannya.';
                    Log::warning("[Agentic] ACCESS_DENIED_FINAL detected on tool '{$toolName}' — breaking loop immediately for User " . \Illuminate\Support\Facades\Auth::id());

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

                    $words = explode(' ', $aksesTolakMsg);
                    foreach ($words as $i => $word) {
                        $chunk = ($i === 0 ? '' : ' ') . $word;
                        echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                        if (ob_get_level() > 0)
                            ob_flush();
                        flush();
                        usleep(30000);
                    }

                    echo "data: " . json_encode(['done' => true]) . "\n\n";
                    if (ob_get_level() > 0)
                        ob_flush();
                    flush();
                    return;
                }

                if (is_array($decodedRes) && $toolName === 'execute_query') {
                    $currencyCols = $decodedRes['currency_columns'] ?? [];
                }

                $aiContent = $toolResult;
                if (is_array($decodedRes) && isset($decodedRes['rows'])) {
                    $rowCount = count($decodedRes['rows']);
                    if ($rowCount > 15) {
                        $aiContent = json_encode([
                            'rows_returned' => $rowCount,
                            'columns' => $decodedRes['columns'] ?? [],
                            'currency_columns' => $decodedRes['currency_columns'] ?? [],
                            'rows' => array_slice($decodedRes['rows'], 0, 15),
                            'instruction' => "ANALYST NOTE: Menampilkan sampel 15 baris pertama dari total " . $rowCount . " baris data yang berhasil diproses database. Sistem frontend sudah memiliki dan akan menampilkan seluruh " . $rowCount . " baris data secara utuh di smart_table. Gunakan 15 baris sampel ini sebagai referensi untuk menyusun narasi, ringkasan eksekutif, dan insight bisnis. JANGAN melakukan query tambahan untuk mengambil baris sisanya."
                        ], JSON_UNESCAPED_UNICODE);
                    } elseif ($rowCount === 0 && $toolName === 'execute_query') {
                        $zeroRowsInstruction = $scopeLimited
                            ? "ANALYST NOTE: Query ini tidak menghasilkan data (0 baris). Jika Anda sedang mencari data untuk periode waktu tertentu, kemungkinan besar data memang belum tersedia di database. Silakan sampaikan temuan ini kepada Bapak/Ibu user secara profesional. Hindari melakukan perulangan query (loop) untuk mencari tanggal alternatif secara otomatis agar tidak terjadi disorientasi konteks waktu."
                            : "ANALYST NOTE: Query database ini tidak menghasilkan data (0 baris). Jika user meminta rekomendasi eksternal, market insight, atau produk yang BELUM ada di database, hasil kosong ini BUKAN alasan untuk berhenti. Gunakan temuan database sebagai konteks pembanding, lalu berikan rekomendasi berbasis pengetahuan umum/analisis bisnis. Jelaskan secara singkat bahwa rekomendasi non-database dibuat berdasarkan pengetahuan umum dan perlu divalidasi dengan riset pasar terbaru karena tidak ada tool pencarian web publik.";
                        $aiContent = json_encode([
                            'rows_returned' => 0,
                            'columns' => $decodedRes['columns'] ?? [],
                            'rows' => [],
                            'instruction' => $zeroRowsInstruction
                        ]);
                    }
                }

                $currentIsProbe = false;
                if ($toolName === 'execute_query') {
                    $sqlToCheck = $call['arguments']['sql'] ?? '';
                    $hasDistinct = stripos($sqlToCheck, 'SELECT DISTINCT') !== false;
                    $hasGroupBy = stripos($sqlToCheck, 'GROUP BY') !== false;
                    $hasAggregate = (bool) preg_match('/\b(SUM|COUNT|AVG|MIN|MAX)\s*\(/i', $sqlToCheck);
                    
                    $selectPart = '';
                    if (preg_match('/SELECT\s+(.*?)\s+FROM/is', $sqlToCheck, $m)) {
                        $selectPart = trim($m[1]);
                    }
                    $isSingleColumn = (strpos($selectPart, ',') === false) && !empty($selectPart) && stripos($selectPart, '*') === false;

                    $currentIsProbe = ($hasDistinct && !$hasGroupBy) || ($isSingleColumn && !$hasAggregate && !$hasGroupBy);
                }

                $frontendResult = [
                    'tool_name' => $toolName,
                    'data' => $decodedRes ?: $toolResult,
                    'currency_columns' => is_array($decodedRes) ? ($decodedRes['currency_columns'] ?? []) : [],
                    'label' => is_array($decodedRes) ? ($decodedRes['label'] ?? '') : '',
                    'is_probe' => $currentIsProbe,
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
                    // $currentIsProbe is already calculated above

                    if ($currentIsProbe) {
                        $probeQueryCount++;
                        Log::info("[Agentic] Probe query #{$probeQueryCount}: " . substr($lastExecutedSql, 0, 150));
                        $isProbeLimitReached = ($currentIsProbe && $probeQueryCount >= $maxProbeQueries);
                        if ($isProbeLimitReached) {
                            Log::warning("[Agentic] PROBE LIMIT reached ({$probeQueryCount}/{$maxProbeQueries}). Injecting force-execute reminder.");
                            if (!empty($executedResults)) {
                                $probeColumn = 'data';
                                if (preg_match('/SELECT\s+DISTINCT\s+([a-zA-Z0-9._"]+)/i', $lastExecutedSql, $m)) {
                                    $probeColumn = str_replace(['"', '`'], '', $m[1]);
                                }

                                $lastIdx = count($executedResults) - 1;
                                $executedResults[$lastIdx]['result'] .= "\n\n**MANDATORY_AI_ACTION**: Batas verifikasi data untuk kolom '{$probeColumn}' telah tercapai ({$probeQueryCount}/{$maxProbeQueries}). Informasi yang Anda peroleh dari probe sebelumnya sudah cukup. Segera susun query utama menggunakan nilai-nilai eksak yang sudah ditemukan agar jawaban dapat segera disajikan kepada Bapak/Ibu user. DILARANG melakukan probe lagi.";
                            }
                        }
                    }
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name' => $toolName,
                    'content' => $aiContent,
                    '_is_live_gemini_response' => true,
                ];

                if (!empty($countWithoutWhereWarning)) {
                    $actualCount = null;
                    if (is_array($decodedRes) && !empty($decodedRes['rows'])) {
                        $firstRow = reset($decodedRes['rows']);
                        $actualCount = is_array($firstRow) ? reset($firstRow) : $firstRow;
                    }
                    $messages[] = [
                        'role' => 'system',
                        'content' => implode("\n", [
                            '[SYSTEM NOTE — STATUS FILTER CHECK]:',
                            'Query COUNT menghasilkan: ' . ($actualCount ?? '?') . '.',
                            'Jika describe_table SUDAH dipanggil sebelumnya dan TIDAK ada kolom status aktif → angka ini sudah benar, LANGSUNG sajikan ke user tanpa komentar teknis.',
                            'Jika ADA kolom status aktif yang terlewat → jalankan ulang COUNT dengan WHERE filter status.',
                            'DILARANG menulis reasoning teknis di response. Langsung sajikan hasilnya.',
                        ]),
                    ];
                }

                // ── TERMINAL TOOL GUARD (Inline — menghindari json_decode berulang) ──
                $isFailed = is_array($decodedRes) && isset($decodedRes['error']);
                if (!$isFailed && in_array($toolName, $terminalTools)) {
                    if (!$currentIsProbe) {
                        $hasTerminalToolThisTurn = true;
                        $terminalToolCallsCount[$toolName] = ($terminalToolCallsCount[$toolName] ?? 0) + 1;

                        // Bedakan limit antara ERP (statis) dan Query (analitis)
                        $limit = str_contains($toolName, 'erp') ? 3 : 8;

                        if ($terminalToolCallsCount[$toolName] >= $limit) {
                            Log::warning("[Agentic] Terminal tool '{$toolName}' reached limit ({$limit}x). Forcing loop termination at loop #{$loopCount}.");
                            $loopCount = $this->maxToolLoops; // Break on next iteration check
                        }
                    }
                }
            }

            // Bersihkan variabel hasil eksekusi dari memori untuk mencegah memory bloat
            unset($executedResults);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            if ($hasTerminalToolThisTurn) {
                $hasExecutedTerminalTool = true;
            }

            if ($hasTerminalToolThisTurn && $loopCount < $this->maxToolLoops) {
                Log::info("[Agentic] Terminal tool detected. Injecting final response reminder.");
                $finalResponseReminder = $scopeLimited
                    ? "[SYSTEM REMINDER]: Anda baru saja memperoleh data penting dari tool terminal. JANGAN melakukan tool call lagi untuk tujuan yang sama. Segera berikan jawaban akhir yang komprehensif, profesional, dan sopan kepada Bapak/Ibu user menggunakan data tersebut. Jika data adalah tabel, gunakan format 'smart_table'."
                    : "[SYSTEM REMINDER]: Anda baru saja memperoleh konteks penting dari tool terminal. JANGAN melakukan tool call lagi untuk tujuan yang sama. Segera berikan jawaban akhir yang komprehensif, profesional, dan sopan kepada Bapak/Ibu user. Jika data database kosong tetapi user meminta rekomendasi eksternal, market insight, atau produk yang belum ada di database, lanjutkan dengan rekomendasi berbasis pengetahuan umum/analisis bisnis dan sebutkan batasannya secara singkat. Jika data adalah tabel, gunakan format 'smart_table'.";
                $messages[] = [
                    'role' => 'system',
                    'content' => $finalResponseReminder,
                ];
            }

            if (ob_get_level() > 0)
                ob_flush();
            flush();
        }
    }

    public function getSessions(Request $request)
    {
        $query = ChatSession::where('user_id', $request->user()->id);

        if ($search = $request->query('q')) {
            $query->where('title', 'like', '%' . $search . '%');
        }

        $sessions = $query->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->offset((int) $request->query('offset', 0))
            ->limit((int) $request->query('limit', 20))
            ->get(['id', 'title', 'is_pinned', 'updated_at']);

        $data = $sessions->map(function ($session) {
            return [
                'id' => \App\Helpers\HashidsHelper::encode($session->id),
                'title' => $session->title,
                'is_pinned' => (bool) $session->is_pinned,
                'updated_at' => $session->updated_at ? $session->updated_at->toISOString() : null
            ];
        });

        return response()->json($data);
    }

    public function getSession($id)
    {
        $rawId = is_numeric($id) ? $id : \App\Helpers\HashidsHelper::decode($id);
        if (!$rawId) {
            abort(404, __('Sesi tidak ditemukan.'));
        }

        $session = ChatSession::where('user_id', Auth::user()->id)->findOrFail($rawId);

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

        // Compute detected language from the last user message in the session
        $detectedLanguage = 'id'; // default fallback
        $lastUserMessage = ChatMessage::where('chat_session_id', $session->id)
            ->where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->first();
        if ($lastUserMessage) {
            $detectedLanguage = $this->detectLanguage($lastUserMessage->content);
        }

        // Convert session to array and obfuscate ID to avoid Laravel's auto-integer cast
        $sessionData = $session->toArray();
        $sessionData['id'] = \App\Helpers\HashidsHelper::encode($session->id);

        // Convert messages to array and obfuscate session IDs
        $messagesData = $messages->map(function ($msg) {
            $arr = $msg->toArray();
            $arr['chat_session_id'] = \App\Helpers\HashidsHelper::encode($msg->chat_session_id);
            return $arr;
        });

        return response()->json([
            'session' => $sessionData,
            'history' => $messagesData,
            'detected_language' => $detectedLanguage,
            'pagination' => [
                'has_more' => $hasMore,
                'oldest_cursor' => $oldestCursor,
            ]
        ]);
    }

    public function deleteSession($id)
    {
        $rawId = is_numeric($id) ? $id : \App\Helpers\HashidsHelper::decode($id);
        if (!$rawId) {
            abort(404, __('Sesi tidak ditemukan.'));
        }

        ChatSession::where('user_id', Auth::user()->id)->findOrFail($rawId)->delete();
        return response()->json(['success' => true]);
    }

    public function updateSessionTitle(Request $request, $id)
    {
        $request->validate(['title' => 'required|string|max:255']);
        $rawId = is_numeric($id) ? $id : \App\Helpers\HashidsHelper::decode($id);
        if (!$rawId) {
            abort(404, __('Sesi tidak ditemukan.'));
        }

        ChatSession::where('user_id', Auth::user()->id)->findOrFail($rawId)->update(['title' => $request->title]);
        return response()->json(['success' => true]);
    }

    public function togglePin($id)
    {
        $rawId = is_numeric($id) ? $id : \App\Helpers\HashidsHelper::decode($id);
        if (!$rawId) {
            abort(404, __('Sesi tidak ditemukan.'));
        }

        $session = ChatSession::where('user_id', Auth::user()->id)->findOrFail($rawId);
        
        // Use quiet update to avoid changing updated_at, so unpinned chats return to their original position
        $session->timestamps = false;
        $session->is_pinned = !$session->is_pinned;
        $session->save();

        return response()->json([
            'success' => true,
            'is_pinned' => (bool) $session->is_pinned
        ]);
    }

    public function exportExcel(Request $request)
    {
        $request->validate([
            'headers' => 'required|array',
            'rows' => 'required|array',
            'title' => 'nullable|string|max:100',
            'currencyColumns' => 'nullable|array',
            'currency_columns' => 'nullable|array',
            'chart_info' => 'nullable|array',
        ]);

        $headers = $request->input('headers', []);
        $rows = $request->input('rows', []);
        $title = $request->input('title', 'Data Export');
        $currencyColumns = $request->input('currencyColumns') ?? $request->input('currency_columns', []);
        $chartInfo = $request->input('chart_info');
        $filename = $request->input('filename', 'export-' . now()->format('Ymd-His') . '.xlsx');

        $normalizedRows = array_map(function ($row) {
            return is_array($row) ? array_values($row) : (array) $row;
        }, $rows);

        $export = new \App\Exports\ChatTableExport($headers, $normalizedRows, $title, $chartInfo, $currencyColumns);

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
            'currency_columns' => 'nullable|array',
        ]);

        $headers = $request->input('headers', []);
        $rows = $request->input('rows', []);
        $title = $request->input('title', 'Data Export');
        $currencyColumns = $request->input('currencyColumns') ?? $request->input('currency_columns', []);
        $filename = $request->input('filename', 'export-' . now()->format('Ymd-His') . '.pdf');

        $normalizedRows = array_map(function ($row) {
            return is_array($row) ? array_values($row) : (array) $row;
        }, $rows);

        if (count($normalizedRows) > 1500) {
            return response()->json([
                'error' => __('Data terlalu besar untuk format PDF (:rows baris). Maksimal 1.500 baris. Silakan gunakan Export Excel untuk mengunduh data sebesar ini.', ['rows' => count($normalizedRows)])
            ], 400);
        }

        $columnTypes = array_map(function ($header) use ($currencyColumns) {
            if ($this->isExportCurrencyHeader((string) $header, $currencyColumns))
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

    private function isExportCurrencyHeader(string $header, array $currencyColumns): bool
    {
        $normalizedHeader = $this->normalizeExportLabel($header);
        foreach ($currencyColumns as $column) {
            $column = (string) $column;
            $normalizedColumn = $this->normalizeExportLabel($column);
            if ($normalizedColumn !== '' && (
                $normalizedHeader === $normalizedColumn ||
                str_contains($normalizedHeader, $normalizedColumn) ||
                str_contains($normalizedColumn, $normalizedHeader)
            )) {
                return true;
            }
        }

        return false;
    }

    private function normalizeExportLabel(string $label): string
    {
        $label = strtolower($label);
        $label = preg_replace('/\s+/', '_', $label);
        $label = preg_replace('/[^a-z0-9_]/', '', $label);
        $label = preg_replace('/_+/', '_', $label);
        return trim($label, '_');
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
        $cleaned = [];

        // We group tool responses that belong to previous assistant tool calls
        $assistantToolsPending = [];
        $postToolUserNotes = [];

        foreach ($messages as $m) {
            foreach ($internalFields as $field) {
                unset($m[$field]);
            }

            $role = $m['role'] ?? '';

            // If system message appears after the first message, treat as user note
            if ($role === 'system') {
                if (empty($cleaned)) {
                    $cleaned[] = $m;
                } else {
                    $postToolUserNotes[] = [
                        'role' => 'user',
                        'content' => (string) ($m['content'] ?? '')
                    ];
                }
                continue;
            }

            if ($role === 'assistant') {
                // If there were pending user notes before this assistant, flush them
                if (!empty($postToolUserNotes)) {
                    foreach ($postToolUserNotes as $note) {
                        $cleaned[] = $note;
                    }
                    $postToolUserNotes = [];
                }

                $cleaned[] = $m;
                continue;
            }

            if ($role === 'tool') {
                if (!is_string($m['content'] ?? null)) {
                    $m['content'] = json_encode($m['content'] ?? '');
                }
                $cleaned[] = $m;
                continue;
            }

            // Normal user message
            if (!empty($postToolUserNotes)) {
                foreach ($postToolUserNotes as $note) {
                    $cleaned[] = $note;
                }
                $postToolUserNotes = [];
            }
            $cleaned[] = $m;
        }

        // Flush any remaining user notes at the end
        if (!empty($postToolUserNotes)) {
            foreach ($postToolUserNotes as $note) {
                $cleaned[] = $note;
            }
        }

        return $cleaned;
    }

    /**
     * STRATEGY 6: Kompresi History Asisten Masa Lalu
     * Membersihkan rekomendasi prompt lama dan membatasi panjang jawaban lampau
     * agar history multi-turn tidak memakan ribuan token input.
     */
    private function cleanPastAssistantMessage(string $content): string
    {
        // 1. Buang blok rekomendasi prompt lama (💡 Rekomendasi Prompt Selanjutnya: ...)
        $cleaned = preg_replace('/💡\s*\*\*Rekomendasi Prompt Selanjutnya:?\*\*[\s\S]*$/i', '', $content);
        $cleaned = preg_replace('/💡\s*\*\*Recommended Follow-up Prompts:?\*\*[\s\S]*$/i', '', $cleaned);

        // 2. Jika konten jawaban lampau sangat panjang (> 2000 karakter), potong dengan catatan
        if (mb_strlen($cleaned) > 2000) {
            $cleaned = mb_substr($cleaned, 0, 2000) . "\n\n... [Rincian data lampau diringkas untuk efisiensi token]";
        }

        return trim($cleaned);
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
                    'content' => $this->cleanPastAssistantMessage($msg['content'] ?? ''),
                    'tool_calls' => $fakeToolCalls,
                ];
                foreach ($toolResults as $index => $res) {
                    $toolName = $res['tool_name'] ?? 'query';
                    $toolData = $res['data'] ?? '';
                    if (is_string($toolData)) {
                        $toolData = json_decode($toolData, true) ?: $toolData;
                    }

                    if ($toolName === 'execute_query' && is_array($toolData)) {
                        $summary = [
                            'tool' => 'execute_query',
                            'label' => $res['label'] ?? ($toolData['label'] ?? 'Query Data'),
                            'rows_returned' => $toolData['rows_returned'] ?? count($toolData['rows'] ?? []),
                            'columns' => $toolData['columns'] ?? [],
                            '_note' => 'Data riwayat masa lalu telah diringkas untuk efisiensi token. Lakukan query baru jika memerlukan data detail terkini.',
                        ];
                        $toolContent = json_encode($summary, JSON_UNESCAPED_UNICODE);
                    } else {
                        $toolContent = is_string($toolData) ? $toolData : json_encode($toolData, JSON_UNESCAPED_UNICODE);
                        if (strlen($toolContent) > 1000) {
                            $toolContent = mb_substr($toolContent, 0, 1000) . '... [truncated]';
                        }
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $fakeToolCalls[$index]['id'] ?? ('hist_' . uniqid()),
                        'name' => $toolName,
                        'content' => $toolContent,
                        'decoded_data' => is_array($toolData) ? $toolData : null,
                    ];
                }
            } else {
                $c = ($msg['role'] ?? '') === 'assistant'
                    ? $this->cleanPastAssistantMessage($msg['content'] ?? '')
                    : ($msg['content'] ?? '');
                $messages[] = ['role' => $msg['role'] ?? 'user', 'content' => $c];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SYSTEM PROMPT
    // ─────────────────────────────────────────────────────────────────────────
    private function buildSystemPrompt(array $allowedDatabases = [], bool $scopeLimited = true, string $detectedLanguage = 'id', string $userMessage = ''): string
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
        $allUserTableNames = [];
        $isRestrictedRbac = false;
        try {
            // Gunakan allowedDatabases (RBAC) untuk menyusun hint tabel.
            // Cek apakah user memiliki hak akses terbatas (bukan super admin dan bukan wildcard *)
            $hasWildcard = false;
            foreach ($allowedDatabases as $dbCode => $schemas) {
                if ($dbCode === '*') {
                    $hasWildcard = true;
                    break;
                }
                foreach ($schemas as $schema => $tables) {
                    if ($schema === '*') {
                        foreach ($tables as $t) {
                            $name = is_array($t) ? ($t['name'] ?? '') : (string) $t;
                            if ($name === '*') {
                                $hasWildcard = true;
                                break 2;
                            }
                        }
                    }
                    foreach ($tables as $t) {
                        $name = is_array($t) ? ($t['name'] ?? '') : (string) $t;
                        if ($name === '*') {
                            $hasWildcard = true;
                            break 2;
                        }
                    }
                }
            }
            $isRestrictedRbac = !$hasWildcard && !empty($allowedDatabases);

            foreach ($allowedDatabases as $dbCode => $schemas) {
                $tableNames = [];
                foreach ($schemas as $schema => $tables) {
                    foreach ($tables as $t) {
                        $name = is_array($t) ? ($t['name'] ?? '') : $t;
                        if (empty($name) || $name === '*') {
                            continue;
                        }
                        if (str_starts_with($name, 'mv_') || str_starts_with($name, 'cdc_')) {
                            continue;
                        }
                        $tableNames[] = $name;
                        $allUserTableNames[] = $name;
                    }
                }
                $tableNames = array_values(array_unique($tableNames));
                // Prioritaskan tabel master, lalu view bisnis, agar yang paling
                // sering dipakai masuk dalam daftar terbatas ini.
                usort($tableNames, function ($a, $b) {
                    $rank = function (string $n): int {
                        if (preg_match('/^ms_/i', $n) || stripos($n, 'master') !== false) return 0;
                        if (str_starts_with($n, 'view_')) return 1;
                        if (str_starts_with($n, 'v_')) return 2;
                        return 3;
                    };
                    $ra = $rank($a);
                    $rb = $rank($b);
                    if ($ra !== $rb) return $ra <=> $rb;
                    return strcmp($a, $b);
                });
                $tableNames = array_slice($tableNames, 0, self::MAX_TABLES_HINT);
                if (!empty($tableNames)) {
                    $mainTablesHint[] = "Database [{$dbCode}]: " . implode(', ', $tableNames);
                }
            }
            $allUserTableNames = array_values(array_unique($allUserTableNames));
        } catch (\Exception $e) {
            Log::warning("[Agentic] Failed to fetch table hints for prompt: " . $e->getMessage());
        }

        if ($isRestrictedRbac) {
            $businessTerms = array_map(fn($t) => self::formatTableToBusinessTerm($t), $allUserTableNames);
            $businessTerms = array_values(array_unique($businessTerms));
            $tablesFormatted = !empty($businessTerms) ? implode(', ', $businessTerms) : 'tidak ada data';

            $tableHintText = "## 🔒 HAK AKSES DATA TERBATAS (USER RBAC):\n"
                . "Peran akun pengguna ini memiliki izin akses yang DIBATASI dan HANYA dapat mengakses data:\n"
                . implode("\n", $mainTablesHint)
                . "\n\n**ATURAN HAK AKSES MUTLAK (WAJIB DIIKUTI):**\n"
                . "1. Data di atas adalah SATU-SATUNYA data yang diizinkan untuk diakses oleh akun pengguna ini. TIDAK ADA data lain di database yang boleh diakses.\n"
                . "2. DILARANG memanggil tool `search_schema` atau `get_database_schema_info` untuk mencari tabel lain di database.\n"
                . "3. Jika pengguna menanyakan data, entitas, atau topik yang TIDAK ADA dalam daftar di atas (misalnya pengguna menanyakan data pelanggan, transaksi, penjualan, produk, stok, keuangan, piutang, dsb padahal akun hanya memiliki akses ke cabang):\n"
                . "   - ❌ **JANGAN MEMANGGIL TOOL APAPUN (DILARANG panggil search_schema ataupun execute_query).**\n"
                . "   - ✅ **SEGERA BERIKAN JAWABAN NARATIF SECARA LANGSUNG MENGGUNAKAN BAHASA BISNIS:**\n"
                . "     - **DILARANG KERAS MENYEBUTKAN NAMA TEKNIS TABEL ATAU DATABASE (seperti `v_ms_cabang`, `dw_erp`, dll).**\n"
                . "     - Selalu gunakan istilah bahasa bisnis yang ramah: sebut sebagai **\"{$tablesFormatted}\"**.\n"
                . "     - Sampaikan secara sopan dan formal: Jelaskan bahwa berdasarkan hak akses akun yang dimiliki saat ini, Bapak/Ibu hanya memiliki izin untuk mengakses **{$tablesFormatted}**, dan belum memiliki kewenangan/izin akses untuk data yang diminta (misalnya data pelanggan atau transaksi penjualan). Sarankan pengguna untuk menghubungi Administrator Sistem apabila memerlukan izin akses data tersebut.";
        } else {
            $tableHintText = !empty($mainTablesHint)
                ? implode("\n", $mainTablesHint)
                    . "\n\n**Catatan: daftar di atas hanyalah SEBAGIAN dari seluruh tabel yang tersedia.** Untuk nama persis tabel lain (termasuk tabel per-perusahaan `cdc_*` dan materialized view `mv_*`), gunakan tool `get_database_schema_info` atau `search_schema` sebelum menulis query."
                : "Panggil get_database_schema_info untuk melihat daftar tabel.";
        }

        // Tanggal saja (tanpa jam:menit) agar teks system prompt identik sepanjang hari
        // → provider dapat mengaktifkan context caching (prefix prompt di-cache),
        //   sehingga ribuan token system prompt dibayar ~10% di pesan berikutnya.
        $currentTime = now()->translatedFormat('l, d F Y');

        $outOfDomainSection = $scopeLimited
            ? "## 🚫 PERTANYAAN DI LUAR DOMAIN (TERAKHIR — HANYA JIKA SUDAH MENCOBA TOOL)\n\nHANYA jika pertanyaan user SUDAH TERBUKTI tidak berkaitan dengan data bisnis, ERP, atau informasi umum/publik (misal: resep masakan, gosip artis, ramalan cuaca) DAN tool pencarian tidak menghasilkan data yang relevan, barulah balas dengan:\n\n*\"Mohon maaf Bapak/Ibu, saya hanya dapat membantu dalam kapasitas sebagai Analis Data Bisnis, Konsultan Sistem ERP, dan pencarian informasi bisnis terkini. Untuk pertanyaan tersebut, saya tidak memiliki kewenangan untuk memberikan jawaban. Apakah ada kebutuhan analisis data atau panduan ERP yang dapat saya bantu?\"*\n\n**PENTING: Kalimat penolakan ini DILARANG digunakan jika:**\n- User bertanya tentang data bisnis atau informasi pasar/regulasi (cabang, dealer, penjualan, keuangan, stok, tarif PPN, dll)\n- Terjadi error database (cari tabel yang benar, jangan tolak)\n- Pertanyaan ambigu (coba tool web_search atau database dulu, baru putuskan)"
            : "## CAKUPAN JAWABAN\n\nAnda bebas membantu user dengan pertanyaan apapun di luar konteks database dan ERP menggunakan tool pencarian web `web_search` atau pengetahuan umum. Tetap utamakan analisis data bisnis jika pertanyaan terkait database, namun Anda BOLEH menjawab pertanyaan umum, pengetahuan umum, dan topik lainnya secara helpful dan informatif.";

        $identitySection = $scopeLimited
            ? "Anda adalah asisten DataBot yang bertugas untuk:\n1. **Analisis data bisnis** - mengakses dan menginterpretasikan data dari database yang tersedia\n2. **Panduan sistem ERP** - membantu navigasi dan penggunaan modul ERP perusahaan\n3. **Pencarian informasi eksternal (Web Search)** - mencari informasi publik terkini dari internet menggunakan tool `web_search` jika user menanyakan data/informasi dari luar database atau regulasi terbaru."
            : "Anda adalah asisten DataBot, Data Analyst dan Business Advisor untuk perusahaan. Tugas utama Anda:\n1. **Analisis data bisnis** - mengakses dan menginterpretasikan data dari database yang tersedia\n2. **Panduan sistem ERP** - membantu navigasi dan penggunaan modul ERP perusahaan\n3. **Pencarian informasi eksternal (Web Search)** - mencari informasi publik terkini dari internet menggunakan tool `web_search` jika user menanyakan data/informasi dari luar database\n4. **Rekomendasi bisnis non-database** - memberikan insight, ide produk, dan rekomendasi pasar berbasis pencarian web serta analisis bisnis ketika user secara eksplisit meminta data dari luar database atau produk yang belum tersedia di database";

        $freeScopeBusinessSection = $scopeLimited
            ? ""
            : "\n## MODE CAKUPAN BEBAS - REKOMENDASI NON-DATABASE\n\nJika user meminta rekomendasi dari luar database, market insight, atau produk yang BELUM ada di database:\n- Gunakan database hanya untuk mengetahui produk/data internal yang sudah ada, bukan sebagai satu-satunya sumber jawaban.\n- Jika data transaksi lokal kosong, tetap berikan rekomendasi berbasis pencarian web menggunakan tool `web_search` dan analisis bisnis.\n- Untuk permintaan seperti \"produk yang belum punya di database\", bandingkan dengan daftar produk internal yang berhasil ditemukan, lalu rekomendasikan item/segmen yang tidak muncul di database.\n- Jangan berkata \"saya belum bisa memberikan rekomendasi\" hanya karena database tidak memiliki transaksi di wilayah tertentu.\n- Selalu gunakan tool `web_search` untuk mencari informasi/berita pasar terkini atau tarif pajak/regulasi terbaru.\n- Untuk produk otomotif seperti baterai/aki, boleh gunakan faktor pasar umum: populasi motor/mobil, iklim panas, kebutuhan replacement, ketersediaan ukuran umum, reputasi merek, margin, dan risiko stok mati.\n";

        $limitedScopeSection = $scopeLimited
            ? "\nUntuk mode database terbatas, tugas berikut berlaku sebagai batasan utama:\n1. **Analisis data bisnis** — mengakses dan menginterpretasikan data dari database yang tersedia\n2. **Panduan sistem ERP** — membantu navigasi dan penggunaan modul ERP perusahaan\n3. **Pencarian informasi eksternal (Web Search)** — mencari data/informasi publik dari luar database menggunakan tool `web_search` jika ditanyakan.\n"
            : "";

        $botIdentityLine = $scopeLimited
            ? "Anda adalah DataBot, Data Analyst AI ahli untuk perusahaan dengan **akses langsung ke database bisnis** dan kemampuan **pencarian web (Web Search)** melalui alat (tools)."
            : "Anda adalah DataBot, asisten AI serbaguna untuk perusahaan. Anda memiliki **akses langsung ke database bisnis** dan kemampuan **pencarian web (Web Search)** melalui alat (tools), sehingga Anda dapat membantu pertanyaan umum, pengetahuan umum, data eksternal, dan topik di luar database secara detail.";

        $langInstruction = "";
        if ($detectedLanguage === 'id') {
            $langInstruction = "## 🔴 MANDATORI BAHASA UTAMA: BAHASA INDONESIA
1. **User menggunakan Bahasa Indonesia.** Anda WAJIB merespons sepenuhnya dalam Bahasa Indonesia.
2. Anda WAJIB menggunakan alias kolom SQL dalam istilah Bahasa Indonesia yang persis diminta user:
   - Cabang / Dealer / (atau nama dimensi lainnya)
   - HPP: Nilai total harga pokok SATUAN (SUM(kolom_satuan_hpp)). BERBEDA dari Total HPP. DILARANG mengalikan dengan qty.
   - Total HPP: Total harga pokok keseluruhan (SUM(kolom_satuan_hpp * kolom_qty) atau SUM(kolom_total_hpp)).
   - Netto: Nilai total penjualan kotor sebelum diskon (SUM(kolom_satuan_harga_jual)).
   - Total Netto: Nilai total penjualan bersih setelah diskon (SUM(kolom_total_netto)).
   - Diskon: Diskon penjualan (SUM(kolom_diskon)).
   - Profit: Keuntungan bersih (Total Netto - Total HPP).
3. DILARANG menggunakan istilah Bahasa Inggris seperti \"COGS\", \"Total COGS\", \"Net\", \"Total Net\", \"Discount\" jika user bertanya dalam Bahasa Indonesia.";
        } else {
            $langInstruction = "## 🔴 PRIMARY LANGUAGE MANDATE: ENGLISH
1. **User is using English.** You MUST reply completely in English.
2. You MUST use SQL column aliases in English terms:
   - Branch / Dealer / (or other dimension names)
   - COGS: SUM of unit cost price (SUM(unit_cost_column)).
   - Total COGS: SUM of total cost (SUM(unit_cost_column * qty_column) or SUM(total_cost_column)).
   - Net: Sum of gross selling price before discount.
   - Total Net: Sum of net selling price after discount.
   - Discount: Discount.
   - Profit: Net profit (Total Net - Total COGS).
3. DO NOT use Indonesian terms like \"HPP\", \"Total HPP\", \"Netto\", \"Total Netto\", \"Diskon\" if user asks in English.";
        }

        // ── STRATEGY 1: DYNAMIC MODULAR PROMPT SECTIONS ──────────────────────
        $isRegionalQuery = empty($userMessage) || (bool) preg_match('/\\b(cabang|kota|propinsi|provinsi|wilayah|daerah|regional|area|lokasi|alamat|jawa|sumatera|sulawesi|kalimantan|bali|jakarta|surabaya|medan|semarang|bandung|makassar)\\b/i', $userMessage);
        $isProductQuery = empty($userMessage) || (bool) preg_match('/\\b(produk|barang|item|part|sparepart|aki|oli|ban|merek|brand|sku|stok|stock|kategori)\\b/i', $userMessage);
        $isErpQuery = empty($userMessage) || (bool) preg_match('/\\b(menu|modul|panduan|navigasi|cara|bantuan|tutorial|erp|input|tombol|fitur|setting)\\b/i', $userMessage);
        $isChartQuery = empty($userMessage) || (bool) preg_match('/\\b(grafik|chart|tren|trend|visual|diagram|plot|per bulan|per tahun|bulanan|tahunan|pertumbuhan|banding)\\b/i', $userMessage);
        $isMetricQuery = empty($userMessage) || (bool) preg_match('/\\b(hpp|cogs|harga pokok|omset|omzet|netto|net|gross|kotor|bersih|diskon|discount|potongan|profit|laba|margin|rugi|penjualan|sales|revenue|transaksi|faktur|invoice|piutang|ar|ap|keuangan|nominal|pembelian|beli|jual|retur)\\b/i', $userMessage);

        $regionalSection = "";
        if ($isRegionalQuery) {
            $regionalSection = <<<'REGIONAL'
## 🔴 ATURAN TERPENTING #1B — RESOLVE NAMA CABANG/ENTITAS SEBELUM QUERY

User sering menyebut nama cabang/dealer/entitas dengan ejaan tidak persis. Nama di database bisa berbeda: "hm yamin" → "HM. YAMIN", "kapt muslim" → "KAPT. MUSLIM 1", dll.

**SANGAT PENTING (OPTIMASI PERFORMA & PENGHINDARAN DISK FULL - SECARA DINAMIS)**:
**DILARANG KERAS** menjalankan `SELECT DISTINCT` pencarian/resolusi nama entitas (cabang, dealer, barang, pelanggan, dll) pada tabel/view transaksi/detail yang berukuran besar (biasanya mengandung kata `penjualan`, `pembelian`, `kartu_stock`, `intransit`, `rinci`, `detail`, `transaksi`). Melakukan `DISTINCT` pada jutaan data transaksi akan menyebabkan database kehabisan ruang disk/temp space (`Disk full: 7 ERROR: could not write to file ... No space left on device`).
Sebagai gantinya, Anda **WAJIB** mencari dan menggunakan tabel/view master yang berukuran jauh lebih kecil untuk query resolusi nama/probe:
- Untuk mencari/resolusi Nama Cabang: gunakan tabel/view master cabang (biasanya memiliki kata kunci `master` dan `cabang` atau `dealer`, contoh: `view_master_cabang_...` atau `mst_cabang`).
- Untuk mencari/resolusi Nama Barang/Produk: gunakan tabel/view master barang (biasanya memiliki kata kunci `master` dan `barang` atau `produk` atau `item`, contoh: `view_master_barang_...` atau `mst_barang`).
- Untuk mencari/resolusi Nama Pelanggan: gunakan tabel/view master pelanggan (biasanya memiliki kata kunci `master` dan `pelanggan` atau `customer`, contoh: `view_master_pelanggan_...` atau `mst_pelanggan`).

**WAJIB LAKUKAN 2 LANGKAH INI saat user menyebut nama cabang/dealer/entitas SPESIFIK (bukan wilayah/propinsi/kota):**

**Langkah 1 — Resolve nama eksak dengan MULTI-KEYWORD OR (wajib dilakukan SEKALI saja):**

Jika nama user terdiri dari beberapa kata (misal: "hm yamin"), buat filter yang mencari SETIAP kata secara terpisah menggunakan OR pada tabel master yang relevan:
```sql
SELECT DISTINCT nama_cabang
FROM schema_name.view_master_cabang -- Ganti dengan tabel master cabang riil yang terdeteksi di database saat ini!
WHERE nama_cabang ILIKE '%hm%'
   OR nama_cabang ILIKE '%yamin%'
LIMIT 10
```
**MENGAPA:** "hm yamin" tidak akan match `%hm yamin%` jika di database ada titik ("HM. YAMIN") atau spasi berbeda. Memecah per kata memastikan hasil selalu ditemukan.



**Aturan pemecahan kata kunci:**
- Jika user menyebut nama cabang diikuti angka, angka itu adalah bagian dari nama cabang. Contoh: "cabang binjai 2 tahun ini" berarti cabang `BINJAI 2` pada tahun berjalan, BUKAN "Binjai selama 2 tahun" dan BUKAN gabungan `BINJAI 1/2/3`.
- Untuk pola `cabang [nama] [angka] tahun ini`, probe wajib menyertakan angka: `nama_cabang ILIKE '%binjai%' AND nama_cabang ILIKE '%2%'`, lalu query utama wajib memakai nama eksak hasil probe, misalnya `nama_cabang = 'BINJAI 2'`.
- Jangan menggabungkan beberapa cabang bernomor hanya karena hasil probe menemukan banyak pilihan. Jika user menyebut angka cabang, pilih nama eksak yang mengandung angka tersebut.
- Frasa periode multi-tahun hanya valid jika user menulis jelas seperti "2 tahun terakhir", "dua tahun terakhir", "2025 vs 2026", atau "dua tahun". Frasa "cabang binjai 2 tahun ini" harus dibaca sebagai cabang `Binjai 2` + periode `tahun ini`.
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
If user asks about regions:
1. **DIRECTLY use `ILIKE`** on relevant province/city columns in the main query.
2. If no data, do automatic search (e.g., search by city if province is empty) WITHOUT asking user for permission.
3. **ABSOLUTELY PROHIBITED** from diverting to National data if regional data is not found. Report using business language: "Berdasarkan data yang tersedia, belum ditemukan catatan aktivitas bisnis untuk wilayah [Wilayah] pada periode ini."

REGIONAL;
        }

        $chartSection = "";
        if ($isChartQuery) {
            $chartSection = <<<'CHART'
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
- Untuk data tren bulanan tahun berjalan (single year): **HANYA sertakan label bulan yang sudah berjalan/memiliki data transaksi** (contoh jika data sampai Agustus, gunakan `["Jan", "Feb", ..., "Agu"]`). JANGAN sertakan bulan mendatang yang belum berjalan (Sep-Des) agar sumbu X bersih dan tidak ada ruang kosong di ujung kanan.
- Untuk data per cabang: gunakan nama cabang sebagai label

**PANDUAN KHUSUS PERBANDINGAN TAHUN/PERIODE (YoY):**
Jika user meminta perbandingan antar tahun (contoh: "penjualan 2025 vs 2026" atau "grafik 2025 dan 2026"):
1. **STRUKTUR QUERY SQL WAJIB BERBENTUK PIVOT / WIDE FORMAT**:
   - Query SQL **WAJIB** menghasilkan 1 baris per bulan dengan kolom terpisah untuk tiap tahun menggunakan `SUM(CASE WHEN ...)`:
   ```sql
   SELECT 
       periode_bulan AS "Bulan",
       SUM(CASE WHEN periode_tahun = '2025' THEN total_netto ELSE 0 END) AS "Penjualan 2025",
       SUM(CASE WHEN periode_tahun = '2026' THEN total_netto ELSE 0 END) AS "Penjualan 2026"
   FROM sch_mbi.view_data_penjualan_rinci_mbi
   WHERE periode_tahun IN ('2025', '2026')
   GROUP BY periode_bulan
   ORDER BY periode_bulan::int;
   ```
   - **DILARANG KERAS** melakukan `GROUP BY periode_tahun, periode_bulan` secara vertikal (yang menghasilkan 20+ baris). Query vertikal akan merusak format visualisasi grafik dan tabel!
2. **FORMAT GRAFIK (CHART)**:
   - **DILARANG** menggunakan label sumbu X yang memanjang sekuensial atau menampilkan tahun sebagai sumbu X ("2025", "2026").
   - **WAJIB** gunakan sumbu X bersama (Shared Axis) yang berisi nama bulan ("Jan", "Feb", ..., "Des").
   - **WAJIB** pecah data ke dalam beberapa `datasets` (satu dataset untuk tiap tahun):
     `{"labels": ["Jan", "Feb", ...], "datasets": [{"label": "Penjualan 2025", "data": [...]}, {"label": "Penjualan 2026", "data": [...]}]}`
   - **PENANGANAN BULAN MENDATANG:** Untuk tahun berjalan (2026), data untuk bulan-bulan yang belum dilalui diisi dengan `null` (bukan `0`). Ini penting agar garis grafik berhenti di bulan terakhir yang ada datanya.

**URUTAN WAJIB jika user minta grafik/analisis:**
1. Ringkasan Eksekutif
2. chart (grafik visualisasi)
3. smart_table (tabel data)
4. Insight Strategis

**URUTAN WAJIB jika user minta daftar/tampilkan:**
1. Ringkasan Eksekutif
2. smart_table (tabel data detail)
3. Insight Strategis (singkat, tanpa grafik tambahan)

CHART;
        }

        $erpSection = "";
        if ($isErpQuery) {
            $erpSection = <<<'ERP'
## ERP GUIDANCE & NAVIGATION
1. Saat `get_erp_menu_navigation` mengembalikan `display_text`, tampilkan **verbatim**. JANGAN tambahkan "Ringkasan Eksekutif".
2. **PROTOKOL PENEMUAN PANDUAN (PROACTIVE DISCOVERY)**:
   - Jika user bertanya tentang "cara", "langkah", atau "bagaimana" menggunakan menu ERP → **WAJIB** panggil `get_erp_guidance`.
   - **SINONIM KRITIS**: Jika mencari "Penerimaan Barang" tidak ada hasil, Anda **WAJIB** mencari "Tanda Terima Barang" atau "TTB". Jika mencari "Pengeluaran Barang" tidak ada hasil, cari "Surat Jalan".
   - Jika `get_erp_guidance` mengembalikan `total_found: 0`, jangan menyerah. Coba keyword yang lebih luas atau cari di `get_erp_menu_navigation` untuk mendapatkan nama menu yang lebih akurat, lalu cari lagi di `get_erp_guidance`.
3. **PANDUAN ERP (Inline Content)**: Saat menyajikan panduan dari `get_erp_guidance`, Anda **WAJIB** menyertakan seluruh konten dari field `detail_panduan_lengkap` secara utuh.
4. **PENTING (Gambar & Video)**:
   - Anda **DILARANG** merangkum atau menghilangkan tag gambar Markdown (`![alt](url)`) yang ada di dalam `detail_panduan_lengkap`. Sertakan gambar tersebut tepat di lokasi aslinya agar muncul secara inline.
   - Sertakan juga link video yang ada di dalam teks (biasanya di bagian akhir) agar user dapat menonton video tutorialnya.
5. Gunakan gaya bahasa profesional Bapak/Ibu saat memberikan pengantar sebelum konten panduan tersebut.

ERP;
        }

        $productSection = "";
        if ($isProductQuery) {
            $productSection = <<<'PRODUCT'
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

PRODUCT;
        }

        $metricSection = "";
        if ($isMetricQuery) {
            $metricSection = <<<'METRIC'
## 🔴 PROTOKOL PENEMUAN & PEMETAAN DINAMIS (SEMANTIC MAPPING METRIK BISNIS)
### **1. Protokol Penemuan & Pemetaan Dinamis (Dynamic Discovery & Semantic Mapping Protocol)**

Saat user meminta metrik bisnis tertentu (seperti HPP, Total HPP, Netto, Total Netto, Diskon, Profit), Anda **WAJIB** mengikuti langkah-langkah berikut secara berurutan untuk memetakan istilah bisnis ke kolom database nyata secara dinamis tanpa melakukan hardcoding nama kolom:

1. **Panggil `describe_table` (WAJIB)**: Dapatkan daftar kolom beserta tipenya secara eksak untuk tabel yang sedang dianalisis.
2. **Pecahkan secara Semantik**: Jangan berasumsi nama kolom. Cocokkan istilah bisnis dengan kata kunci yang ada pada nama kolom fisik dari tabel:
   - **Harga Pokok / HPP / COGS**: Cari kolom fisik yang mengandung kata kunci `hpp`, `pokok`, `cogs`, `cost`, `purchase`, `price_cost`.
   - **Netto / Selling Price**: Cari kolom fisik yang mengandung kata kunci `netto`, `net`, `jual`, `price`, `selling`.
   - **Discount / Potongan (Total)**: Cari kolom fisik yang mengandung kata kunci `disc`, `potongan`, `discount`, `rabat`, `pot`.
   - **Qty / Kuantitas**: Cari kolom fisik yang mengandung kata kunci `qty`, `jumlah`, `kuantitas`, `quantity`, `jual_qty`.
3. **Panggil `get_table_preview` (WAJIB)**: Lihat 5 baris data sampel untuk memahami skala dan format kolom (apakah kolom moneter bersifat satuan atau total).
   - Cara membedakan satuan vs total: Jika `nilai_kolom * qty` mendekati nilai total penjualan, maka kolom tersebut adalah **satuan**. Jika nilainya sudah mencerminkan nilai total, maka kolom tersebut sudah **total**.
4. **Prioritaskan Kolom Fisik**: Jika ada kolom fisik di database yang secara langsung menyimpan nilai metrik tersebut (misal: terdapat kolom bernama `total_hpp` atau `profit` atau `laba`), gunakan kolom fisik tersebut secara langsung dalam query.
5. **Formulasikan secara Dinamis jika Kolom Fisik Tidak Ada**: Jika kolom fisik tidak tersedia secara langsung, gunakan kolom-kolom yang berhasil Anda temukan untuk merumuskan perhitungan secara akurat:
   - **Dalam Kueri Detail (Tanpa `GROUP BY`)**:
     - **HPP / COGS**: Gunakan kolom satuan harga pokok yang ditemukan.
     - **Total HPP / Total COGS**: Jika tidak ada kolom fisik total HPP, hitung menggunakan rumus: `kolom_satuan_hpp * kolom_qty`.
     - **Profit**: Hitung menggunakan rumus: `kolom_total_netto - (kolom_satuan_hpp * kolom_qty)` atau `kolom_total_netto - kolom_total_hpp`. (Sapaan labelnya tetap "Profit", tidak ada pemisahan "Total Profit").
   - **Dalam Kueri Agregasi (Dengan `GROUP BY`)**:
      - **HPP (COGS) DAN Total HPP (Total COGS) sekaligus (PENTING/MANDATORI)**: Jika user meminta KEDUA-DUANYA ("HPP" dan "Total HPP" atau "COGS" dan "Total COGS"), Anda **WAJIB** menyertakan KEDUA kolom tersebut di dalam SELECT secara berdampingan. Hitung HPP (atau COGS) = `SUM(kolom_satuan_hpp)` DAN Total HPP (atau Total COGS) = `SUM(kolom_satuan_hpp * kolom_qty)` (atau `SUM(kolom_total_hpp)`). Kedua nilai ini tidak sama karena HPP adalah total dari unit cost price, sedangkan Total HPP adalah total dari cost price dikali kuantitas. DILARANG MENGGABUNGKAN ATAU MENGHILANGKAN SALAH SATU KOLOM DARI SELECT LIST DENGAN ALIAS YANG SESUAI!
      - **HPP (COGS)**: Hitung total dari unit cost price: `SUM(kolom_satuan_hpp)`. **DILARANG KERAS** menghitung rata-rata, weighted average, atau menggunakan perkalian kuantitas (seperti `SUM(kolom_satuan_hpp * kolom_qty)`) karena HPP di sini berdiri sendiri sebagai jumlah harga pokok satuan.
      - **Total HPP (Total COGS)**: Hitung menggunakan rumus total perkalian kuantitas: `SUM(kolom_satuan_hpp * kolom_qty)` atau `SUM(kolom_total_hpp)`.
      - **Profit**: Hitung menggunakan rumus: `SUM(kolom_total_netto) - SUM(kolom_satuan_hpp * kolom_qty)` (yang merupakan `Total Netto - Total HPP`). Label alias kolom di SQL wajib ditulis sebagai `"Profit"`.

### **2. Aturan Perbedaan Istilah Berpasangan & Istilah Tunggal**

Saat menyusun kolom SELECT, pastikan Anda mematuhi aturan berikut agar visualisasinya tepat dan tidak redundan:
- **Netto vs Total Netto**:
  - **Netto**: Nilai total penjualan kotor sebelum dikurangi diskon. Hitung sebagai: `SUM(kolom_satuan_harga_jual)`. **DILARANG KERAS** menghitung rata-rata, weighted average, atau mengalikan dengan kuantitas.
  - **Total Netto**: Nilai total penjualan bersih setelah dikurangi diskon. Hitung sebagai: `SUM(kolom_total_netto)` (atau `SUM(kolom_satuan_harga_jual * kolom_qty - kolom_total_diskon)`).
  - Tampilkan sebagai dua kolom terpisah jika user meminta keduanya.
- **HPP vs Total HPP (atau COGS vs Total COGS)**:
  - **HPP (atau COGS)**: Nilai total harga pokok satuan. Hitung sebagai: `SUM(kolom_satuan_hpp)`. **DILARANG KERAS** menghitung rata-rata atau mengalikan dengan kuantitas.
  - **Total HPP (atau Total COGS)**: Nilai total harga pokok keseluruhan. Hitung sebagai: `SUM(kolom_satuan_hpp * kolom_qty)` atau `SUM(kolom_total_hpp)`.
  - **WAJIB**: Tampilkan sebagai dua kolom terpisah jika user meminta keduanya. Keduanya **tidak bernilai sama** karena HPP adalah sum dari harga pokok satuan (`SUM(kolom_satuan_hpp)`), sedangkan Total HPP adalah sum dari total harga pokok perkalian kuantitas. DILARANG KERAS memaksa nilainya sama atau menghilangkan salah satunya.
- **Profit**:
  - Hanya ada istilah **Profit** (mewakili total keuntungan). Tidak ada "Total Profit" atau "Profit Satuan".
  - Cara hitungnya: `Total Netto - Total HPP` (atau `total_netto - total_hpp`).
  - Harus selalu ditampilkan sebagai kolom `"Profit"` jika diminta oleh user.

### **3. Aturan Eksekusi & Output**
- **Wajib Tampil**: Semua metrik yang diminta user **HARUS** ada di tabel hasil, baik berasal dari kolom asli maupun hasil kalkulasi.
- **Identifikasi Sumber**: Jika data pendukung tidak ada di tabel utama, lakukan `JOIN` ke tabel master yang relevan secara mandiri.
- **Format Profesional**: Sajikan data dalam format horizontal yang bersih. Gunakan alias kolom yang mencerminkan istilah bisnis yang diminta user (bukan nama kolom teknis).
- **FORMAT TABEL LEBAR (WIDE TABLE)**: Anda **WAJIB** menghasilkan data dalam format horizontal (1 baris banyak kolom). Letakkan dimensi (Nama Cabang/Kategori/dll) di kolom pertama, lalu metrik-metrik di kolom-kolom berikutnya. **DILARANG KERAS** memutar (pivot) hasil menjadi format vertikal/baris kecuali diminta per-barang/per-tanggal.

## 🔴 ATURAN TERPENTING #2 — AGREGASI WAJIB (GROUP BY)

Jika user menyebut istilah bisnis (HPP, Netto, Diskon, Profit, Omzet, Qty) **tanpa kata "detail" atau "per transaksi"**, Anda WAJIB:

1. Gunakan `SUM(nama_kolom_dari_describe_table)` — BUKAN nama kolom mentah
2. GROUP BY HANYA kolom dimensi/identitas (nama_cabang, nama_dealer, dll)
3. DILARANG memasukkan kolom moneter ke GROUP BY

**Contoh Pola Query (semua nama kolom adalah PLACEHOLDER — wajib diganti dengan hasil describe_table):**
```sql
-- User: "Berapa profit per cabang?"
-- Setelah describe_table & get_table_preview: tentukan sendiri kolom dan ekspresi yang tepat
SELECT [kolom_identitas]          AS "Cabang",
       [ekspresi_total_netto]      AS "Total Netto",
       [ekspresi_total_hpp]        AS "Total HPP",
       [ekspresi_total_netto] - [ekspresi_total_hpp] AS "Profit"
FROM [schema].[table]
GROUP BY [kolom_identitas]
```

## 🔴 ATURAN TERPENTING #1B — SATU ISTILAH USER = SATU ALIAS KOLOM SQL

**WAJIB DIBACA**: Jika user menyebut beberapa istilah bisnis sekaligus, **SETIAP istilah harus muncul sebagai kolom tersendiri** dalam SELECT dengan alias yang PERSIS sama dengan istilah user.

**Contoh — User minta: "tampilkan Netto, Total Netto, HPP, Total HPP, Diskon, dan Profit":**

Ini berarti query WAJIB menghasilkan **6 kolom terpisah**. Tentukan sendiri rumus tiap kolom berdasarkan hasil `describe_table` dan `get_table_preview` — jangan terpaku pada contoh di bawah:
```sql
-- Setelah describe_table & get_table_preview: tentukan sendiri kolom dan rumus yang tepat
SELECT [kolom_identitas_dari_describe_table]  AS "Cabang",
       [ekspresi_netto_dari_describe_table]    AS "Netto",
       [ekspresi_total_netto]                  AS "Total Netto",
       [ekspresi_hpp]                          AS "HPP",
       [ekspresi_total_hpp]                    AS "Total HPP",
       [ekspresi_diskon]                       AS "Diskon",
       [ekspresi_profit]                       AS "Profit"
FROM [schema].[table]
WHERE ...
GROUP BY [kolom_identitas_dari_describe_table]
```

**ATURAN KRITIS ALIAS:**
- Alias kolom di SELECT WAJIB identik dengan istilah yang user minta (misal user minta "Total Netto" → alias harus `AS "Total Netto"`, bukan `AS "Netto"` atau `AS "netto"`).
- Jika user minta "Netto" DAN "Total Netto" sebagai dua hal terpisah → buat DUA kolom berbeda di SELECT.
- Jangan pernah menggabungkan dua istilah user menjadi satu kolom.
- Jangan pernah menghilangkan satu pun istilah yang user minta dari SELECT.
- Jika ada istilah yang tidak dapat dipenuhi dari data (kolom tidak ada), nyatakan dengan jelas di narasi — JANGAN diam-diam menghilangkan kolom tersebut.

**DEFINISI PERBEDAAN ISTILAH BERPASANGAN (KRITIS — JANGAN SAMAKAN):**

Saat user meminta istilah yang tampak mirip secara berpasangan, keduanya HARUS muncul sebagai kolom terpisah di tabel:

| Istilah User | Makna Bisnis |
|---|---|
| **Netto** | Nilai total penjualan kotor — total harga jual sebelum dikurangi diskon (SUM(harga_jual * qty)) |
| **Total Netto** | Nilai total penjualan bersih — total penjualan setelah dikurangi diskon (SUM(total_netto)) |
| **HPP** | Jumlah total harga pokok SATUAN — dihitung sebagai SUM(kolom_satuan_hpp). **BERBEDA** dari Total HPP. DILARANG mengalikan dengan qty. |
| **Total HPP** | Total harga pokok keseluruhan — dihitung sebagai SUM(kolom_satuan_hpp * kolom_qty) atau SUM(kolom_total_hpp). **BERBEDA** dari HPP. |
| **Profit** | Keuntungan bersih keseluruhan — dihitung sebagai (Total Netto - Total HPP). Tidak ada pemisahan "Total Profit" atau "Profit Satuan" |

**Prinsip utama: Jika user minta N kolom, tampilkan N kolom.** Jangan kurangi, jangan gabung. Jika setelah query dijalankan dua kolom ternyata bernilai sama, itu adalah hasil data yang valid — bukan alasan untuk menghapus salah satunya. Tentukan rumus tiap kolom sendiri berdasarkan hasil `describe_table` dan `get_table_preview`.

### **1C. SISTEM REMINDER PENGHITUNGAN DINAMIS (INTERNAL REMINDER - JANGAN PERNAH TAMPILKAN KE USER)**

**PENTING: Protokol ini dirancang agar Anda berpikir secara akurat di balik layar untuk menghitung metrik bisnis, tanpa membocorkan formula teknis atau nama kolom asli ke user.**

Saat menyusun query, ikuti alur berpikir internal berikut:
1. **Lihat Hasil `describe_table`**:
   - Cari tahu apakah ada kolom fisik yang langsung mewakili metrik yang diminta (misalnya, kolom bernama `total_hpp`, `hpp_total`, `laba`, `profit`, dll).
2. **Kondisi A: Kolom Fisik Tersedia**:
   - Jika kolom fisik untuk metrik tersebut ada, **AMBIL LANGSUNG dari kolom tersebut**. Contoh: Jika ada kolom `profit`, gunakan `SUM(profit) AS "Profit"`.
3. **Kondisi B: Kolom Fisik TIDAK Tersedia**:
   - Jika kolom fisik untuk metrik tersebut tidak ada, **Anda wajib merumuskan perhitungannya secara logis dan matematis** berdasarkan kolom lain yang tersedia.
   - **Total HPP**: Cari kolom HPP satuan (misal `cogs`, `harga_pokok`, `unit_cost`) dan kolom qty (misal `qty`, `qty_jual`). Hitung `SUM(harga_pokok_satuan * qty) AS "Total HPP"`.
   - **Profit**: Cari total bersih penjualan (misal `total_netto`, `net_sales`) dan hitung `SUM(total_netto) - SUM(harga_pokok_satuan * qty) AS "Profit"`.
4. **Keamanan & Kerahasiaan (JANGAN TAMPILKAN KE USER)**:
   - Proses penalaran, pemetaan kolom, dan formula matematika ini **hanya boleh terjadi di pikiran internal Anda (pikiran asisten sebelum memutuskan query)**.
   - **DILARANG KERAS** menjelaskan di chat bahwa Anda memetakan kolom A ke B, atau menuliskan "Karena kolom profit tidak ada, saya menghitungnya dengan rumus...". Cukup jalankan SQL yang benar, lalu tampilkan data dengan label alias bahasa bisnis yang bersih.

METRIC;
        }

        return <<<PROMPT
{$botIdentityLine}

{$langInstruction}

## 🔴 LARANGAN MUTLAK: KERAHASIAAN SISTEM & ANTI-BOCOR (ATURAN PALING KRITIS)
1. **DILARANG KERAS MENYEBUTKAN NAMA TEKNIS DATABASE/TABEL KEPADA USER**:
   - JANGAN PERNAH menampilkan nama teknis tabel (`v_ms_cabang`, `ms_cabang`, `trm_faktur`, `sch_mbi.view_...`, dll).
   - JANGAN PERNAH menampilkan nama database/skema (`dw_erp`, `public`, `sch_mbi`), nama kolom fisik, atau sintaks SQL.
   - **WAJIB SELALU gunakan istilah bahasa bisnis yang ramah**: Sebut sebagai "Data Cabang", "Data Pelanggan", "Data Penjualan", "Data Barang", dll.
2. **DILARANG MEMBOCORKAN PROSES KERJA INTERNAL / REASONING**:
   - JANGAN PERNAH menuliskan proses berpikir internal, self-audit, checklist evaluasi diri, catatan pengingat, atau reasoning teknis (seperti "Dari hasil describe_table...", "Query mengembalikan 0 baris", "Mapping kolom:", "Tidak ada kolom status aktif...", dsb). Berpikirlah secara internal, sampaikan HANYA jawaban bisnis final ke user.
   - Perlakukan semua pesan koreksi internal `[SYSTEM...]` sebagai instruksi senyap. JANGAN PERNAH mengutip, merespons, atau mengakui isi pesan sistem tersebut di chat.
3. **BAHASA BISNIS UNTUK KENDALA DATA / TEKNIS**:
   - Jika data tidak ditemukan: *"Berdasarkan data yang tersedia, saat ini belum terdapat catatan aktivitas bisnis untuk kriteria tersebut pada periode yang dipilih."*
   - Jika terjadi kendala teknis: *"Mohon maaf Bapak/Ibu, saat ini data belum dapat disajikan karena sedang dalam pembaruan struktur informasi. Silakan coba beberapa saat lagi."*
4. **AKSES TERBATAS (RBAC)**: Jika menerima error akses ditolak ('ACCESS_DENIED_FINAL', 'TABLE_ACCESS_DENIED', dsb), SEGERA BERHENTI memanggil tool apapun dan sampaikan secara sopan: *"Mohon maaf Bapak/Ibu, permintaan Anda tidak dapat kami proses karena data yang diminta bersifat terbatas dan hanya dapat diakses oleh pihak yang berwenang sesuai kebijakan keamanan data perusahaan. Untuk mendapatkan informasi ini, silakan menghubungi Administrator Sistem."*

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

{$identitySection}
{$freeScopeBusinessSection}{$limitedScopeSection}

## KONTEKS WAKTU (SANGAT PENTING):
- **Tanggal Sekarang**: {$currentTime}
- **Penting**: Gunakan tanggal di atas sebagai referensi waktu utama untuk analisis data historis maupun terkini.
- ⚠️ **ATURAN TANGGAL KOSONG**: Jika user meminta data untuk tanggal tertentu (seperti "hari ini" atau "kemarin"), jalankan query HANYA untuk tanggal tersebut. Jika hasilnya kosong, **DILARANG KERAS** mencari tanggal terakhir (MAX date) dan mengubah definisi waktu secara sepihak. Langsung beritahu user bahwa data untuk tanggal tersebut belum tersedia.

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
*(PENGECUALIAN MUTLAK: Aturan ini TIDAK BERLAKU jika akun user memiliki hak akses terbatas (RBAC) dan menanyakan topik/data di luar tabel yang diizinkan di atas. Jika data yang diminta berada di luar hak akses, LANGSUNG tolak secara sopan tanpa memanggil tool.)*

Jika Anda tidak yakin apakah pertanyaan berkaitan dengan bisnis → PANGGIL TOOL DULU, baru putuskan.
Jika Anda mendapat error database → JANGAN TOLAK, cari tabel yang benar dengan `search_schema`.
Jika schema salah → GUNAKAN `get_database_schema_info` untuk mendapat schema yang benar.

## 🔴 ATURAN COUNTING — WAJIB UNTUK SEMUA QUERY COUNT

Saat user bertanya **"berapa", "total", "jumlah"** entitas (cabang, dealer, pelanggan, produk, dll):

1. **Gunakan `COUNT(*)` langsung pada tabel/view master yang relevan**.
2. **DILARANG berasumsi atau menyisipkan filter `WHERE is_active = true` atau `WHERE status = 'aktif'`** KECUALI user secara eksplisit menyebutkan kata "aktif" (misal: "cabang aktif", "pelanggan yang aktif") DAN kolom tersebut benar-benar terbukti ada di tabel.
3. **JANGAN gunakan** `COUNT(nama_kolom)` karena akan melewati baris NULL — selalu `COUNT(*)`.
4. Jika user hanya bertanya "berapa total cabang" atau "jumlah cabang", hitung seluruh baris dengan `SELECT COUNT(*) FROM schema.tabel` tanpa filter WHERE status buatan.

## 🔴 ATURAN DAFTAR & RINCIAN — WAJIB UNTUK "TAMPILKAN", "DAFTAR", "LIST"

Jika user menggunakan kata kerja **"tampilkan"**, **"daftar"**, **"list"**, atau **"rincian"** [entitas] (contoh: "tampilkan cabang", "daftar dealer", "rincian penjualan") **DAN TIDAK MENYEBUT KATA "GRAFIK" ATAU "CHART"**:

1. **WAJIB** sajikan data dalam bentuk **smart_table** yang berisi baris detail (BUKAN agregasi).
2. **LANGSUNG QUERY PADA VIEW MASTER**: Untuk entitas master umum (cabang, dealer, customer, produk), **LANGSUNG jalankan `execute_query`** (misal: `SELECT * FROM sch_mbi.view_master_cabang_mbi`) tanpa berputar-putar melakukan describe/probe terlebih dahulu.
3. **KONSISTENSI ANGKA RINGKASAN EKSEKUTIF (MUTLAK)**:
   - Angka total di dalam **Ringkasan Eksekutif WAJIB PERSIS SAMA** dengan jumlah baris yang dikembalikan oleh `execute_query` (`rows_returned` / total baris tabel).
   - Contoh: Jika query cabang mengembalikan 95 baris, Ringkasan Eksekutif WAJIB menyebut: "**Saat ini terdapat total 95 cabang yang terdaftar di dalam sistem perusahaan.**"
   - **DILARANG KERAS** menghitung manual sampel yang terlihat atau menyebut angka lain (seperti 22) yang berbeda dari total baris hasil query.
4. **DILARANG KERAS MENYISIPKAN FILTER STATUS (`WHERE is_active = ...`)** secara sembarangan jika user tidak memintanya. Ambil semua baris data yang ada di tabel master.
5. **DILARANG** melakukan `GROUP BY` atau agregasi summary jika user meminta detail/daftar.
6. **CHART PROHIBITION (MUTLAK)**: **DILARANG KERAS** menampilkan blok `chart` untuk permintaan daftar/tampilkan murni. User ingin melihat data, bukan grafik.
7. ⚠️ **LARANGAN LIMIT/OFFSET (MUTLAK)**: **DILARANG KERAS** menggunakan klausa `LIMIT` atau `OFFSET` dalam query (contoh: `LIMIT 50`, `LIMIT 100`, dll) untuk permintaan menampilkan data. Biarkan query mengembalikan **SELURUH baris data secara 100% utuh**. Sistem frontend sudah otomatis menangani pagination dan ekspor data besar.

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

## 🔴 ATURAN TERPENTING #1 — JANGAN TEBAK NAMA KOLOM

Kata bisnis dari user ("HPP", "netto", "diskon", "profit", "omzet") adalah **ISTILAH BISNIS**, BUKAN nama kolom database.

Sebelum `execute_query`, **WAJIB** panggil `describe_table` untuk mendapatkan nama kolom EKSAK.

**Checkpoint wajib sebelum tulis query**: *"Setiap nama kolom yang saya gunakan, apakah berasal dari hasil describe_table tadi?"*
- YA → lanjut execute_query
- TIDAK / RAGU → panggil describe_table dulu, baru execute_query

**DILARANG KERAS** menebak nama kolom apapun sebelum memanggil describe_table. Ini berlaku mutlak untuk kolom harga, qty, diskon, tanggal, status, dan semua kolom lainnya.

{$regionalSection}

{$metricSection}


## 🔴 FORMAT PENYAJIAN DATA (CALL #1 GUIDELINE)
1. **Data Tabular**: Jika kueri mengembalikan data berbaris/tabel (≥ 2 baris atau ≥ 2 kolom), sajikan data dengan Ringkasan Eksekutif, blok `smart_table` singkat, dan insight bisnis.
2. ⛔ **Larangan Mutlak Angka Tunggal**: Jika hasil kueri hanya 1 baris DAN 1 kolom (misal `COUNT(*) = 6` atau `SUM = Rp 500.000`), DILARANG KERAS membuat smart_table. Sajikan angka tersebut langsung di dalam narasi kalimat.
3. Seluruh detail format smart_table, currency_columns, insight strategis, dan rekomendasi prompt akan diatur secara lengkap dan otomatis pada tahap penyajian akhir.

## TOOLS TERSEDIA
1. `execute_query` — Eksekusi SQL SELECT query untuk mengambil data. Gunakan secara instan (Single-Shot) untuk menjawab pertanyaan bisnis atau mengambil daftar data master.
2. `describe_table` — Dapatkan struktur kolom tabel/view jika diperlukan untuk memverifikasi kolom.
3. `search_schema` — Cari nama tabel/kolom yang tidak terdaftar di prompt berdasarkan 1 kata kunci.
4. `get_erp_guidance` / `get_erp_menu_navigation` / `fetch_erp_guidance_from_web` — Panduan ERP.
5. `web_search` — Melakukan pencarian informasi eksternal terkini di internet (Web Search) menggunakan SearXNG.

{$erpSection}

## 🔴 PROTOKOL RECOVERY — WAJIB JIKA search_schema TIDAK MENEMUKAN HASIL

Jika `search_schema` mengembalikan hasil kosong atau tidak relevan, **JANGAN menyerah dan JANGAN tanya user**. Lakukan langkah berikut secara berurutan:

1. **Coba keyword alternatif** yang lebih umum atau sinonim:
   - "baterai" tidak ada hasil → coba "barang", "item", "sparepart"
   - "penjualan" tidak ada hasil → coba "jual", "transaksi", "order"
   - "cabang" tidak ada hasil → coba "dealer", "toko", "outlet"

2. **Jangan pernah menyimpulkan "data tidak ada"** hanya karena `search_schema` kosong. Data bisa ada di tabel dengan nama yang tidak mengandung kata kunci pencarian.

## PROTOKOL URUTAN LANGKAH (FAST-PATH SINGLE-SHOT)

1. **JIKA TABEL SUDAH JELAS DARI DAFTAR DI PROMPT (Cabang, Customer, Dealer, Penjualan, Stok, dll):**
   - **LANGSUNG PANGGIL `execute_query`** (1-Shot Instant Execution).
   - **LEWATI** `get_database_schema_info`, `search_schema`, `describe_table`, dan `probe query`.
   - Jalankan query utama langsung (contoh: `SELECT * FROM sch_mbi.view_master_cabang_mbi` untuk cabang).
   - Langsung sajikan hasil ke user.

2. **JIKA TABEL BELUM JELAS / PERTANYAAN KOMPLEKS:**
   - Ambil tabel relevan dari `get_database_schema_info` atau `search_schema` (max 1x).
   - Panggil `describe_table` untuk melihat kolom.
   - Jalankan `execute_query` final.

3. Sajikan hasil dengan format: Ringkasan Eksekutif + **smart_table** (jika data tabular) + Insight Bisnis.

**ATURAN PROBE QUERY — KRITIS:**
- **Maksimal 1 probe** per kolom yang ingin diketahui nilainya
- Setelah 1 probe mendapat nilai → **LANGSUNG ke query final**, tidak ada probe lagi
- DILARANG probe `nama_cabang` jika user bertanya tentang wilayah (gunakan kolom propinsi/kabupaten)
- DILARANG probe untuk memverifikasi hasil probe sebelumnya

## 🔴 PROTOKOL KHUSUS: FILTER NILAI KOLOM PADA VIEW

- ❌ SALAH: `SELECT DISTINCT kolom_wilayah WHERE ILIKE '%medan%'` → kemungkinan besar KOSONG karena nilai aslinya mungkin berbeda (misal: 'SUMATERA UTARA').
- ✅ BENAR: `SELECT DISTINCT kolom_wilayah FROM schema_name.table_name LIMIT 20` → tampil semua nilai wilayah yang tersedia.
- ✅ BENAR: Dari hasil terlihat nilai eksak → gunakan nilai tersebut di query utama.

**WAJIB LAKUKAN**: Eksekusi query probe TANPA FILTER untuk mendapatkan semua nilai valid (jika tabel/view master tersedia, seperti `view_master_cabang_mbi` untuk wilayah/cabang, **WAJIB** query ke tabel master tersebut demi performa dan menghindari disk full error):
```sql
SELECT DISTINCT nama_kolom_yang_dibutuhkan
FROM schema_name.nama_master_tabel_atau_view -- Prioritaskan tabel master (contoh: view_master_cabang_mbi)
LIMIT 20
```
Kemudian cocokkan nilai EKSAK dari hasil dengan kata kunci user, lalu gunakan di query utama dengan `=` (bukan ILIKE).


Contoh nyata — user tanya "cabang di Medan":
- ❌ SALAH: `SELECT DISTINCT nama_propinsi_cabang WHERE ILIKE '%medan%'` → hasilnya KOSONG karena nilai aslinya `'SUMATERA UTARA'`
- ✅ BENAR: `SELECT DISTINCT nama_propinsi_cabang FROM sch_mbi.view_... LIMIT 20` → tampil semua propinsi
- ✅ BENAR: Dari hasil terlihat `'SUMATERA UTARA'` → query utama: `WHERE nama_propinsi_cabang = 'SUMATERA UTARA'`

{$productSection}

## 🔴 ATURAN FILTER TANGGAL — WAJIB DIIKUTI
## 🔴 ATURAN FILTER TANGGAL — WAJIB DIIKUTI

**Jika user TIDAK menyebut periode/tanggal/bulan/tahun secara eksplisit:**
- **JANGAN tambahkan filter tanggal apapun** ke dalam query
- Biarkan query mengambil seluruh data yang tersedia di database tanpa batasan waktu
- Contoh pertanyaan TANPA periode: "produk terlaris", "cabang terbaik", "total penjualan" → **TANPA WHERE tanggal**

**Jika user menyebut periode secara eksplisit:**
- Gunakan filter tanggal sesuai yang diminta
- Contoh: "penjualan bulan Maret [Tahun]" → `WHERE tgl_... BETWEEN '[Tahun]-03-01' AND '[Tahun]-03-31'`
- Contoh: "data tahun [Tahun]" → `WHERE tgl_... BETWEEN '[Tahun]-01-01' AND '[Tahun]-12-31'`
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
- **PostgreSQL**: prefix wajib `schema_name.table_name` (contoh: `sales.transactions`)
- **MySQL/MariaDB**: JANGAN pakai prefix schema — cukup `table_name` saja.
- **POSTGRESQL STRUCTURE (CRITICAL)**: Hanya gunakan format 2 level: `schema.table`. **DILARANG KERAS** menggunakan format 3 level seperti `schema.table.column` di dalam klausa `FROM` atau `JOIN`. Kolom hanya boleh diletakkan di `SELECT`, `WHERE`, `GROUP BY`, dll.
- Cara mengetahui driver database: lihat info di bagian "DATABASE TERSEDIA" di atas — tercantum driver-nya.
- SELECT only — no INSERT/UPDATE/DELETE/DROP
- Filter tanggal: BETWEEN pada kolom DATE/TIMESTAMP dari describe_table
- Pencarian teks: `kolom ILIKE '%kata1%' AND kolom ILIKE '%kata2%'` (PostgreSQL) atau `kolom LIKE '%kata1%' AND kolom LIKE '%kata2%'` (MySQL)
- Alias: Title Case `AS "Total Penjualan Bersih"`
- Pembulatan: `ROUND(SUM(kolom), 0)`
- Ikuti `MANDATORY_AI_ACTION` dari tool hasil jika ada

## IDENTIFIKASI MATA UANG (KRITIS)
- **WAJIB**: Pastikan **SEMUA** kolom yang berisi nilai uang (netto, hpp, omset, revenue, profit, diskon, biaya, dll) masuk ke dalam array `currency_columns` di tool `execute_query` DAN blok ```smart_table```.
- **JANGAN MALAS**: Jika ada 5 kolom uang, masukkan kelimanya ke `currency_columns`. JANGAN hanya masukkan satu kolom saja.
- Gunakan "Rp" dalam narasi teks.

## ATURAN NOMINAL & DESIMAL (SANGAT KRITIS)
- Untuk semua nilai uang/mata uang, gunakan angka murni tanpa pemisah ribuan dan tanpa singkatan (K/jt/M/rb).
- **BILANGAN BERKOMA**: Jika data memiliki angka desimal/berkoma (misal di kolom HPP atau Kurs), **TETAP SERTAKAN** desimal tersebut menggunakan titik (.) sebagai pemisah desimal agar data tetap akurat. Namun, sistem frontend akan memformatnya dengan cara **MEMBULATKAN** ke angka terdekat tanpa desimal (tanpa koma) untuk tampilan yang lebih rapi.
- Contoh: `3103569312.13` (akan tampil Rp 3.103.569.312).

## PROTOKOL TIMEOUT & HASIL KOSONG
Jika `get_column_values` error/timeout → skip, lanjut ke describe_table.
Jika `execute_query` timeout, 0 rows, atau error database/kolom:
1. JANGAN simpulkan "data tidak ada" dan JANGAN beritahu user tentang detail error teknis (seperti "column does not exist") di awal.
2. WAJIB panggil `describe_table` → perbaiki query (pastikan nama kolom benar, filter tanggal tepat, dan alias sesuai) → retry query.
3. Ulangi proses ini (debug & retry) minimal 3 kali dengan strategi berbeda sebelum akhirnya melapor kendala teknis kepada user.

## 🔴 ATURAN LIMIT QUERY — WAJIB DIIKUTI (REVISI)

**1. Aturan "TAMPILKAN SEMUA" (Prioritas Tertinggi):**
- Untuk pertanyaan "tampilkan data", "lihat data", "rekap", "semua", serta pertanyaan deskriptif tanpa agregasi → **DILARANG KERAS menggunakan LIMIT**.
- Tampilkan SEMUA data secara utuh. Sistem sudah dioptimasi untuk menangani ribuan baris.
- **DILARANG** menggunakan `LIMIT 100` atau angka pengaman lainnya secara diam-diam.

**2. Aturan default jika user tidak menyebut jumlah spesifik:**
- Hanya untuk pertanyaan perbandingan ("terlaris", "terpopuler", "terbaik", "terburuk") → gunakan `LIMIT 10`.

**3. Aturan jika user menyebut angka spesifik:**
- "top 5" → `LIMIT 5`
- "20 terlaris" → `LIMIT 20`
- "tampilkan 200" → `LIMIT 200`
- Ikuti persis angka user.

**Aturan presentasi hasil:**
- Jika hasil query LEBIH SEDIKIT dari LIMIT yang diminta → tampilkan semua yang ada, sebutkan di Ringkasan Eksekutif: "Hanya ditemukan X data di database."
- JANGAN menyebut "10 produk terlaris" jika LIMIT-nya 10 dan data hanya ada 5 — katakan "Seluruh 5 produk yang tersedia"
- Jika user minta top 10 tapi data hanya 5, tampilkan 5 dan jelaskan bahwa hanya ada 5 data

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
     * STRATEGY 2: Slim Summarization System Prompt (~650 tokens)
     * Digunakan pada Call #2+ setelah query/terminal tool sukses dieksekusi.
     * Mengeliminasi 18.000+ token aturan SQL dan daftar tabel yang sudah tidak dibutuhkan
     * pada tahap narasi dan penyajian data akhir.
     */
    private function buildSummarizationSystemPrompt(string $detectedLanguage = 'id', bool $scopeLimited = true, string $userMessage = ''): string
    {
        $isChartQuery = !empty($userMessage) && (bool) preg_match('/\b(grafik|chart|tren|trend|visual|diagram|plot|per bulan|per tahun|bulanan|tahunan|pertumbuhan|banding)\b/i', $userMessage);

        $chartInstruction = "";
        if ($isChartQuery) {
            $chartInstruction = <<<CHART

## 🔴 FORMAT GRAFIK (CHART)
Sertakan blok ```chart``` tepat sebelum atau sesudah smart_table:
```chart
{"type":"bar","title":"Judul Grafik","data":{"labels":["Label1","Label2"],"datasets":[{"label":"Dataset","data":[100,200]}]}}
```
- Gunakan "line" untuk tren waktu, "bar" untuk perbandingan antar cabang/kategori, "pie" untuk proporsi.
CHART;
        }

        if ($detectedLanguage === 'id') {
            return <<<PROMPT
Anda adalah DataBot, Data Analyst dan Business Advisor profesional untuk perusahaan.
Tugas Anda saat ini adalah menyajikan hasil data bisnis yang telah diperoleh dari sistem ke dalam format laporan eksekutif bisnis yang rapi, elegan, dan mudah dipahami oleh Bapak/Ibu pimpinan.

## 🔴 PRINSIP KERAHASIAAN & ANTI-BOCOR (MUTLAK)
1. DILARANG KERAS membocorkan atau menyebutkan nama teknis database, nama teknis tabel (seperti v_ms_cabang, trm_faktur, dll), schema, nama kolom SQL fisik, atau sintaks SQL ke dalam teks jawaban.
2. Selalu terjemahkan ke bahasa bisnis alami yang formal (contoh: "Data Cabang", "Data Pelanggan", "Data Penjualan", "Data Barang").
3. DILARANG menyebutkan proses teknis internal seperti "query berhasil dijalankan", "data diambil dari view", "berdasarkan SQL SELECT", dll. Bersikaplah sebagai analis bisnis profesional yang langsung memaparkan data.

## 🔴 STRUKTUR FORMAT JAWABAN (WAJIB DIIKUTI SECARA BERURUTAN)

1. **Ringkasan Eksekutif**:
   Tuliskan 1-2 kalimat pengantar formal dan ringkas yang merangkum poin inti data (misal: "Saat ini terdapat total 6 cabang aktif yang terdaftar di dalam sistem perusahaan.").

2. **smart_table (Tabel Data Interaktif)**:
   - Jika data memiliki ≥ 2 baris atau ≥ 2 kolom, Anda WAJIB mencantumkan blok ```smart_table``` singkat tepat setelah Ringkasan Eksekutif:
   ```smart_table
   {
       "title": "Nama Judul Bisnis yang Rapi",
       "currency_columns": ["Kolom1", "Kolom2"]
   }
   ```
   - Masukkan seluruh nama kolom yang berisi nilai uang (seperti Netto, HPP, Total, Omset, Profit) ke dalam `currency_columns`.
   - DILARANG menggunakan tabel Markdown biasa (`| Kolom | Kolom |`).
   - Pengecualian mutlak: Jika hasil hanya angka tunggal (1 baris 1 kolom, misal COUNT = 6), JANGAN buat smart_table, cukup tulis langsung dalam narasi: "Total cabang saat ini adalah 6 cabang."
   - Untuk nilai mata uang, tuliskan angka nominal penuh tanpa pemisah ribuan (contoh: 1500000). Frontend akan otomatis memformatnya ke Rupiah.
{$chartInstruction}

3. **Insight Strategis Bisnis**:
   Berikan analisis bisnis mendalam minimal 3 poin bertanda emoji dengan angka konkret:
   📍 **Sebaran Wilayah / Cakupan**: [Analisis sebaran atau cakupan data secara komprehensif]
   🏢 **Kontributor Utama / Pusat Aktivitas**: [Analisis entitas terbesar, terpenting, atau paling dominan]
   📋 **Rekomendasi Operasional**: [Langkah tindak lanjut bisnis, efisiensi, atau evaluasi yang perlu dilakukan]

4. **Rekomendasi Prompt Selanjutnya**:
   Di bagian paling akhir, berikan 4 pilihan pertanyaan lanjutan spesifik dalam tanda kutip untuk eksplorasi lebih dalam:
   💡 **Rekomendasi Prompt Selanjutnya:**
   1. "[Pertanyaan lanjutan 1 yang relevan dengan data di atas]"
   2. "[Pertanyaan lanjutan 2 yang relevan dengan data di atas]"
   3. "[Pertanyaan lanjutan 3 yang relevan dengan data di atas]"
   4. "[Pertanyaan lanjutan 4 yang relevan dengan data di atas]"
   - DILARANG menambahkan keterangan dalam kurung `()` setelah tanda kutip.
PROMPT;
        }

        return <<<PROMPT
You are DataBot, a professional Data Analyst and Business Advisor for the company.
Your task is to present the business data in an executive, polished, and business-oriented format.

## 🔴 STRICT CONFIDENTIALITY & ANTI-LEAKAGE
1. NEVER reveal technical database names, table names, schema names, physical column names, or SQL queries in your response.
2. Always use natural business terms (e.g., "Branch Data", "Customer Data", "Sales Invoices").

## 🔴 MANDATORY RESPONSE STRUCTURE
1. **Executive Summary**: 1-2 concise, formal sentences summarizing key findings.
2. **smart_table**: Brief smart_table block with title and currency_columns.
{$chartInstruction}
3. **Strategic Business Insights**: At least 3 key business takeaways with emojis (📍, 🏢, 📋).
4. **Next Prompt Recommendations**: 4 specific follow-up questions.
PROMPT;
    }


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

                    if (!empty($params['headers']) && !empty($params['rows'])) {
                        return $matches[0];
                    }

                    $toolIdx = isset($params['tool_index']) ? (int) $params['tool_index'] : -1;

                    $toolRes = null;
                    if ($toolIdx >= 0 && !empty($toolResults[$toolIdx])) {
                        $toolRes = $toolResults[$toolIdx];
                    } else {
                        // First pass: prioritize non-probe results
                        foreach (array_reverse($toolResults) as $tr) {
                            $isProbe = $tr['is_probe'] ?? false;
                            if ($isProbe) continue;

                            $d = $tr['data'] ?? null;
                            if (is_array($d) && !empty($d['rows']) && !empty($d['columns'])) {
                                $toolRes = $tr;
                                break;
                            }
                        }

                        // Second pass: fallback to any result if no non-probe result found
                        if (!$toolRes) {
                            foreach (array_reverse($toolResults) as $tr) {
                                $d = $tr['data'] ?? null;
                                if (is_array($d) && !empty($d['rows']) && !empty($d['columns'])) {
                                    $toolRes = $tr;
                                    break;
                                }
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
                    $rawRows = $tableData['rows'] ?? [];
                    $columns = $tableData['columns'] ?? [];

                    if (empty($rawRows) || empty($columns))
                        return $matches[0];

                    $monthMap = [
                        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
                        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
                    ];

                    $isVerticalPeriod = false;
                    // Detect if query returned vertical columns: [Tahun, Bulan, Total Netto] or similar
                    if (count($columns) === 3 && preg_match('/tahun|year/i', $columns[0]) && preg_match('/bulan|month/i', $columns[1])) {
                        $isVerticalPeriod = true;
                    }

                    if ($isVerticalPeriod) {
                        $yearCol = $columns[0];
                        $monthCol = $columns[1];
                        $valCol = $columns[2];

                        $pivotData = [];
                        $allYears = [];
                        $allMonths = [];

                        foreach ($rawRows as $row) {
                            $y = (string) ($row[$yearCol] ?? '');
                            $m = (int) ($row[$monthCol] ?? 0);
                            $v = is_numeric($row[$valCol] ?? null) ? (float) $row[$valCol] : 0;

                            if (!in_array($y, $allYears)) $allYears[] = $y;
                            if ($m > 0 && !in_array($m, $allMonths)) $allMonths[] = $m;

                            if (!isset($pivotData[$m])) $pivotData[$m] = [];
                            $pivotData[$m][$y] = $v;
                        }
                        sort($allMonths);
                        sort($allYears);

                        $newHeaders = ['Bulan'];
                        $newCurrencyCols = [];
                        foreach ($allYears as $y) {
                            $colName = 'Penjualan ' . $y;
                            $newHeaders[] = $colName;
                            $newCurrencyCols[] = $colName;
                        }

                        $normalizedRows = [];
                        foreach ($allMonths as $m) {
                            $rowArr = [$monthMap[$m] ?? (string) $m];
                            foreach ($allYears as $y) {
                                $rowArr[] = $pivotData[$m][$y] ?? 0;
                            }
                            $normalizedRows[] = $rowArr;
                        }

                        $newParams = $params;
                        $newParams['headers'] = $newHeaders;
                        $newParams['rows'] = $normalizedRows;
                        $newParams['currency_columns'] = $newCurrencyCols;
                    } else {
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
                        if (empty($newParams['currency_columns']) && !empty($toolRes['currency_columns'])) {
                            $newParams['currency_columns'] = $toolRes['currency_columns'];
                        }
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

    private function injectChartDataIntoContent(string $content, array $toolResults): string
    {
        if (empty($toolResults) || strpos($content, 'chart') === false) {
            return $content;
        }

        $content = preg_replace_callback(
            '/```chart\s*([\s\S]*?)```/m',
            function (array $matches) use ($toolResults) {
                $rawJson = trim($matches[1]);
                if (empty($rawJson)) return $matches[0];

                try {
                    $config = json_decode($rawJson, true);
                    if (!is_array($config)) return $matches[0];

                    $toolIdx = isset($config['tool_index']) ? (int) $config['tool_index'] : -1;
                    $toolRes = null;
                    if ($toolIdx >= 0 && !empty($toolResults[$toolIdx])) {
                        $toolRes = $toolResults[$toolIdx];
                    } else {
                        // Find latest execute_query tool result
                        foreach (array_reverse($toolResults) as $tr) {
                            if (!empty($tr['is_probe'])) continue;
                            $d = $tr['data'] ?? null;
                            if (is_array($d) && !empty($d['rows']) && !empty($d['columns'])) {
                                $toolRes = $tr;
                                break;
                            }
                        }
                    }

                    if (!$toolRes) return $matches[0];

                    $dataObj = $toolRes['data'] ?? null;
                    if (is_string($dataObj)) $dataObj = json_decode($dataObj, true) ?: null;
                    if (!is_array($dataObj) || empty($dataObj['rows']) || empty($dataObj['columns'])) {
                        return $matches[0];
                    }

                    $rows = $dataObj['rows'];
                    $cols = $dataObj['columns'];
                    if (count($cols) < 2) return $matches[0];

                    // Month mapping
                    $monthMap = [
                        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
                        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
                    ];

                    $currentMonth = (int) date('n'); // e.g. 8 for August
                    $currentYear = (string) date('Y'); // e.g. '2026'

                    $isVerticalPeriod = false;
                    // Detect if query returned vertical columns: [Tahun, Bulan, Total Netto] or similar
                    if (count($cols) === 3 && preg_match('/tahun|year/i', $cols[0]) && preg_match('/bulan|month/i', $cols[1])) {
                        $isVerticalPeriod = true;
                    }

                    if ($isVerticalPeriod) {
                        $yearCol = $cols[0];
                        $monthCol = $cols[1];
                        $valCol = $cols[2];

                        $pivotData = []; // [year => [month => val]]
                        $allYears = [];
                        $allMonths = [];

                        foreach ($rows as $row) {
                            $y = (string) ($row[$yearCol] ?? '');
                            $m = (int) ($row[$monthCol] ?? 0);
                            $v = is_numeric($row[$valCol] ?? null) ? (float) $row[$valCol] : 0;

                            if (!in_array($y, $allYears)) $allYears[] = $y;
                            if ($m > 0 && !in_array($m, $allMonths)) $allMonths[] = $m;

                            if (!isset($pivotData[$y])) $pivotData[$y] = [];
                            $pivotData[$y][$m] = $v;
                        }
                        sort($allMonths);
                        sort($allYears);

                        $labels = [];
                        foreach ($allMonths as $m) {
                            $labels[] = $monthMap[$m] ?? (string) $m;
                        }

                        $datasets = [];
                        foreach ($allYears as $y) {
                            $seriesValues = [];
                            $isCurrentYear = ($y === $currentYear);

                            foreach ($allMonths as $m) {
                                $v = $pivotData[$y][$m] ?? null;
                                if ($isCurrentYear && $m > $currentMonth && ($v === 0.0 || $v === null)) {
                                    $v = null;
                                }
                                $seriesValues[] = $v;
                            }

                            $datasets[] = [
                                'label' => 'Penjualan ' . $y,
                                'data' => $seriesValues
                            ];
                        }
                    } else {
                        $labelCol = $cols[0];
                        $valCols = array_slice($cols, 1);

                        $labels = [];
                        foreach ($rows as $row) {
                            $rawLabel = is_array($row) ? ($row[$labelCol] ?? ($row[0] ?? '')) : '';
                            if (is_numeric($rawLabel) && isset($monthMap[(int)$rawLabel])) {
                                $labels[] = $monthMap[(int)$rawLabel];
                            } else {
                                $labels[] = (string) $rawLabel;
                            }
                        }

                        $datasets = [];
                        foreach ($valCols as $colIndex => $colName) {
                            $seriesValues = [];
                            $isCurrentYearSeries = str_contains((string) $colName, $currentYear);

                            foreach ($rows as $rIdx => $row) {
                                $rawVal = is_array($row) ? ($row[$colName] ?? ($row[$colIndex + 1] ?? null)) : null;
                                $val = is_numeric($rawVal) ? (float) $rawVal : null;

                                // If this series is current year and month is past current month and value is 0 or null -> null
                                $monthNum = $rIdx + 1;
                                if ($isCurrentYearSeries && $monthNum > $currentMonth && ($val === 0.0 || $val === null)) {
                                    $val = null;
                                }

                                $seriesValues[] = $val;
                            }

                            $datasets[] = [
                                'label' => (string) $colName,
                                'data' => $seriesValues
                            ];
                        }
                    }

                    // Reconstruct config data perfectly matching DB query
                    $type = $config['type'] ?? 'line';
                    $title = $config['title'] ?? ($toolRes['label'] ?? 'Grafik Perbandingan Penjualan');

                    $newConfig = [
                        'type' => $type,
                        'title' => $title,
                        'data' => [
                            'labels' => $labels,
                            'datasets' => $datasets
                        ]
                    ];

                    $newJson = json_encode($newConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    Log::info('[ChartInject] Injected ' . count($datasets) . ' datasets with ' . count($labels) . ' points from DB query into chart block');
                    return "```chart\n" . $newJson . "\n```";

                } catch (\Throwable $e) {
                    Log::warning('[ChartInject] Failed: ' . $e->getMessage());
                    return $matches[0];
                }
            },
            $content
        );

        return $content;
    }

    private function processContentForCharts(string $content, array $toolResults): string
    {
        if (strpos($content, '|') !== false) {
            $lines = explode("\n", $content);
            $newLines = [];
            $currentTable = [];
            $isInsideTable = false;

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^\|.*\|$/', $trimmed)) {
                    $isInsideTable = true;
                    $currentTable[] = $trimmed;
                } else {
                    if ($isInsideTable) {
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

        if (strpos($content, '"headers"') !== false && strpos($content, '"rows"') !== false) {
            if (strpos($content, '```smart_table') === false) {
                $content = preg_replace_callback('/\{\s*"title":\s*".*?"\s*,\s*"headers":\s*\[.*?\].*?\}/s', function ($matches) {
                    return "```smart_table\n" . $matches[0] . "\n```";
                }, $content);
            }
        }

        // Repair truncated chart/smart_table JSON blocks (fix missing closing braces/brackets)
        $content = preg_replace_callback('/```(chart|smart_table)\s*\n([\s\S]*?)```/m', function ($matches) {
            $blockType = $matches[1];
            $jsonStr = trim($matches[2]);
            if (empty($jsonStr)) return $matches[0];

            // Check if JSON is already valid
            $decoded = json_decode($jsonStr);
            if ($decoded !== null) return $matches[0]; // Already valid

            // Attempt repair: count opening vs closing braces/brackets and append missing closers
            $repaired = $this->repairJsonBraces($jsonStr);
            $decoded = json_decode($repaired);
            if ($decoded !== null) {
                Log::info("[ChartRepair] Fixed truncated {$blockType} JSON (appended missing closers)");
                return "```{$blockType}\n{$repaired}\n```";
            }

            // If repair failed, return original
            Log::warning("[ChartRepair] Failed to repair {$blockType} JSON: " . substr($jsonStr, -50));
            return $matches[0];
        }, $content);

        return $content;
    }

    /**
     * Repair truncated JSON by appending missing closing braces/brackets.
     * Handles strings (including escaped quotes) correctly.
     */
    private function repairJsonBraces(string $jsonStr): string
    {
        $inString = false;
        $escape = false;
        $stack = [];
        $pairs = ['{' => '}', '[' => ']'];
        $closers = ['}' => true, ']' => true];

        for ($i = 0; $i < strlen($jsonStr); $i++) {
            $ch = $jsonStr[$i];
            if ($escape) { $escape = false; continue; }
            if ($ch === '\\' && $inString) { $escape = true; continue; }
            if ($ch === '"') { $inString = !$inString; continue; }
            if ($inString) continue;
            if (isset($pairs[$ch])) {
                $stack[] = $pairs[$ch];
            } elseif (isset($closers[$ch])) {
                if (!empty($stack) && end($stack) === $ch) {
                    array_pop($stack);
                }
            }
        }

        // Append missing closers in reverse order
        return $jsonStr . implode('', array_reverse($stack));
    }

    /**
     * Otomatis sinkronisasi angka total di Ringkasan Eksekutif dengan jumlah baris nyata dari query detail.
     */
    private function synchronizeExecutiveSummaryWithTableData(string $content, array $toolResults): string
    {
        if (empty($toolResults)) {
            return $content;
        }

        // Cari hasil execute_query yang mengembalikan baris data list/detail
        $totalRows = null;
        foreach (array_reverse($toolResults) as $tr) {
            $d = $tr['data'] ?? null;
            if (is_array($d) && !empty($d['rows']) && is_array($d['rows'])) {
                $count = count($d['rows']);
                // Hanya sinkronkan jika data detail (bukan 1 baris agregasi)
                if ($count > 1) {
                    $totalRows = $count;
                    break;
                }
            }
        }

        if ($totalRows === null) {
            return $content;
        }

        // Koreksi pola umum seperti: "terdapat [total] [**]X[**] cabang", "memiliki [total] [**]X[**] cabang", dll.
        $patterns = [
            '/(terdapat\s+(?:total\s+)?(?:\*\*)?)\d+((?:\*\*)?\s+(?:cabang|dealer|pelanggan|customer|produk|barang|outlet|unit|data))/i',
            '/(memiliki\s+(?:total\s+)?(?:\*\*)?)\d+((?:\*\*)?\s+(?:cabang|dealer|pelanggan|customer|produk|barang|outlet|unit|data))/i',
            '/(total\s+(?:\*\*)?)\d+((?:\*\*)?\s+(?:cabang|dealer|pelanggan|customer|produk|barang|outlet|unit)\s+yang)/i',
            '/(sebanyak\s+(?:\*\*)?)\d+((?:\*\*)?\s+(?:cabang|dealer|pelanggan|customer|produk|barang|outlet|unit))/i',
            '/(currently\s+(?:has\s+)?(?:a\s+total\s+of\s+)?(?:\*\*)?)\d+((?:\*\*)?\s+(?:active\s+)?(?:branches|dealers|customers|products|items))/i',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace_callback($pattern, function ($m) use ($totalRows) {
                return $m[1] . $totalRows . $m[2];
            }, $content, 1);
        }

        return $content;
    }

    /**
     * Otomatis format angka rupiah mentah (contoh: Rp 4004147475) menjadi berpemisah ribuan (Rp 4.004.147.475).
     */
    private function formatRupiahNumbersInText(string $content): string
    {
        return preg_replace_callback('/\b(Rp\.?|IDR)\s*([0-9]{4,})(?:\.([0-9]{1,2}))?\b/i', function ($m) {
            $prefix = 'Rp ';
            $integerPart = number_format((float) $m[2], 0, ',', '.');
            if (!empty($m[3]) && (int)$m[3] > 0) {
                return $prefix . $integerPart . ',' . $m[3];
            }
            return $prefix . $integerPart;
        }, $content);
    }

    private function extractOriginalUserMessage(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $message = $messages[$i] ?? [];
            if (($message['role'] ?? '') !== 'user') {
                continue;
            }

            $content = (string) ($message['content'] ?? '');
            if ($content === '' || str_starts_with($content, '[')) {
                continue;
            }

            return $content;
        }

        return '';
    }

    private function enforceNumberedBranchIntent(string $userMessage, string $toolName, array $arguments): array
    {
        if ($toolName !== 'execute_query' || empty($arguments['sql'])) {
            return $arguments;
        }

        $intent = $this->extractNumberedBranchIntent($userMessage);
        if (!$intent) {
            return $arguments;
        }

        $sql = $arguments['sql'];
        $originalSql = $sql;
        $branchName = preg_quote($intent['name'], '/');
        $branchNumber = preg_quote($intent['number'], '/');
        $exactBranch = strtoupper($intent['name'] . ' ' . $intent['number']);
        $exactSql = str_replace("'", "''", $exactBranch);

        if (preg_match('/\bnama_cabang\s+IN\s*\(([^)]*)\)/i', $sql, $m)) {
            $items = $m[1];
            if (preg_match("/'[^']*{$branchName}[^']*{$branchNumber}[^']*'/i", $items)) {
                $sql = preg_replace('/\bnama_cabang\s+IN\s*\(([^)]*)\)/i', "nama_cabang = '{$exactSql}'", $sql, 1);
            }
        }

        if (preg_match("/\bnama_cabang\s+ILIKE\s+'%{$branchName}%'/i", $sql) && !preg_match("/\bnama_cabang\s+ILIKE\s+'%{$branchNumber}%'/i", $sql)) {
            $sql = preg_replace(
                "/\bnama_cabang\s+ILIKE\s+'%{$branchName}%'/i",
                "nama_cabang ILIKE '%{$intent['name']}%' AND nama_cabang ILIKE '%{$intent['number']}%'",
                $sql,
                1
            );
        }

        if (preg_match('/\btahun\s+ini\b/i', $userMessage) && !preg_match('/\b(2|dua)\s+tahun\s+(terakhir|lalu|sebelumnya)\b/i', $userMessage)) {
            $currentYear = (int) date('Y');
            $sql = preg_replace('/EXTRACT\s*\(\s*YEAR\s+FROM\s+([^)]+)\)\s+IN\s*\(\s*\d{4}\s*,\s*\d{4}\s*\)/i', "EXTRACT(YEAR FROM $1) = {$currentYear}", $sql);
            $sql = preg_replace('/\bperiode_tahun\s+IN\s*\(\s*\d{4}\s*,\s*\d{4}\s*\)/i', "periode_tahun = {$currentYear}", $sql);
        }

        if ($sql !== $originalSql) {
            Log::info('[Agentic] Corrected numbered branch intent SQL for ' . $exactBranch);
            $arguments['sql'] = $sql;
            if (!empty($arguments['label'])) {
                $arguments['label'] = preg_replace('/binjai\s*\(gabungan\)|binjai\s*1,\s*binjai\s*2,\s*dan\s*binjai\s*3/i', $exactBranch, $arguments['label']);
            }
        }

        return $arguments;
    }

    private function extractNumberedBranchIntent(string $userMessage): ?array
    {
        $normalized = strtolower($userMessage);
        $normalized = preg_replace('/[^a-z0-9\s]/i', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        if (!preg_match('/\bcabang\s+([a-z][a-z\s]*?)\s+(\d+)\s+tahun\s+ini\b/i', $normalized, $m)) {
            return null;
        }

        $name = trim($m[1]);
        $number = trim($m[2]);
        if ($name === '' || $number === '') {
            return null;
        }

        return [
            'name' => $name,
            'number' => $number,
        ];
    }

    private function convertMarkdownToSmartTable(array $tableLines): string
    {
        if (count($tableLines) < 2)
            return implode("\n", $tableLines);

        $headers = [];
        $dataRows = [];
        $foundSeparator = false;

        foreach ($tableLines as $idx => $line) {
            $cols = explode('|', $line);
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
                if (count($cols) >= count($headers)) {
                    $dataRows[] = array_slice($cols, 0, count($headers));
                }
            }
        }

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

    // ─────────────────────────────────────────────────────────────────────────
    // PERUBAHAN 1: stripThinkingLeakage yang diperkuat
    // Sekarang juga membersihkan blok "Mapping kolom (internal):",
    // "Nama eksak ... ditemukan:", dan baris reasoning teknis lainnya
    // yang bocor ke output user saat streaming aktif.
    // ─────────────────────────────────────────────────────────────────────────
    private function stripThinkingLeakage(string $content): string
    {
        // ══════════════════════════════════════════════════════════════════════
        // LAPISAN NUCLEAR (JAMINAN MUTLAK) — Strip [SYSTEM...] & SYSTEM tag
        // Dijalankan PERTAMA KALI sebelum apapun, tidak ada yang lolos dari ini
        // ══════════════════════════════════════════════════════════════════════
        // Strip baris yang dimulai atau mengandung [SYSTEM ...] tag
        $content = preg_replace('/^[^\n]*\[SYSTEM[^\]]*\][^\n]*\n?/mi', '', $content);
        // Strip baris yang mengandung kata "SYSTEM REMINDER / FORMAT / CORRECTION / NOTE"
        $content = preg_replace('/^[^\n]*\bSYSTEM\s+(FORMAT|REMINDER|CORRECTION|NOTE)\b[^\n]*\n?/mi', '', $content);
        // Strip baris yang mengandung MANDATORY_AI_ACTION atau variasi lainnya
        $content = preg_replace('/^[^\n]*\bMANDATORY(_|\s)+(AI_ACTION|NEXT_STEP|SCHEMA_USAGE|ACTION|RESPONSE|INSIGHT|FORMAT)\b[^\n]*\n?/mi', '', $content);

        // ── Strip blok "Nama eksak ... ditemukan: ..." ────────────────────────
        $content = preg_replace('/Nama eksak [^\n]+ ditemukan[^\n]*\n?/i', '', $content);

        // ── Strip "Sekarang saya akan menjalankan query..." ───────────────────
        $content = preg_replace('/Sekarang saya akan menjalankan query[^\n]*\n?/i', '', $content);

        // ── Strip seluruh blok "Mapping kolom (internal):" beserta isinya ─────
        // Pola: baris yang dimulai "Mapping kolom" diikuti baris-baris non-kosong
        $content = preg_replace('/^Mapping kolom\s*\(internal\)[^\n]*\n(?:[^\n]+\n)*/mi', '', $content);

        // ── Strip checklist/audit headers
        $content = preg_replace('/^(?:#+|-|\*|•)?\s*(🔴|##|#)?\s*(kritis|periksa kembali|self-audit|checklist|pemeriksaan mandiri|evaluasi diri)[^\n]*\n?/mi', '', $content);

        $checklistSystemKeywords = [
            'sapaan', 'ringkasan', 'eksekutif', 'insight', 'prompt',
            'smart_table', 'chart', 'currency', 'nominal', 'echoing', 'tabel', 'kolom',
            'query', 'sql', 'limit', 'offset', 'aturan', 'bahasa', 'database', 'rupiah',
            'rp', 'format', 'koreksi', 'reminder', 'menyajikan', 'menyebut', 'mencantumkan',
            'menampilkan', 'kesimpulan', 'jawaban', 'output', 'respon', 'user', 'analisis',
            'desimal', 'historis', 'tanggal', 'kosong', 'tersedia'
        ];

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
            // ── Tambahan untuk leakage yang sering bocor ─────────────────────
            '/^mapping kolom/i',
            '/^nama eksak\b/i',
            '/^sekarang saya akan menjalankan/i',
            '/^saya akan mencari nama tabel/i',
            '/^saya akan memeriksa struktur tabel/i',
            '/^saya telah menemukan kolom/i',
            '/^berdasarkan hasil pencarian/i',
            '/^saya menyimpulkan bahwa/i',
            '/^probe query/i',
            '/^hasil probe/i',
            '/^query utama\s*:/i',
            '/\[SYSTEM[^\]]*\]/i',
            '/\bSYSTEM\s+(FORMAT|REMINDER|CORRECTION|NOTE)\b/i',
            '/\bMANDATORY(_|\s)+(AI_ACTION|NEXT_STEP|SCHEMA_USAGE|ACTION|RESPONSE|INSIGHT|FORMAT)\b/i',
            '/\bANALYST\s+NOTE\b/i',
            '/\bINTERNAL\s+ANALYST\s+NOTE\b/i',
            '/\bPERFORMANCE_NOTE\b/i',
            '/^hasil query hanya mengandung\s+satu\s+angka\s+tunggal/i',
            '/^query\s+count\s+menghasilkan/i',
            '/^format respons yang benar/i',
            '/^contoh\s+(benar|salah)\s*:/i',
            '/sebutkan\s+angka.*(langsung|kalimat|tebal)/i',
            '/insight\s+strategis.*\d+\-\d+\s+poin/i',
            '/rekomendasi\s+prompt.*\d+\-\d+\s+pertanyaan/i',
            '/jawab\s+langsung\s+dalam\s+narasi/i',
            '/^tulis\s+\d+\-\d+\s+kalimat/i',
            '/^tulis\s+\d+\-\d+\s+poin/i',
            '/pertanyaan\s+(lanjutan spesifik|analisis lebih dalam|tren atau perbandingan|cross-analysis)/i',
            '/anda\s+wajib\s+menyajikan\s+jawaban/i',
            '/mohon\s+berikan\s+jawaban\s+atau\s+panggil\s+tool/i',
            '/jangan\s+hanya\s+berpikir\s+tanpa\s+output/i',
            '/baru saja\s+(memperoleh data penting dari tool|mengirimkan query sql mentah)/i',
            '/jangan\s+melakukan\s+tool\s+call/i',
            '/segera\s+berikan\s+jawaban\s+akhir/i',
            '/gunakan\s+data\s+ini\s+untuk\s+menyusun/i',
            '/tidak\s+perlu\s+melakukan\s+query\s+tambahan/i',
            '/gunakan\s+contoh\s+data\s+ini/i',
            '/jika\s+describe_table\s+sudah/i',
            '/jika\s+ada\s+kolom\s+status\s+aktif/i',
            '/langkah\s+(wajib|berikutnya)/i',
            '/waJib\s+dibaca/i',
            // Baris berisi pemetaan kolom: "Netto = hrg_jual" atau "* Netto = `hrg_jual`"
            '/^\s*(netto|hpp|total\s+netto|total\s+hpp|diskon|profit)\s*=\s*[a-z_]+/i',
            '/^[\*\-]\s*(netto|hpp|total\s+netto|total\s+hpp|diskon|profit)\s*=/i',
            '/^[\*\-]\s*\w[\w\s]+\s*=\s*`[a-z][a-z0-9_]+`/i',
            // Format: "- `nama_kolom` = deskripsi" atau "`nama_kolom` = deskripsi"
            '/^[\*\-]\s*`[a-z][a-z0-9_]+`\s*=/i',
            '/^[\*\-]\s*`[a-z][a-z0-9_]+`\s*:/i',
            '/^`[a-z][a-z0-9_]+`\s*[=:]/i',
            // Baris penjelasan skala kolom: "(perlu dikali qty...)", "sudah total", "harga satuan"
            '/^\(perlu dikali/i',
            '/^sudah\s+(total|bersih|termasuk)/i',
            '/^harga\s+(jual|pokok|satuan)\s+/i',
            '/langsung\s+di[\-\s]?sum/i',
            // Pemetaan kolom teknis internal: hanya blokir format pemetaan eksplisit,
            // BUKAN narasi biasa yang menyebut nama kolom
            '/^`?(hrg_jual|hrg_pokok|total_netto|total_disc|qty_jual)`?\s*[=:]/i',
            '/^[\*\-]\s*`?(hrg_jual|hrg_pokok|total_netto|total_disc|qty_jual)`?\s*[=:]/i',
            '/=\s*`(hrg_jual|hrg_pokok|total_netto|total_disc|qty_jual)`/i',

            // ═══════════════════════════════════════════════════════════════════
            // ANTI SYSTEM-PROMPT REGURGITATION — menangkap AI yang mengulang
            // instruksi internal-nya ke user (sangat berbahaya)
            // ═══════════════════════════════════════════════════════════════════

            // Instruksi format output yang di-echo kembali
            '/selalu\s+awali\s+dengan\s+(ringkasan|executive)/i',
            '/selalu\s+mulai\s+dengan\s+(ringkasan|ring)/i',
            '/ingat\s*:\s*selalu\s+mulai\s+dengan/i',
            '/awali\s+dengan\s+ringkasan\s+eksekutif/i',
            '/^jika\s+data\s+adalah\s+angka\s+tunggal/i',
            '/angka\s+tunggal.*tulis\s+langsung\s+dalam\s+narasi/i',
            '/sertakan\s+ringkasan\s+eksekutif/i',
            '/sertakan\s+insight\s+strategis/i',
            '/sertakan\s+rekomendasi\s+prompt/i',
            '/sesuai\s+instruksi\.?$/i',
            '/akhiri\s+dengan\s+rekomendasi\s+prompt/i',
            '/gunakan\s+format\s+[\'"]?(chart|smart_table|smart table)[\'"]?/i',
            '/jika\s+data\s+adalah\s+grafik/i',
            '/WAJIB\s+menggunakan\s+angka\s+dan\s+analisis/i',
            '/JANGAN\s+mengarang\s+atau\s+membuat\s+kesimpulan/i',
            '/tanpa\s+dasar\s+data/i',

            // Pattern "WAJIB + aksi teknis" — khas instruksi, bukan narasi bisnis
            '/WAJIB\s+(mencantumkan|menyertakan|menggunakan|mengikuti|diikuti|dipanggil|memanggil|menampilkan)\b/i',
            '/DILARANG\s+(KERAS\s+)?(menampilkan|menggunakan|menulis|menambahkan|menyebut|tulis|tampil)/i',
            '/JANGAN\s+(gunakan|tambahkan|tulis|tampilkan|sebut|pernah|hanya)/i',

            // Kalimat instruksi internal yang khas system prompt
            '/^\s*❌\s*(DILARANG|SALAH)/i',
            '/^\s*✅\s*(WAJIB|BENAR)/i',
            '/ATURAN\s+(EMAS|WAJIB|KETAT|KRITIS)/i',
            '/PROTOKOL\s+(RECOVERY|URUTAN|KHUSUS|TIMEOUT)/i',
            '/INSTRUKSI\s+PERTAMA/i',
            '/CRITICAL\s+PRIORITY/i',
            '/MANDATORY\s+(RESPONSE|INSIGHT|FORMAT)/i',

            // AI menjelaskan format/tools internal
            '/^\s*tools?\s+tersedia/i',
            '/^\s*tools?\s+yang\s+(tersedia|digunakan)/i',
            '/gunakan\s+bahasa\s+yang\s+sesuai\s+dengan\s+user/i',
            '/gunakan\s+bahasa\s+indonesia\s+formal/i',
            '/Berpikirlah\s+secara\s+internal/i',
            '/sampaikan\s+HANYA\s+jawaban\s+bisnis/i',

            // AI mengulang instruksi format chart/table
            '/format\s+berikut.*smart_table/i',
            '/blok.*```smart_table/i',
            '/blok.*```chart/i',

            // Pattern system prompt: "Anda adalah DataBot"
            '/^Anda\s+adalah\s+(DataBot|Data\s*Bot|asisten\s+Data\s+Analyst)/i',
            '/^saya\s+adalah\s+(DataBot|Data\s*Bot|asisten\s+Data\s+Analyst)/i',

            // Pattern: AI sedang membacakan aturan-aturan
            '/^\s*\d+\.\s+`(get_database_schema_info|search_schema|describe_table|execute_query|get_column_values|get_view_definition|get_table_preview|get_erp_guidance|get_erp_menu_navigation|fetch_erp_guidance_from_web|web_search)`/i',

            // "SYSTEM FORMAT CORRECTION" / "SYSTEM FORMAT REMINDER" leakage
            '/\[SYSTEM\s+FORMAT\s+(CORRECTION|REMINDER)\]/i',
        ];

        $columnListPattern = '/(`[a-z][a-z0-9_]*`[,\s]*){4,}/i';

        $lines = explode("\n", $content);
        $cleanLines = [];
        $strippedCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $isThinkingLine = false;

            // Checklist question: bullet + 'apakah/sudahkah' anywhere + system keyword + ends with '?'
            // Syarat '?' wajib: checklist selalu berupa pertanyaan, tapi insight bisnis biasanya tidak.
            // Contoh BENAR diblokir: "- Untuk data historis, apakah Anda sudah menggunakan tanggal...?"
            // Contoh SALAH BLOKIR: "2. Perlu dikaji apakah penurunan ini... insight dari data cabang."
            if (!$isThinkingLine
                && substr($trimmed, -1) === '?'
                && preg_match('/^\s*(?:[\*\-•]|\d+\.)\s*.*?\b(apakah|sudahkah)\b/i', $trimmed)
            ) {
                foreach ($checklistSystemKeywords as $kw) {
                    if (stripos($trimmed, $kw) !== false) {
                        $isThinkingLine = true;
                        break;
                    }
                }
            }

            if (!$isThinkingLine) {
                foreach ($thinkingLinePatterns as $pattern) {
                    if (preg_match($pattern, $trimmed)) {
                        $isThinkingLine = true;
                        break;
                    }
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

    // ─────────────────────────────────────────────────────────────────────────
    // PERUBAHAN 2: Method baru isThinkingLeakageLine()
    // Digunakan oleh curlStreamSse untuk filter real-time per baris
    // SEBELUM chunk dikirim ke user, sehingga thinking tidak pernah tampil.
    // ─────────────────────────────────────────────────────────────────────────
    private function isThinkingLeakageLine(string $line): bool
    {
        $trimmed = trim($line);
        if (empty($trimmed))
            return false;

        // ══ NUCLEAR CHECK — JAMINAN MUTLAK, SELALU DIJALANKAN PERTAMA ══
        // Blokir SEMUA baris yang mengandung [SYSTEM...], SYSTEM REMINDER, MANDATORY, dll.
        // Tidak peduli kata lain di sekitarnya — ini tidak pernah boleh tampil ke user.
        if (
            preg_match('/\[SYSTEM[^\]]*\]/i', $trimmed) ||
            preg_match('/\bSYSTEM\s+(FORMAT|REMINDER|CORRECTION|NOTE)\b/i', $trimmed) ||
            preg_match('/\bMANDATORY(_|\s)+(AI_ACTION|NEXT_STEP|SCHEMA_USAGE|ACTION|RESPONSE|INSIGHT|FORMAT)\b/i', $trimmed) ||
            preg_match('/\bANALYST\s+NOTE\b/i', $trimmed) ||
            preg_match('/\bINTERNAL\s+ANALYST\s+NOTE\b/i', $trimmed) ||
            preg_match('/\bPERFORMANCE_NOTE\b/i', $trimmed)
        ) {
            Log::info('[StreamFilter][NUCLEAR] Blocked system tag line: ' . substr($trimmed, 0, 100));
            return true;
        }

        // 1. Block checklist headers, system alerts/notes, critical self-audit markers
        if (preg_match('/(kritis|periksa kembali|self-audit|checklist|🔴|pemeriksaan mandiri|evaluasi diri|catatan pengingat)/i', $trimmed)) {
            // But don't block normal Indonesian phrases unless they look like headers or contain critical symbols
            if (preg_match('/^(#+|-|\*|•)?\s*(🔴|##|#)?\s*(kritis|periksa kembali|self-audit|checklist|pemeriksaan mandiri|evaluasi diri)/i', $trimmed)) {
                Log::info('[StreamFilter] Blocked checklist/audit header: ' . substr($trimmed, 0, 100));
                return true;
            }
        }

        // 2. Block system reminder and internal alert prefixes
        if (
            preg_match('/\[SYSTEM[^\]]*\]/i', $trimmed) ||
            preg_match('/SYSTEM\s+(FORMAT|REMINDER|CORRECTION|NOTE)/i', $trimmed) ||
            preg_match('/MANDATORY(_|\s)+(AI_ACTION|NEXT_STEP|SCHEMA_USAGE|ACTION|RESPONSE|INSIGHT|FORMAT)/i', $trimmed) ||
            preg_match('/ANALYST\s+NOTE/i' , $trimmed) ||
            preg_match('/INTERNAL\s+ANALYST\s+NOTE/i', $trimmed) ||
            preg_match('/PERFORMANCE_NOTE/i', $trimmed)
        ) {
            Log::info('[StreamFilter] Blocked system reminder prefix: ' . substr($trimmed, 0, 100));
            return true;
        }

        // 3. Block checklist questions using a keyword matrix.
        // Untuk baris yang DIMULAI dengan bullet + apakah/sudahkah:
        //   → Wajib diakhiri '?' karena checklist = pertanyaan, insight bisnis = tidak.
        // Untuk baris yang LANGSUNG dimulai apakah/sudahkah (tanpa bullet):
        //   → Cukup cek keyword matrix (tidak butuh '?' karena konteks sudah jelas).
        $isBulletWithApakah = substr($trimmed, -1) === '?'
            && preg_match('/^\s*(?:[\*\-•]|\d+\.)\s*.*?\b(apakah|sudahkah)\b/i', $trimmed);
        $isDirectApakah = preg_match('/^(apakah|sudahkah)\b/i', $trimmed);

        if ($isBulletWithApakah || $isDirectApakah) {
            $systemKeywords = [
                'sapaan', 'ringkasan', 'eksekutif', 'insight', 'prompt',
                'smart_table', 'chart', 'currency', 'nominal', 'echoing', 'tabel', 'kolom',
                'query', 'sql', 'limit', 'offset', 'aturan', 'bahasa', 'database', 'rupiah',
                'rp', 'format', 'koreksi', 'reminder', 'menyajikan', 'menyebut', 'mencantumkan',
                'menampilkan', 'kesimpulan', 'jawaban', 'output', 'respon', 'user', 'analisis',
                'desimal', 'historis', 'tanggal', 'kosong', 'tersedia'
            ];
            foreach ($systemKeywords as $kw) {
                if (stripos($trimmed, $kw) !== false) {
                    Log::info("[StreamFilter] Blocked checklist question (matched kw: {$kw}): " . substr($trimmed, 0, 100));
                    return true;
                }
            }
        }

        $patterns = [
            '/^nama eksak [a-zA-Z ]+ ditemukan/i',
            '/^sekarang saya akan menjalankan query/i',
            '/^mapping kolom/i',
            // Baris pemetaan biasa: "Netto = hrg_jual"
            '/^(netto|hpp|total\s+netto|total\s+hpp|diskon|profit)\s*=/i',
            // Baris pemetaan format bullet: "* Netto = `hrg_jual`" atau "- Netto = ..."
            '/^[\*\-]\s*(netto|hpp|total\s+netto|total\s+hpp|diskon|profit)\s*=/i',
            // Pattern generik: "* Label = `kolom_db`" (pemetaan bisnis ke nama kolom teknis)
            '/^[\*\-]\s*\w[\w\s]+\s*=\s*`[a-z][a-z0-9_]+`/i',
            // Format: "- `nama_kolom` = deskripsi" atau "* `nama_kolom` = deskripsi"
            '/^[\*\-]\s*`[a-z][a-z0-9_]+`\s*=/i',
            // Format: "- `nama_kolom` : deskripsi" (pakai titik dua)
            '/^[\*\-]\s*`[a-z][a-z0-9_]+`\s*:/i',
            // Format tanpa bullet: "`nama_kolom` = deskripsi" di awal baris
            '/^`[a-z][a-z0-9_]+`\s*[=:]/i',
            '/^jika (tidak ada|ragu)[,.]?/i',
            '/^dari hasil\s+`?describe_table`?/i',
            '/^tidak ada kolom yang secara eksplisit/i',
            '/^oleh karena itu[,]?\s+(kita|saya) akan/i',
            '/^saya akan mencari nama tabel/i',
            '/^saya akan memeriksa struktur tabel/i',
            '/^saya telah menemukan kolom/i',
            '/^berdasarkan hasil pencarian/i',
            '/^saya menyimpulkan bahwa/i',
            '/^probe query/i',
            '/^hasil probe/i',
            '/^query utama\s*:/i',
            '/^hasil query hanya mengandung\s+satu\s+angka\s+tunggal/i',
            '/^query\s+count\s+menghasilkan/i',
            '/^format respons yang benar/i',
            '/^contoh\s+(benar|salah)\s*:/i',
            '/sebutkan\s+angka.*(langsung|kalimat|tebal)/i',
            '/insight\s+strategis.*\d+\-\d+\s+poin/i',
            '/rekomendasi\s+prompt.*\d+\-\d+\s+pertanyaan/i',
            '/jawab\s+langsung\s+dalam\s+narasi/i',
            '/^tulis\s+\d+\-\d+\s+kalimat/i',
            '/^tulis\s+\d+\-\d+\s+poin/i',
            '/pertanyaan\s+(lanjutan spesifik|analisis lebih dalam|tren atau perbandingan|cross-analysis)/i',
            '/anda\s+wajib\s+menyajikan\s+jawaban/i',
            '/mohon\s+berikan\s+jawaban\s+atau\s+panggil\s+tool/i',
            '/jangan\s+hanya\s+berpikir\s+tanpa\s+output/i',
            '/baru saja\s+(memperoleh data penting dari tool|mengirimkan query sql mentah)/i',
            '/jangan\s+melakukan\s+tool\s+call/i',
            '/segera\s+berikan\s+jawaban\s+akhir/i',
            '/gunakan\s+data\s+ini\s+untuk\s+menyusun/i',
            '/tidak\s+perlu\s+melakukan\s+query\s+tambahan/i',
            '/gunakan\s+contoh\s+data\s+ini/i',
            '/jika\s+describe_table\s+sudah/i',
            '/jika\s+ada\s+kolom\s+status\s+aktif/i',
            '/langkah\s+(wajib|berikutnya)/i',
            '/waJib\s+dibaca/i',
            '/^saya akan menggunakan\s+(jumlah|count)/i',
            '/^kolom yang tersedia/i',
            '/^kolom dari `?describe_table`?/i',
            // Baris pemetaan kolom teknis internal: hanya blokir jika formatnya adalah
            // pemetaan eksplisit seperti "Netto = hrg_jual" atau "hrg_jual = ..."
            // BUKAN baris narasi biasa yang kebetulan menyebut nama kolom
            '/^`?(hrg_jual|hrg_pokok|total_netto|total_disc|qty_jual)`?\s*[=:]/i',
            '/^[\*\-]\s*`?(hrg_jual|hrg_pokok|total_netto|total_disc|qty_jual)`?\s*[=:]/i',
            // Format: "Label bisnis = `kolom_teknis`" atau "Label bisnis : `kolom_teknis`"
            '/=\s*`(hrg_jual|hrg_pokok|total_netto|total_disc|qty_jual)`/i',

            // ═══════════════════════════════════════════════════════════════════
            // ANTI SYSTEM-PROMPT REGURGITATION (real-time stream filter)
            // ═══════════════════════════════════════════════════════════════════
            '/selalu\s+awali\s+dengan\s+(ringkasan|executive)/i',
            '/selalu\s+mulai\s+dengan\s+(ringkasan|ring)/i',
            '/ingat\s*:\s*selalu\s+mulai\s+dengan/i',
            '/awali\s+dengan\s+ringkasan\s+eksekutif/i',
            '/^jika\s+data\s+adalah\s+angka\s+tunggal/i',
            '/angka\s+tunggal.*tulis\s+langsung\s+dalam\s+narasi/i',
            '/sertakan\s+ringkasan\s+eksekutif/i',
            '/sertakan\s+insight\s+strategis/i',
            '/sertakan\s+rekomendasi\s+prompt/i',
            '/sesuai\s+instruksi\.?$/i',
            '/akhiri\s+dengan\s+rekomendasi\s+prompt/i',
            '/gunakan\s+format\s+[\'"]?(chart|smart_table|smart table)[\'"]?/i',
            '/jika\s+data\s+adalah\s+grafik/i',
            '/WAJIB\s+menggunakan\s+angka\s+dan\s+analisis/i',
            '/JANGAN\s+mengarang\s+atau\s+membuat\s+kesimpulan/i',
            '/tanpa\s+dasar\s+data/i',
            '/WAJIB\s+(mencantumkan|menyertakan|menggunakan|mengikuti|diikuti|dipanggil|memanggil|menampilkan)\b/i',
            '/DILARANG\s+(KERAS\s+)?(menampilkan|menggunakan|menulis|menambahkan|menyebut|tulis|tampil)/i',
            '/JANGAN\s+(gunakan|tambahkan|tulis|tampilkan|sebut|pernah|hanya)/i',
            '/^\s*❌\s*(DILARANG|SALAH)/i',
            '/^\s*✅\s*(WAJIB|BENAR)/i',
            '/ATURAN\s+(EMAS|WAJIB|KETAT|KRITIS)/i',
            '/PROTOKOL\s+(RECOVERY|URUTAN|KHUSUS|TIMEOUT)/i',
            '/INSTRUKSI\s+PERTAMA/i',
            '/CRITICAL\s+PRIORITY/i',
            '/MANDATORY\s+(RESPONSE|INSIGHT|FORMAT)/i',
            '/^\s*tools?\s+tersedia/i',
            '/^\s*tools?\s+yang\s+(tersedia|digunakan)/i',
            '/gunakan\s+bahasa\s+yang\s+sesuai\s+dengan\s+user/i',
            '/gunakan\s+bahasa\s+indonesia\s+formal/i',
            '/Berpikirlah\s+secara\s+internal/i',
            '/sampaikan\s+HANYA\s+jawaban\s+bisnis/i',
            '/format\s+berikut.*smart_table/i',
            '/blok.*```smart_table/i',
            '/blok.*```chart/i',
            '/^Anda\s+adalah\s+(DataBot|Data\s*Bot|asisten\s+Data\s+Analyst)/i',
            '/^saya\s+adalah\s+(DataBot|Data\s*Bot|asisten\s+Data\s+Analyst)/i',
            '/^\s*\d+\.\s+`(get_database_schema_info|search_schema|describe_table|execute_query|get_column_values|get_view_definition|get_table_preview|get_erp_guidance|get_erp_menu_navigation|fetch_erp_guidance_from_web|web_search)`/i',
            '/\[SYSTEM\s+FORMAT\s+(CORRECTION|REMINDER)\]/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                Log::info('[StreamFilter] Blocked leakage line: ' . substr($trimmed, 0, 100));
                return true;
            }
        }

        // Cek list kolom teknis berurutan (lebih dari 3 backtick kolom)
        if (preg_match('/(`[a-z][a-z0-9_]*`[,\s]*){4,}/i', $trimmed)) {
            Log::info('[StreamFilter] Blocked column list leak: ' . substr($trimmed, 0, 100));
            return true;
        }

        return false;
    }

    private function streamText(string $text): void
    {
        $chunkSize = 32;

        foreach (mb_str_split($text, $chunkSize) as $chunk) {
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
            if (ob_get_level() > 0)
                ob_flush();
            flush();
            usleep(15000); // 15ms delay per 32-char chunk for a smooth, readable typing speed
        }
    }

    private function handleProviderResponse($response, string $providerCode): ?array
    {
        if ($response->failed()) {
            $body = $response->body();
            $status = $response->status();
            Log::error("[Agentic] API Error ({$providerCode}) status={$status} body=" . $body);

            if ($status === 429 || $status === 402) {
                Log::warning("[Agentic] Rate Limit / Insufficient Balance ({$providerCode}): " . $body);
                throw new \RuntimeException('__RATE_LIMIT__');
            }

            $bodyLower = strtolower($body);
            if (
                str_contains($bodyLower, 'quota') ||
                str_contains($bodyLower, 'resource_exhausted') ||
                str_contains($bodyLower, 'rate_limit') ||
                str_contains($bodyLower, 'insufficient_balance') ||
                str_contains($bodyLower, 'balance') ||
                str_contains($bodyLower, 'exceeded')
            ) {
                throw new \RuntimeException('__RATE_LIMIT__');
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

        Log::info("[Agentic] streamOpenAiCompatibleApi loop={$loopCount} msg_count=" . count($messages) . " messages=" . json_encode(array_map(fn($m) => ['role' => $m['role'], 'name' => $m['name'] ?? null, 'tc' => !empty($m['tool_calls']), 'content_len' => strlen(is_string($m['content'] ?? null) ? $m['content'] : json_encode($m['content'] ?? ''))], $messages)));

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
            $toolMode = ($loopCount === 1) ? 'ANY' : 'AUTO';
            $payload['toolConfig'] = [
                'functionCallingConfig' => ['mode' => $toolMode],
            ];
            Log::info("[Agentic] Gemini stream toolConfig mode={$toolMode} loop={$loopCount}");
        }
        $headers = ['Content-Type: application/json'];
        return $this->curlStreamSse($url, $headers, $payload, 'gemini');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERUBAHAN 3: curlStreamSse dengan real-time line buffer filter
    // Token dikumpulkan per baris, lalu dicek via isThinkingLeakageLine()
    // SEBELUM dikirim ke user. Thinking tidak akan pernah bocor lagi.
    // ─────────────────────────────────────────────────────────────────────────
    private function curlStreamSse(string $url, array $headers, array $payload, string $providerCode): array
    {
        $fullText = '';
        $sseBuffer = '';
        $toolCallsRaw = [];
        $totalTokens = 0;

        $rawBody = '';
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
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$fullText, &$sseBuffer, &$toolCallsRaw, $providerCode, &$rawBody) {
                $rawBody .= $data;
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
        if ($httpCode >= 400) {
            Log::error("[StreamSSE] HTTP {$httpCode} ({$providerCode}) Response body: " . substr($rawBody, 0, 1000));
        }
        if ($httpCode === 429 || $httpCode === 402) {
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

    private function detectLanguage(string $message): string
    {
        $messageLower = strtolower($message);

        // Keywords for Indonesian language detection
        $idKeywords = [
            'tampilkan', 'berapa', 'cabang', 'penjualan', 'bulan', 'tahun', 'diskon', 
            'selisih', 'total', 'maret', 'laba', 'rinci', 'detail', 'dan', 'dari', 
            'untuk', 'yang', 'ini', 'semua', 'per', 'dengan', 'ada', 'tidak', 'hpp',
            'maret', 'mret', 'nopember', 'desember', 'januari', 'pebruari', 'omset',
            'keuntungan', 'harga', 'pokok'
        ];

        // Keywords for English language detection
        $enKeywords = [
            'show', 'how', 'many', 'branch', 'sales', 'month', 'year', 'discount', 
            'difference', 'profit', 'march', 'cogs', 'detail', 'and', 'from', 
            'for', 'that', 'this', 'all', 'per', 'with', 'is', 'no', 'not',
            'english', 'inggris'
        ];

        $idScore = 0;
        $enScore = 0;

        // Split into words
        $words = preg_split('/[^a-zA-Z]/', $messageLower, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $w) {
            if (in_array($w, $idKeywords)) {
                $idScore++;
            }
            if (in_array($w, $enKeywords)) {
                $enScore++;
            }
        }

        // If no word matched, try simple substring check
        if ($idScore === 0 && $enScore === 0) {
            foreach ($idKeywords as $w) {
                if (str_contains($messageLower, $w)) {
                    $idScore++;
                }
            }
            foreach ($enKeywords as $w) {
                if (str_contains($messageLower, $w)) {
                    $enScore++;
                }
            }
        }

        return $enScore > $idScore ? 'en' : 'id';
    }

    /**
     * Konversi nama teknis tabel database ke istilah bisnis yang ramah dan mudah dipahami user.
     */
    public static function formatTableToBusinessTerm(string $tableName): string
    {
        // Bersihkan prefix teknis database umum
        $clean = preg_replace('/^(view_master_|v_ms_|v_|ms_|master_|trm_|mhd_|tb_|tbl_|cdc_|mv_)/i', '', $tableName);
        $clean = str_replace('_', ' ', $clean);
        $clean = preg_replace('/\b(mbi)\b/i', '', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        $map = [
            'cabang' => 'Data Cabang',
            'pelanggan' => 'Data Pelanggan',
            'langganan' => 'Data Pelanggan',
            'customer' => 'Data Pelanggan',
            'barang' => 'Data Produk / Barang',
            'produk' => 'Data Produk',
            'item' => 'Data Barang',
            'pegawai' => 'Data Karyawan',
            'karyawan' => 'Data Karyawan',
            'perusahaan' => 'Data Perusahaan',
            'penjualan' => 'Data Penjualan',
            'pembelian' => 'Data Pembelian',
            'faktur' => 'Data Faktur Penjualan',
            'piutang' => 'Data Piutang',
            'hutang' => 'Data Hutang',
            'stok' => 'Data Stok / Inventaris',
            'dealer' => 'Data Dealer',
            'sales' => 'Data Tim Sales',
            'salesman' => 'Data Salesman',
            'lookup' => 'Data Referensi Master',
        ];

        $lower = strtolower($clean);
        if (isset($map[$lower])) {
            return $map[$lower];
        }

        foreach ($map as $key => $label) {
            if (str_contains($lower, $key)) {
                return $label;
            }
        }

        return 'Data ' . ucwords($clean);
    }
}
