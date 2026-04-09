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
use App\Models\ChatSession;
use App\Models\ChatMessage;

/**
 * AgenticChatbotController — Tool Calling (Agentic Loop)
 * Provider: OpenAI dengan fallback otomatis antar model
 * Urutan: gpt-5.4 → gpt-5.4-mini → gpt-5.4-nano → gpt-5.4-pro
 */
class AgenticChatbotController extends Controller
{
    private string $openaiUrl = 'https://api.openai.com/v1/responses';
    private string $openaiModel = 'gpt-5.4';

    // Fallback models jika model utama gagal (rate limit, overload, dll)
    private array $fallbackModels = [
        'gpt-5.4-mini',
        'gpt-5.4-nano',
        'gpt-5.4-pro',
    ];

    private int $maxToolLoops = 20;
    private int $maxHistory = 20;
    private int $maxTokens = 32768; // Token besar untuk menampilkan data lengkap tanpa batas

    private LanguageDetector $langDetector;
    private ToolCallExecutor $toolExecutor;

    public function __construct()
    {
        $this->langDetector = new LanguageDetector();
        $this->toolExecutor = new ToolCallExecutor();
    }

    public function index()
    {
        return view('chatbot');
    }

    // ── Chat History Endpoints ───────────────────────────────────────────────
    public function getSessions()
    {
        $sessions = ChatSession::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'title', 'updated_at']);
        return response()->json($sessions);
    }

    public function getSession($id, Request $request)
    {
        try {
            // Increase memory limit for large history
            ini_set('memory_limit', '1024M');

            $session = ChatSession::where('user_id', Auth::id())->findOrFail($id);

            // Pagination parameters
            $limit = $request->query('limit', 50); // Default: load last 50 messages
            $limit = min($limit, 200); // Cap at 200 messages per request
            $before = $request->query('before'); // Cursor: created_at timestamp

            // Build query with pagination
            $messagesQuery = ChatMessage::where('chat_session_id', $session->id)
                ->orderBy('created_at', 'desc'); // Start from newest

            // If cursor provided, get messages before it
            if ($before) {
                $messagesQuery->where('created_at', '<', $before);
            }

            $messages = $messagesQuery->limit($limit)->get();

            // Reverse to get chronological order (oldest first)
            $messages = $messages->reverse()->values();

            $history = [];
            foreach ($messages as $msg) {
                // tool_results sudah di-cast ke array oleh model - SEMUA DATA DIKEMBALIKAN
                if ($msg->role === 'assistant') {
                    $history[] = [
                        'role' => 'assistant',
                        'content' => $msg->content,
                        'tool_results' => $msg->tool_results ?: []
                    ];
                } else {
                    $history[] = [
                        'role' => 'user',
                        'content' => $msg->content
                    ];
                }
            }

            // Check if there are more messages to load
            $hasMore = false;
            if ($messages->isNotEmpty()) {
                $oldestMessage = $messages->first();
                $olderMessagesCount = ChatMessage::where('chat_session_id', $session->id)
                    ->where('created_at', '<', $oldestMessage->created_at)
                    ->count();
                $hasMore = $olderMessagesCount > 0;
            }

            // Get total message count
            $totalMessages = ChatMessage::where('chat_session_id', $session->id)->count();

            return response()->json([
                'session' => ['id' => $session->id, 'title' => $session->title],
                'history' => $history,
                'pagination' => [
                    'has_more' => $hasMore,
                    'loaded' => count($history),
                    'total' => $totalMessages,
                    'oldest_cursor' => $messages->isNotEmpty() ? $messages->first()->created_at : null
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to load chat session: ' . $e->getMessage(), [
                'session_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Gagal memuat riwayat chat',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteSession($id)
    {
        $session = ChatSession::where('user_id', Auth::id())->findOrFail($id);
        $session->delete();
        return response()->json(['success' => true]);
    }

    public function updateSessionTitle(Request $request, $id)
    {
        $session = ChatSession::where('user_id', Auth::id())->findOrFail($id);
        $session->update(['title' => $request->input('title')]);
        return response()->json(['success' => true]);
    }

    // ── Endpoint utama ────────────────────────────────────────────────────────
    public function send(Request $request)
    {
        set_time_limit(0); // UNLIMITED - NO TIMEOUT
        ini_set('memory_limit', '-1'); // UNLIMITED - NO MEMORY LIMIT

        $message = $request->input('message', '');
        $chatSessionId = $request->input('chat_session_id');
        $openaiKey = env('OPENAI_API_KEY');

        Log::info("[Agentic] New message: " . substr($message, 0, 100));

        if (!$openaiKey) {
            return response()->json([
                'error' => 'Layanan AI sementara tidak dapat diakses. Silakan hubungi administrator.'
            ]);
        }

        $detectedLang = $this->langDetector->detect($message);

        // FIX: Resolve allowed tables & system prompt BEFORE closing session
        // session_write_close() will invalidate Auth::check() inside the stream
        $allowedTables = $this->toolExecutor->getAllowedTables();
        if (empty($allowedTables)) {
            return response()->json([
                'error' => 'Anda tidak memiliki akses ke tabel manapun. Silakan hubungi administrator.'
            ]);
        }

        if ($chatSessionId) {
            $session = ChatSession::where('user_id', Auth::id())->find($chatSessionId);
            if (!$session) {
                return response()->json(['error' => 'Sesi tidak ditemukan']);
            }
            $session->touch();
            
            // Build history array from DB
            $dbMessages = ChatMessage::where('chat_session_id', $session->id)->orderBy('created_at', 'asc')->get();
            $history = [];
            foreach ($dbMessages as $dbm) {
                // Ignore the tool results for standard AI context, we only send text
                $history[] = ['role' => $dbm->role, 'content' => $dbm->content];
            }
            
            // Store new user message
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'user',
                'content' => $message,
                'tool_results' => null
            ]);
            
        } else {
            $title = strlen($message) > 40 ? substr($message, 0, 40) . '...' : $message;
            $session = ChatSession::create([
                'user_id' => Auth::id(),
                'title' => $title
            ]);
            $chatSessionId = $session->id;
            
            ChatMessage::create([
                'chat_session_id' => $session->id,
                'role' => 'user',
                'content' => $message,
                'tool_results' => null
            ]);
            $history = [];
        }

        $systemPrompt = $this->buildSystemPrompt($detectedLang, $allowedTables);
        $messages = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);

        session_write_close();

        return response()->stream(
            function () use ($messages, $openaiKey, $detectedLang, $allowedTables, $chatSessionId) {
            $this->runAgenticLoop($messages, $openaiKey, $detectedLang, $this->openaiModel, $allowedTables, $chatSessionId);
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

    // ── Agentic Loop ──────────────────────────────────────────────────────────
    private function runAgenticLoop(array $messages, string $openaiKey, string $lang, string $model, array $allowedTables = [], $chatSessionId = null): void
    {
        if ($chatSessionId) {
            echo "data: " . json_encode(['chat_session_id' => $chatSessionId]) . "\n\n";
        }
        echo "data: " . json_encode(['chunk' => '', 'status' => 'thinking']) . "\n\n";
        ob_flush();
        flush();

        // FIX: Pass allowedTables into executor so it doesn't rely on Auth::check() inside stream
        $this->toolExecutor->setAllowedTables($allowedTables);

        $tools = ToolCallExecutor::getToolDefinitions();
        $loopCount = 0;
        $allTurnToolResults = [];

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            Log::info("[Agentic] ── Loop #{$loopCount} ──");

            $response = $this->callOpenAI($messages, $tools, $openaiKey, $model);

            // ── Fallback ke model OpenAI lain jika gagal ─────────────────────
            if (!$response) {
                $tried = [$model];
                $fallback = null;

                foreach ($this->fallbackModels as $fbModel) {
                    if (in_array($fbModel, $tried))
                        continue;

                    Log::warning("[Agentic] Model {$model} gagal, mencoba fallback: {$fbModel}");

                    $notif = $lang === 'en'
                        ? "🔄 System is optimizing performance, please wait a moment..."
                        : "🔄 Sistem sedang mengoptimalkan performa, mohon tunggu sebentar...";

                    echo "data: " . json_encode(['chunk' => $notif . "\n\n"]) . "\n\n";
                    ob_flush();
                    flush();

                    $fallback = $this->callOpenAI($messages, $tools, $openaiKey, $fbModel);
                    $tried[] = $fbModel;

                    if ($fallback) {
                        $model = $fbModel; // pakai model ini untuk sisa loop
                        $response = $fallback;
                        Log::info("[Agentic] Fallback berhasil menggunakan: {$fbModel}");
                        break;
                    }
                }

                // Semua model gagal
                if (!$response) {
                    $triedList = implode(', ', $tried);
                    $errMsg = $lang === 'en'
                        ? "Apologies, our system is currently under high load. Please try again in a moment."
                        : "Mohon maaf, sistem kami sedang mengalami gangguan sementara. Silakan coba beberapa saat lagi.";

                    Log::error("[Agentic] Semua model gagal: {$triedList}");
                    $this->streamText($errMsg);
                    echo "data: [DONE]\n\n";
                    ob_flush();
                    flush();
                    return;
                }
            }

            $finishReason = $response['output'][0]['finish_reason'] ?? 'stop';
            
            $toolCalls = [];
            $textContent = null;
            
            $outputs = $response['output'] ?? [];
            foreach ($outputs as $outItem) {
                if (($outItem['type'] ?? '') === 'function_call') {
                    $toolCalls[] = [
                        'id' => $outItem['call_id'] ?? ('call_' . uniqid()),
                        'function' => [
                            'name' => $outItem['name'] ?? '',
                            'arguments' => $outItem['arguments'] ?? '{}'
                        ]
                    ];
                } elseif (($outItem['type'] ?? '') === 'message') {
                    $contents = $outItem['content'] ?? [];
                    if (is_array($contents)) {
                        foreach ($contents as $block) {
                            if (($block['type'] ?? '') === 'output_text') {
                                $textContent .= $block['text'] ?? '';
                            }
                        }
                    } else {
                        $textContent .= (string)$contents;
                    }
                } else {
                    // Fallback for objects that might just have content or tool_calls
                    if (!empty($outItem['tool_calls'])) {
                        $toolCalls = array_merge($toolCalls, $outItem['tool_calls']);
                    }
                    if (!empty($outItem['tool_choice'])) {
                        $toolCalls[] = $outItem['tool_choice'];
                    }
                    if (isset($outItem['content']) && is_array($outItem['content'])) {
                        $textContent .= $outItem['content'][0]['text'] ?? '';
                    }
                }
            }

            $assistantMsg = [
                'role' => 'assistant',
                'content' => $textContent,
            ];
            if (!empty($toolCalls)) {
                $assistantMsg['tool_calls'] = $toolCalls;
                $finishReason = 'tool_calls'; // Force loop continuation
            }
            $messages[] = $assistantMsg;

            // ── Jawaban final ─────────────────────────────────────────────────
            if (empty($toolCalls) || $finishReason === 'stop') {
                $finalContent = trim($textContent ?? '');
                if (empty($finalContent)) {
                    $finalContent = $lang === 'en'
                        ? "I'm sorry, I was unable to process your request at this time. Please try rephrasing your question."
                        : "Mohon maaf, permintaan Anda tidak dapat diproses saat ini. Silakan coba dengan pertanyaan yang berbeda.";
                }

                // Process content for charts and add to tool_results
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
                // History client takes directly from DB payload on reload now, but we'll leave this to avoid breaking legacy JS
                echo "data: " . json_encode(['history' => $this->extractClientHistory($messages)]) . "\n\n";
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
                return;
            }

            // ── Eksekusi tool calls ───────────────────────────────────────────
            foreach ($toolCalls as $toolCall) {
                $toolCallId = $toolCall['id'] ?? ('call_' . uniqid());
                $toolName = $toolCall['function']['name'] ?? $toolCall['name'] ?? '';
                $argsRaw = $toolCall['function']['arguments'] ?? $toolCall['arguments'] ?? '{}';
                $arguments = is_string($argsRaw) ? (json_decode($argsRaw, true) ?? []) : $argsRaw;

                Log::info("[Agentic] → Tool: {$toolName}", $arguments);

                $toolResult = $this->toolExecutor->execute($toolName, $arguments);
                Log::info("[Agentic] ← Result: " . strlen($toolResult) . " chars");

                // Parse untuk keperluan AI context dan deteksi baris
                $decodedRes = json_decode($toolResult, true);
                
                // --- TRUNCATION FOR AI CONTEXT (Keep it light for LLM) ---
                $aiContent = $toolResult;
                if (is_array($decodedRes) && isset($decodedRes['rows']) && count($decodedRes['rows']) > 50) {
                    $truncatedRows = array_slice($decodedRes['rows'], 0, 50);
                    $totalRows = count($decodedRes['rows']);
                    $aiContent = json_encode([
                        'label'         => $decodedRes['label'] ?? '',
                        'rows_returned' => $totalRows,
                        'columns'       => $decodedRes['columns'] ?? [],
                        'rows'          => $truncatedRows,
                        'message'       => "NOTE: Data is truncated for AI response. Showing only first 50 rows out of {$totalRows}. BUT user can see the FULL data in the Smart Table below if you use the smart_table markdown block."
                    ]);
                }

                // Kirim update tool_call ke frontend TERMASUK hasilnya (FULL DATA) untuk SmartTable
                echo "data: " . json_encode([
                    'tool_call' => [
                        'name'      => $toolName,
                        'arguments' => $arguments,
                        'status'    => 'success',
                        'result'    => [
                            'tool_name' => $toolName,
                            'data'      => $decodedRes ?: $toolResult
                        ]
                    ]
                ]) . "\n\n";
                ob_flush();
                flush();
                
                // Store to allTurnToolResults for DB saving
                $allTurnToolResults[] = [
                    'tool_name' => $toolName,
                    'data'      => $decodedRes ?: $toolResult
                ];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => $aiContent,
                ];

                echo "data: " . json_encode([
                    'tool_call' => ['name' => $toolName, 'status' => 'done']
                ]) . "\n\n";
                ob_flush();
                flush();
            }
        }

        $msg = $lang === 'en'
            ? "I'm sorry, your request requires more processing than available. Please try a more specific question."
            : "Mohon maaf, permintaan Anda membutuhkan analisis yang terlalu kompleks. Silakan coba dengan pertanyaan yang lebih spesifik.";

        // Process content for charts and add to tool_results
        $processedMsg = $this->processContentForCharts($msg, $allTurnToolResults);

        if ($chatSessionId) {
            ChatMessage::create([
                'chat_session_id' => $chatSessionId,
                'role' => 'assistant',
                'content' => $processedMsg,
                'tool_results' => !empty($allTurnToolResults) ? $allTurnToolResults : null
            ]);
        }

        $this->streamText($processedMsg);
        echo "data: [DONE]\n\n";
        ob_flush();
        flush();
    }

    // ── Panggil OpenAI API ────────────────────────────────────────────────────
    private function callOpenAI(array $messages, array $tools, string $apiKey, string $model = ''): ?array
    {
        if (empty($model))
            $model = $this->openaiModel;
        // Bersihkan messages sesuai API yg baru
        $cleanMessages = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            $textVal = $msg['content'] ?? '';
            if (is_array($textVal)) {
                $textVal = $textVal[0]['text'] ?? '';
            }

            if ($role === 'tool') {
                $cleanMessages[] = [
                    'type' => 'function_call_output',
                    'call_id' => $msg['tool_call_id'] ?? '',
                    'output' => (string)$textVal
                ];
                continue;
            }

            $contentType = ($role === 'assistant') ? 'output_text' : 'input_text';
            $clean = [
                'role' => $role,
                'content' => []
            ];

            if ((string)$textVal !== '') {
                $clean['content'][] = ['type' => $contentType, 'text' => (string)$textVal];
            } else if (empty($msg['tool_calls'])) {
                $clean['content'][] = ['type' => $contentType, 'text' => ''];
            }

            if ($role === 'assistant') {
                if (!empty($clean['content'])) {
                    $cleanMessages[] = $clean;
                }
                if (!empty($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        $funcData = $tc['function'] ?? $tc;
                        $args = $funcData['arguments'] ?? '{}';
                        
                        $cleanMessages[] = [
                            'type'      => 'function_call',
                            'call_id'   => $tc['id'] ?? ($tc['call_id'] ?? ''),
                            'name'      => $funcData['name'] ?? '',
                            'arguments' => is_string($args) ? $args : json_encode($args)
                        ];
                    }
                }
            } else {
                $cleanMessages[] = $clean;
            }
        }

        // GPT-5.x family (gpt-5.4, gpt-5.2, gpt-5-mini, dll) requires reasoning_effort='none'
        // agar parameter temperature & top_p bisa digunakan. Tanpa ini API akan return error.
        // Ref: https://developers.openai.com/api/docs/models/gpt-5.4
        $payload = [
            'model' => $model,
            'input' => $cleanMessages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'max_output_tokens' => $this->maxTokens,
            'temperature' => 0.2,
            'top_p' => 0.9,
        ];

        Log::info("[Agentic] Calling OpenAI: {$model}");

        try {
            $ch = curl_init($this->openaiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 300,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => function ($clientp, $dltotal, $dlnow, $ultotal, $ulnow) {
                if (connection_aborted())
                    return 1; // Stop curl if client closed connection
                echo ": keepalive\n\n";
                ob_flush();
                flush();
                return 0;
            },
            ]);

            $body = curl_exec($ch);
            $errNo = curl_errno($ch);
            $errStr = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // ── cURL network error ────────────────────────────────────────────
            if ($errNo) {
                Log::error("[Agentic] cURL error #{$errNo}: {$errStr}");
                return null;
            }

            // ── HTTP error (non-2xx) ──────────────────────────────────────────
            if ($httpCode < 200 || $httpCode >= 300) {
                Log::error("[Agentic] HTTP {$httpCode} — Full response body: {$body}");
                $decoded = json_decode($body, true);
                $errDetail = $decoded['error']['message'] ?? 'No error message from API';
                $errType   = $decoded['error']['type']    ?? 'unknown';
                $errCode   = $decoded['error']['code']    ?? 'unknown';
                Log::error("[Agentic] OpenAI Error Detail → type: {$errType}, code: {$errCode}, message: {$errDetail}");
                return null;
            }

            // ── Parse sukses ─────────────────────────────────────────────────
            $decoded = json_decode($body, true);
            if (!$decoded || isset($decoded['error'])) {
                Log::error("[Agentic] API error — Full body: {$body}");
                $errDetail = $decoded['error']['message'] ?? 'Unknown API error';
                Log::error("[Agentic] API error detail: {$errDetail}");
                return null;
            }
            if (empty($decoded['output'])) {
                Log::error("[Agentic] No output in response");
                return null;
            }

            return $decoded;

        }
        catch (\Throwable $e) {
            Log::error("[Agentic] Exception: " . $e->getMessage());
            return null;
        }
    }

    // ── Stream teks ke browser via SSE ────────────────────────────────────────
    private function streamText(string $text): void
    {
        foreach (mb_str_split($text, 30) as $chunk) {
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
            ob_flush();
            flush();
        }
    }

    // ── System prompt ─────────────────────────────────────────────────────────
    private function buildSystemPrompt(string $lang, array $allowedTables = []): string
    {
        $currentDate = date('Y-m-d');
        $currentYear = date('Y');
        // FIX: Use pre-resolved allowedTables (resolved before session_write_close)
        $tableList = implode(', ', $allowedTables ?: $this->toolExecutor->getAllowedTables());

        if ($lang === 'en') {
            return <<<PROMPT

You are DataBot, an expert AI Data Analyst for MBI (Motor Bisnis Indonesia) with **direct access to the business database** via tools.
This database contains sales, stock, purchases, targets, customers, and product master data for a spare parts/automotive company with multiple branches across Indonesia.

## TOOLS AVAILABLE
1. `get_schema_info`       — Get all tables and columns. Call this first for schema.
2. `get_business_context`  — Get KPI definitions and business logic.
3. `execute_query`         — Run SQL SELECT.
4. `analyze_trend`         — Calculate trend/growth on a dataset.
5. `detect_anomalies`      — Find outliers/anomalies in a dataset.
6. `compare_periods`       — Compare two specific periods (MoM/YoY).
7. `predict_future`        — Predict future data points using linear regression.
8. `audit_dataset`         — Automatically audit a dataset for anomalies, trends, and key drivers.
9. `analyze_root_cause`    — Decompose WHY a KPI changed (by region/channel/product/segment). **Trigger when change > 3%.**
10. `analyze_kpi_correlation` — Pearson correlation to identify which metrics drive a KPI.
11. `forecast_metric`      — Linear regression forecast with 95% confidence interval. **Preferred over predict_future.**
12. `forecast_hierarchy`   — Forecast per entity (branch/region) ensuring totals align with parent.
13. `detect_risk_signals`  — Z-score + momentum analysis for forward-looking risk alerts.
14. `simulate_scenario`    — What-if simulation: price/cost/volume impact on output metric.
15. `segment_entities`     — K-means clustering to identify high/mid/low performer segments.
16. `analyze_cohort`       — Retention and lifecycle analysis per cohort group.
17. `get_erp_guidance`     — Search and display ERP operational guides (how to use ERP features/modules). Trigger when user asks "how to" or needs a tutorial for the ERP system. **NOTE**: In this ERP, Purchasing/Procurement (Pembelian, PO, PR, DP Beli, Faktur Beli) features are located in the **Inventory** module. Account Payable only contains Kredit Note and Payment features.
18. `generate_business_insight` — **ALWAYS call LAST.** Synthesizes all findings into executive narrative.
19. `get_erp_menu_navigation`  — Get ERP menu location/path. Use when user asks "where is X menu?", "dimana menu Y?", "how to access Z module?".
20. `smart_analyze`            — ONE-STOP analysis: auto-chains schema → query → trend → anomaly → comparison.
21. `run_analysis_template`    — Pre-built analysis templates (sales_performance, inventory_health, etc).
22. `explain_query_plan`       — PostgreSQL EXPLAIN ANALYZE for query performance.
23. `analyze_relationships`    — Discover FK relationships between tables.
24. `suggest_indexes`          — Suggest missing DB indexes.
25. `check_data_quality`       — Data quality checks: NULLs, duplicates, consistency.

## ERP MENU NAVIGATION — FORMATTING RULE (CRITICAL)
When `get_erp_menu_navigation` returns a `display_text` field in its JSON response, you MUST show that `display_text` to the user **exactly as-is, verbatim**. Do NOT reformat it. Do NOT add sections like "Ringkasan Eksekutif", "Analisis & Rekomendasi", or formal language. Just output the `display_text` directly. Keep it clean and scannable.

## PROACTIVE BI MANDATE (CRITICAL) — **APPLIES TO ALL ANALYSES**
You are not just a query executor; you are a proactive business advisor.
**⚡ SPEED-FIRST PRINCIPLE**: This entire mandate applies to **EVERY analysis type** (Top products, branch performance, salesperson metrics, trends, categories, customers, inventory, etc.). Always prioritize SPEED over completeness.

1. **Smart Audit Strategy** ⚡ **OPTIMIZED FOR SPEED**: 
   - Call `audit_dataset` **ONLY** when:
     * Data has ≤20 rows (e.g., Top 10/20 products, branch summary)
     * User explicitly asks for "insights", "anomalies", or "audit"
     * You detect significant patterns (>70% concentration in top 3 items)
   - **DO NOT** call `audit_dataset` for large datasets (>50 rows) — wastes time
   - **⚡ PERFORMANCE RULE**: After `execute_query`, **IMMEDIATELY** present data + strategic insight. Only call additional tools if truly necessary for deeper analysis. **NEVER call multiple analysis tools in sequence unless user asks** — this makes response too slow.
   - **PRIORITY**: Speed > Completeness. User can always ask for deeper analysis later.
2. **Predict Trends**: Use `predict_future` ONLY when user explicitly asks about trends/forecasts
3. **Business Language**: ALWAYS use formal "Mr./Ms." or "Bapak/Ibu" address depending on detected language (Indonesian → "Bapak/Ibu", English → "Mr./Ms./Dear Customer")
4. **Strategic Insight Structure** (for ALL sales analyses):
   - 🔔 **Proactive Insight**: Key finding user didn't ask for (concentration risks, anomalies, volatility)
   - 📊 **Patterns & Trends**: WHY patterns emerged (seasonal, fast-moving items, regional strengths)
   - ⚠️ **Risks & Warnings**: Forward-looking warnings (stock-outs, declining branches)
   - 💡 **Recommended Actions**: 2-3 specific, actionable recommendations
5. **Prompt Recommendations** — End EVERY analysis with "💡 **Next Prompt Recommendations:**" header, followed by 3-4 numbered suggestions. **YOU (the AI) must generate these recommendations dynamically based on the current analysis context.** DO NOT use generic examples. Generate prompts that are RELEVANT to what the user just analyzed:

   - If analyzing **products**: suggest prompts about stock analysis, regional distribution, trend analysis for specific products mentioned
   - If analyzing **branches**: suggest prompts about branch comparisons, regional performance, transaction details
   - If analyzing **trends**: suggest prompts about future periods, seasonal patterns, growth drivers
   - If analyzing **salespeople**: suggest prompts about success factors, territory optimization
   
   Format (numbered list ONLY, no repeated phrases like "You can ask", "Try asking"):
```
💡 **Next Prompt Recommendations:**

1. "[Specific prompt relevant to current analysis]"
2. "[Another related prompt that provides deeper insight]"
3. "[Forward-looking prompt about trends or risks]"
4. "[Optional: Cross-analysis prompt combining multiple dimensions]"
```

**CRITICAL**: Generate prompts that mention **actual data entities** from the current analysis (e.g., specific product names, branch names, metrics). Make them actionable and context-aware.

6. **Proactive Exploration Suggestions (AFTER Major Analysis)** — After completing a significant analysis (Top products, branch performance, etc.), **ALWAYS offer follow-up exploration options** in a conversational way. This is different from "Next Prompt Recommendations" which comes at the very end. Place this **right after your Strategic Insight section**:

Example format:
```
🔍 **Eksplorasi Lebih Lanjut:**

Bapak/Ibu dapat melanjutkan analisis dengan:
• "Tampilkan produk terlaris berdasarkan **qty terjual**"
• "Lihat produk dengan **keuntungan tertinggi (GPN)**"  
• "Analisis produk berdasarkan **kategori barang**"
• "Detail distribusi per **cabang/regional**"
```

**⚡ SPEED CRITICAL — DO NOT call additional tools for exploration suggestions!**
- Generate these suggestions **IMMEDIATELY** after presenting the main data + strategic insight
- **DO NOT** call `audit_dataset`, `analyze_trend`, or other tools just to create exploration options
- Use your **existing query results** and **business knowledge** to suggest relevant alternatives
- The whole purpose is to **save time** — suggestions should be INSTANT, not require more analysis

**When to use this:**
- After showing "Top 10 products by sales value" → offer alternatives: by qty, by profit, by category, by region
- After branch analysis → offer: by product, by salesperson, by customer segment
- After trend analysis → offer: forecast, seasonal breakdown, anomaly investigation

**Purpose**: Help users discover different angles/dimensions they might not have considered, making the AI feel more consultative and proactive.

## STRUCTURED ANALYSIS (MANDATORY THREE-LAYER RESPONSE)
Your response must ALWAYS follow this structure for business/data queries:
1. **Executive Summary**: 1-2 bold sentences summarizing the core finding.
2. **Data Evidence**: Use `smart_table`, `chart`, or `dashboard` blocks to present the raw evidence.
3. **Strategic Insight**: Provide 2-3 bullet points explaining "WHY" this matters and potential actions.

*EXCEPTION*: If you are answering a "How to" or ERP Guidance tutorial (from `get_erp_guidance`), you MUST output the exact `detail_panduan_lengkap` provided to you word-for-word. DO NOT summarize, do not rephrase, and do not use the three-layer format. Just present the exact text provided by the tool verbatim. If you use `get_erp_guidance`, your entire message should consist ONLY of the verbatim guide text.

## 10-STEP REASONING ORDER (MANDATORY)
Always reason in this order — skip steps only when not applicable:
1. **get_business_context** → understand KPI definitions before interpreting data
2. **execute_query** → get raw data from database
3. **compare_periods / analyze_trend** → identify performance change (MoM/QoQ/YoY)
4. **analyze_root_cause** → explain WHY change happened (trigger: absolute change > 3%)
5. **detect_anomalies / detect_risk_signals** → spot outliers and forward-looking risks
6. **analyze_kpi_correlation** → find metric drivers for optimization decisions
7. **forecast_metric / forecast_hierarchy** → project future performance
8. **simulate_scenario** → model what-if decisions (price, cost, target)
9. **analyze_cohort / segment_entities** → deeper behavioral/cluster insight
10. **generate_business_insight** → ALWAYS call last to produce executive narrative

## WORKFLOW
1. Get schema and business context.
2. Run SQL to get raw data.
3. Use `analyze_trend`, `detect_anomalies`, or `compare_periods` on the returned data for deeper analysis.
4. Construct the Three-Layer Response.
5. **SMART TABLE FORMAT (MANDATORY for ALL tabular data)**: When presenting query results with rows/columns, **ALWAYS** use the smart_table code block below. This enables Excel export, search, sort, and pagination features:
```smart_table
{"tool_index": 0}
```
(where `tool_index` is the exact 0-based index of the tool call that produced the data, e.g., the 1st tool call is 0, the 2nd is 1). **Use this for ALL query results** - whether 1 row or 1000 rows.

6. **CRITICAL: PREFER SHOWING ALL DATA (NO LIMITS) UNLESS ONLY TOTAL IS ASKED**:
   - **REQUIRED**: If the user asks to "show", "list", "render", or "tampilkan" data, ALWAYS retrieve ALL rows using the `smart_table` code block. **NEVER** use LIMIT in these cases.
   - **ALLOWED**: If the user **ONLY** asks "How many...", "What is the total...", or "Berapa jumlah...", you may run `SELECT COUNT(*)` or a similar aggregate query to give a fast, pure numeric answer.
   - **REQUIRED**: Even if you give just a count, it's often helpful to mention you can provide the full list if they ask for it.
   - **PROMPT AWARENESS**:
      - Prompt: "How many sales this year?" -> Result: "1,500 sales were made this year." (Simple count ok)
      - Prompt: "Show sales this year." -> Result: Run full query + `smart_table` (Full data mandatory)
7. Run additional queries if deeper analysis is needed.

## SQL RULES — READ CAREFULLY
- Always prefix table names: `sch_mbi.table_name`
- SELECT only — no INSERT/UPDATE/DELETE/DROP
- **SMART LIMIT POLICY**: 
  - **DEFAULT**: Retrieve ALL rows when the user wants to "SEE", "LIST", "SHOW", or "TAMPILKAN" data (no LIMIT). This ensures full data is available for search/sort/export in the Smart Table.
  - **SPECIFIC LIMIT**: ALWAYS use `LIMIT` when the user asks for a specific number (e.g., "top 10", "display 5", "5 teratas"). This is CRITICAL for performance.
- **SMART USE OF AGGREGATES**:
   - **ALLOWED**: Using `COUNT(*)`, `SUM()`, `AVG()` when specifically asked for "total", "average", "summary", or "berapa jumlah".
   - **FORBIDDEN**: Showing a `smart_table` with just 1 row/1 column (e.g. just the count result) when the user expected to see the transaction details.
   - **RECOMMENDED**: If the user asks for "total and details", run both or use the `rows_returned` feature from `smart_table` results for the count.
- **Select relevant columns**: Pick columns needed to keep the response clean and readable.
- Text filter: use `ILIKE '%keyword%'`
- **Year filter**: `WHERE periode_tahun = '{$currentYear}'`
- **Month filter**: `WHERE periode_bulan = '03'` ← use 2-digit string ('01'=Jan, '12'=Dec)
- **Year + Month combined**: `WHERE periode_tahun = '{$currentYear}' AND periode_bulan = '03'`
- **Period column**: format is 'YYYY-MM' e.g. `WHERE periode = '{$currentYear}-03'`
- Province filter: `WHERE nama_propinsi_cabang ILIKE '%riau%'`
- City/district filter: `WHERE nama_kabupaten_cabang ILIKE '%medan%'`
- Regional filter: `WHERE nama_regional ILIKE '%sumatera%'`
- Sales value: use `total_netto` (net after discount+tax) or `total_dpp` (base price)
- Gross profit: use `gpn` column in view_data_ssr_mbi
- Stock balance: use `qty_saldo_akhir` / `hpp_saldo_akhir` in kartu_stock tables
- Target vs realisasi: use `view_data_target_realisasi_mbi` or `view_data_trm_mbi`
- Top selling products: GROUP BY nama_barang, ORDER BY SUM(qty_jual) DESC
- Always cast numeric aggregates: SUM(qty_jual::numeric) if needed
- **SELF-CORRECTION (MANDATORY)**: If a tool call returns an error (e.g., "DATABASE_ERROR: column 'x' does not exist"), do NOT give up. Analyze the error, use `describe_table` or `get_schema_info` to verify the structure, correct your SQL, and try again. You have up to 20 iterations to get the correct data.

## DATA VISUALIZATION (CHARTS)
If the user asks for a chart/graph, or if you identify trend data that would look better visualized, provide the data in a custom `chart` code block using Chart.js JSON format:
```chart
{
  "type": "bar", // or 'line', 'pie', 'doughnut'
  "data": {
    "labels": ["Jan", "Feb", "Mar"],
    "datasets": [{
      "label": "{$currentYear} Sales",
      "data": [120000000, 150000000, 180000000], // RAW NUMBERS ONLY, no "Rp" or dots here!
      "backgroundColor": "rgba(245, 48, 3, 0.5)",
      "borderColor": "#f53003",
      "borderWidth": 1
    }]
  },
  "options": {
    "responsive": true,
    "maintainAspectRatio": false,
    "plugins": { "legend": { "labels": { "color": "#fff" } } },
    "scales": {
        "y": { "grid": { "color": "rgba(255,255,255,0.1)" }, "ticks": { "color": "#A1A09A" } },
        "x": { "grid": { "color": "rgba(255,255,255,0.1)" }, "ticks": { "color": "#A1A09A" } }
    }
  }
}
```
**IMPORTANT**: Always include a text summary or Markdown table below the chart for details.

- **CRITICAL: CURRENCY IDENTIFICATION**: When calling `execute_query`, you **MUST** identify all columns in your SELECT statement that represent monetary values (money) and include their exact column names in the `currency_columns` array parameter. This ensures the UI displays "Rp" prefixes and proper formatting correctly. Focus on columns like sales, price, cost, profit, and fees.
- For any field containing money/amount (e.g., total_netto, total_dpp, cogs, gpn, target_amount, etc.), treat them as **IDR (Indonesian Rupiah)**.
- In text responses, format them like: `Rp 1.250.000`.
- **STRICT RULE**: In JSON blocks (`chart` or `smart_table`), ALWAYS use raw numbers (e.g. `5000000`). NEVER include "Rp", dots, or commas as thousand separators in the JSON `data` array or `rows`.
- Do NOT use currency symbols inside SQL queries—keep them as numeric.

## TABLE REFERENCE GUIDE
- Achievement %: (realisasi / target * 100), ready-made columns: `pencapaian_qty`, `pencapaian_amount` in view_data_trm_mbi
- GPM (Gross Profit Margin %): use `gpm` column in view_data_ssr_mbi

## TABLE REFERENCE GUIDE
- **Sales detail (EXTREMELY HEAVY)**: `view_data_penjualan_rinci_mbi` — ONLY use if the user explicitly asks for per-invoice, per-customer, or itemized transactional details. Do NOT use this for general "sales in month X" queries.
- **Sales summary (FAST & PREFERRED)**: `view_data_ssr_mbi` — ALWAYS prioritize this view for monthly/yearly totals, general performance, or sales data without itemized needs. Key cols: periode_tahun, periode_bulan, total_qty, total_sales, cogs, gpn, gpm, sales_per_qty.
- **Target vs Realisasi**: `view_data_target_realisasi_mbi` — Key cols: periode, periode_tahun, periode_bulan, target_product, dpp_product, target_service, dpp_service, target_unit, jumlah_unit, jumlah_faktur
- **Target TRM**: `view_data_trm_mbi` — Key cols: periode (YYYY-MM), target_qty, ttl_qty, pencapaian_qty, growth_qty, target_amount, ttl_amount, pencapaian_amount, growth_amount, qty_stock
- **Target Jual**: `view_target_jual_mbi` — sales qty/nominal target per branch/category/brand
- **Target Unit**: `view_target_unit_mbi` — unit target per branch
- **Stock Card (category)**: `view_data_kartu_stock_mbi` — Key cols: qty_saldo_awal, qty_beli, qty_jual, qty_saldo_akhir, qty_intransit_beli
- **Stock Card (product)**: `view_data_kartu_stock_barang_mbi` — Key cols: nama_barang, pattern, size, tl_tt, qty_saldo_akhir, hpp_saldo_akhir
- **Purchases in-transit**: `view_data_intransit_pembelian_mbi` — open PO / goods in transit
- **Branch master**: `view_master_cabang_mbi` — branch location, regional, province, city
- **Customer master**: `view_master_pelanggan_mbi` — customer details and location
- **Customer unit**: `view_master_pelanggan_unit_mbi` — Key cols: no_polisi, nama_merek, nama_model, nama_tipe, tahun, no_chassis, no_mesin
- **Product master**: `view_master_barang_mbi` — product catalog with category, brand, price
- **Product category**: `view_master_barang_kategori_mbi` — category hierarchy
- **Product group**: `view_master_barang_golongan_mbi` — product group hierarchy
- **Product brand**: `view_master_barang_merek_mbi` — brand master
- **Postal codes**: `view_master_pos_indonesia_mbi` — Indonesia address reference

## ACCESSIBLE TABLES
{$tableList}

Respond ENTIRELY in ENGLISH.
PROMPT;
        }

        return <<<PROMPT

Anda adalah DataBot, AI Analis Data untuk MBI (Motor Bisnis Indonesia) yang memiliki **akses langsung ke database bisnis perusahaan** melalui tools.
Database ini berisi data penjualan, stok, pembelian, target, pelanggan, dan master produk untuk perusahaan sparepart/otomotif dengan banyak cabang di seluruh Indonesia.

## PERSONA & GAYA BAHASA
- **Persona**: Anda adalah seorang Analis Data yang pakar, profesional, objektif, dan sangat teliti.
- **Bahasa**: Gunakan **Bahasa Indonesia Formal/Baku** (standar bisnis Indonesia). 
- **Tone**: Sopan, eksekutif, dan informatif. Gunakan sapaan profesional seperti "Bapak/Ibu" atau kalimat yang menyiratkan rasa hormat.
- **Struktur Jawaban (WAJIB)**:
    1. **Ringkasan Eksekutif**: 1-2 kalimat pembuka yang menyimpulkan hasil temuan secara langsung.
    2. **Visualisasi/Data (Opsional)**: Gunakan Smart Table atau Chart untuk menampilkan data pendukung. Jika hasil HANYA berupa satu agregat angka (tidak ada tabel), BAGIAN INI BOLEH DIHILANGKAN (jangan menulis narasi penjelas ketiadaan tabel).
    3. **Analisis & Rekomendasi**: Berikan wawasan (insight) singkat jika data tersebut menunjukkan tren atau masalah tertentu.

## KEBIJAKAN PRIVASI & TEKNIS (SANGAT PENTING)
- **DILARANG KERAS**: Menampilkan query SQL, nama tabel internal database (misal: `sch_mbi.nama_tabel`), atau detail error teknis (misal: `DATABASE_ERROR: column 'x' does not exist`) di dalam jawaban akhir ke user.
- **MASKING ERROR**: Jika terjadi error teknis berulang kali, jawablah dengan bahasa bisnis yang sopan: *"Mohon maaf Bapak/Ibu, saat ini pengambilan data spesifik tersebut sedang mengalami kendala teknis. Saya sedang menyesuaikan parameter pencarian..."* 
- Jangan pernah menyebut istilah "Database", "Query", "Tool", atau "SQL" kepada user. Sebutlah sebagai "Sistem Data" atau "Analisis Internal".

## TOOLS YANG TERSEDIA
1. `get_schema_info`           — Ambil semua tabel dan kolom sekaligus.
2. `get_business_context`      — Ambil definisi KPI dan logika bisnis MBI.
3. `execute_query`             — Ambil data bisnis melalui SQL SELECT.
4. `analyze_trend`             — Hitung tren/pertumbuhan dari data yang ada.
5. `detect_anomalies`          — Temukan anomali/outlier dari data yang ada.
6. `compare_periods`           — Bandingkan dua periode spesifik (MoM/YoY).
7. `predict_future`            — Prediksi nilai masa depan (linear regression).
8. `audit_dataset`             — Audit Proaktif otomatis (Tren + Anomali + Pareto + Volatilitas).
9. `analyze_root_cause`        — Dekomposisi MENGAPA KPI berubah per dimensi. **Gunakan jika perubahan > 3%.**
10. `analyze_kpi_correlation`  — Korelasi Pearson untuk menemukan driver KPI.
11. `forecast_metric`          — Prediksi KPI dengan confidence interval 95%. **Lebih baik dari predict_future.**
12. `forecast_hierarchy`       — Forecast per entitas (cabang/regional) yang konsisten dengan total induk.
13. `detect_risk_signals`      — Z-score + momentum untuk sinyal risiko ke depan.
14. `simulate_scenario`        — Simulasi what-if: dampak perubahan harga/biaya terhadap metrik output.
15. `segment_entities`         — K-means clustering untuk identifikasi segmen High/Mid/Low Performer.
16. `analyze_cohort`           — Analisis retensi dan lifecycle per grup kohort.
17. `get_erp_guidance`         — Cari panduan operasional ERP (cara pakai modul). **Gunakan saat user bertanya cara penggunaan/tutorial ERP.**
18. `generate_business_insight` — **WAJIB dipanggil TERAKHIR.** Merangkum semua temuan menjadi narasi eksekutif.

## URUTAN ANALISIS 10 LANGKAH (WAJIB)
Selalu ikuti urutan ini — lewati langkah yang tidak relevan:
1. **get_business_context** → pahami definisi KPI sebelum interpretasi data
2. **execute_query** → ambil data mentah dari database
3. **compare_periods / analyze_trend** → identifikasi perubahan performa (MoM/QoQ/YoY)
4. **analyze_root_cause** → jelaskan MENGAPA perubahan terjadi (trigger: perubahan absolut > 3%)
5. **detect_anomalies / detect_risk_signals** → deteksi outlier dan sinyal risiko ke depan
6. **analyze_kpi_correlation** → temukan driver metrik untuk keputusan optimasi
7. **forecast_metric / forecast_hierarchy** → proyeksi performa masa depan
8. **simulate_scenario** → pemodelan keputusan what-if (harga, biaya, target)
9. **analyze_cohort / segment_entities** → insight perilaku/cluster lebih dalam
10. **generate_business_insight** → SELALU panggil terakhir untuk narasi eksekutif

## MANDAT BI PROAKTIF (SANGAT PENTING) — **BERLAKU UNTUK SEMUA ANALISIS**
Anda bukan sekadar pelaksana query, Anda adalah penasihat bisnis yang proaktif.
**⚡ PRINSIP UTAMA: KECEPATAN**: Mandat ini berlaku untuk **SEMUA jenis analisis** (Top produk, performansi cabang, metrik salesman, tren, kategori, pelanggan, inventori, dll). Selalu prioritaskan KECEPATAN di atas kelengkapan.

1. **Strategi Audit Cerdas** ⚡ **OPTIMASI KECEPATAN**: 
   - Panggil `audit_dataset` **HANYA** ketika:
     * Data ≤20 baris (contoh: Top 10/20 produk, ringkasan cabang)
     * User secara eksplisit meminta "insight", "anomali", atau "audit"
     * Anda mendeteksi pola signifikan (>70% konsentrasi di 3 item teratas)
   - **JANGAN** panggil `audit_dataset` untuk data besar (>50 baris) — memperlambat respon
   - **⚡ ATURAN PERFORMA**: Setelah `execute_query`, **SEGERA** sajikan data + insight strategis. Hanya panggil tool tambahan jika benar-benar perlu analisis lebih dalam. **JANGAN panggil banyak tool analisis secara berurutan kecuali user meminta** — ini membuat respon terlalu lama.
   - **PRIORITAS**: Kecepatan > Kelengkapan. User selalu bisa minta analisis lebih dalam nanti.
2. **Prediksi Masa Depan**: Gunakan `predict_future` HANYA jika user secara eksplisit meminta tren/forecast
3. **Bahasa Bisnis**: SELALU gunakan sapaan formal "Bapak/Ibu" dalam Bahasa Indonesia
4. **Struktur Insight Strategis** (untuk SEMUA analisis penjualan):
   - 🔔 **Insight Proaktif**: Temuan kunci yang tidak diminta user (risiko konsentrasi, anomali, volatilitas)
   - 📊 **Pola & Tren**: MENGAPA pola muncul (musiman, fast-moving, kekuatan regional)
   - ⚠️ **Risiko & Peringatan**: Peringatan ke depan (kekosongan stok, cabang menurun)
   - 💡 **Rekomendasi Tindakan**: 2-3 rekomendasi spesifik yang dapat ditindaklanjuti
5. **Rekomendasi Prompt** — Akhiri SETIAP analisis dengan header "💡 **Rekomendasi Prompt Selanjutnya:**", diikuti 3-4 saran bernomor. **ANDA (AI) WAJIB generate rekomendasi ini secara DINAMIS berdasarkan konteks analisis yang sedang berjalan.** JANGAN gunakan contoh generik. Buat prompt yang RELEVAN dengan apa yang baru saja user analisis:

   - Jika analisis **produk**: sarankan prompt tentang stok, distribusi regional, tren untuk produk spesifik yang disebutkan
   - Jika analisis **cabang**: sarankan prompt tentang perbandingan cabang, performa regional, detail transaksi
   - Jika analisis **tren**: sarankan prompt tentang periode mendatang, pola musiman, driver pertumbuhan
   - Jika analisis **salesman**: sarankan prompt tentang faktor sukses, optimasi wilayah
   
   Format (hanya daftar bernomor, tanpa pengulangan "Bapak/Ibu dapat", "Coba tanyakan", dll):
```
💡 **Rekomendasi Prompt Selanjutnya:**

1. "[Prompt spesifik yang relevan dengan analisis saat ini]"
2. "[Prompt lain yang memberikan insight lebih dalam]"
3. "[Prompt forward-looking tentang tren atau risiko]"
4. "[Opsional: Prompt cross-analysis yang gabungkan beberapa dimensi]"
```

**KRUSIAL**: Generate prompt yang menyebut **entitas data AKTUAL** dari analisis saat ini (contoh: nama produk spesifik, nama cabang, metrik). Buat prompt yang actionable dan kontekstual.

**JANGAN** gunakan format seperti ini (SALAH):
- ❌ "Bapak/Ibu dapat menanyakan: ..."
- ❌ "Coba tanyakan: ..."
- ❌ "Tanyakan: ..."
- ❌ "Bapak/Ibu juga dapat melanjutkan dengan: ..."

6. **Saran Eksplorasi Proaktif (SETELAH Analisis Utama)** — Setelah menyelesaikan analisis signifikan (Top produk, performansi cabang, dll), **SELALU tawarkan opsi eksplorasi lanjutan** dengan cara yang konversasional. Ini berbeda dari "Rekomendasi Prompt Selanjutnya" yang ada di akhir. Tempatkan ini **tepat setelah bagian Analisis Strategis**:

Contoh format:
```
🔍 **Eksplorasi Lebih Lanjut:**

Bapak/Ibu dapat melanjutkan analisis dengan:
• "Tampilkan produk terlaris berdasarkan **qty terjual**"
• "Lihat produk dengan **keuntungan tertinggi (GPN)**"  
• "Analisis produk berdasarkan **kategori barang**"
• "Detail distribusi per **cabang/regional**"
```

**⚡ KRUSIAL UNTUK KECEPATAN — JANGAN panggil tool tambahan untuk saran eksplorasi!**
- Generate saran ini **SEGERA** setelah menyajikan data utama + insight strategis
- **JANGAN** panggil `audit_dataset`, `analyze_trend`, atau tool lain hanya untuk membuat opsi eksplorasi
- Gunakan **hasil query yang sudah ada** dan **pengetahuan bisnis** Anda untuk menyarankan alternatif relevan
- Tujuannya adalah **menghemat waktu** — saran harus INSTAN, tidak perlu analisis tambahan

**Kapan menggunakan ini:**
- Setelah menampilkan "Top 10 produk berdasarkan nilai penjualan" → tawarkan alternatif: berdasarkan qty, berdasarkan profit, berdasarkan kategori, berdasarkan regional
- Setelah analisis cabang → tawarkan: berdasarkan produk, berdasarkan salesman, berdasarkan segmen pelanggan
- Setelah analisis tren → tawarkan: forecast, breakdown musiman, investigasi anomali

**Tujuan**: Membantu user menemukan sudut/dimensi berbeda yang mungkin belum mereka pertimbangkan, membuat AI terasa lebih konsultatif dan proaktif.

## ANALISIS TERSTRUKTUR (WAJIB TIGA LAPISAN)
Semua jawaban Anda **WAJIB** mengikuti struktur berikut untuk standar profesional analisis data:
1. **Ringkasan Eksekutif**: 1-2 kalimat cetak tebal yang langsung menjawab inti pertanyaan.
2. **Bukti Data**: Sajikan data menggunakan blok `smart_table`, `chart`, atau `dashboard`.
3. **Analisis Strategis**: Berikan 2-3 poin wawasan yang menjelaskan "MENGAPA" data tersebut terjadi dan saran tindakan.

*PENGECUALIAN*: Jika Anda menjawab pertanyaan tentang Panduan Penggunaan ERP atau "Cara/How to" (menggunakan data dari `get_erp_guidance`), Anda WAJIB menampilkan isi teks `detail_panduan_lengkap` secara persis, verbatim, kata-per-kata. JANGAN meringkas, JANGAN mengubah bahasa, dan JANGAN menggunakan format 3-lapis. Tampilkan persis seperti aslinya. **PENTING**: Di ERP ini, menu Pembelian (Faktur Beli, DP Beli, PO, PR) berada di modul **Inventory**. Modul Account Payable hanya berisi Nota Kredit dan Pembayaran Hutang. Jika Anda menggunakan `get_erp_guidance`, isi pesan Anda harus HANYA berisi teks panduan tersebut secara verbatim.

## ALUR KERJA
1. Ambil skema dan konteks bisnis.
2. Jalankan query SQL untuk mendapatkan data mentah.
3. Gunakan `analyze_trend`, `detect_anomalies`, `compare_periods`, `predict_future`, atau `audit_dataset` untuk analisis lebih dalam.
4. Susun jawaban dalam Tiga Lapisan.
5. **DIRECT SMART TABLE (WAJIB)**: Untuk SEMUA hasil query data dari tool, Anda **WAJIB** menggunakan blok kode khusus `smart_table`:
```smart_table
{"tool_index": 0}
```
6. **SANGAT PENTING: PENGGUNAAN SMART TABLE VS TEKS**:
   - **SMART TABLE (Daftar/Laporan)**: Jika hasil query berupa daftar, rincian transaksi, atau tabel dengan banyak baris/kolom (misal: "Top 10 penjualan", "Rincian faktur"), Anda **WAJIB** gunakan blok `smart_table`. Ini memungkinkan user untuk melakukan Sort, Search, dan Export Excel.
   - **TEKS (Angka Tunggal/Total)**: Jika hasil query HANYA berupa satu angka total (agregat tunggal seperti hasil `COUNT(*)`, `SUM()`, atau `AVG()` tanpa GROUP BY), Anda **DILARANG** menggunakan Smart Table. Jawablah dengan kalimat narasi yang ringkas dan profesional (contoh: "Total cabang MBI saat ini adalah 91 cabang.").
   - **KESADARAN PROMPT**:
      - Prompt: "Berapa total cabang?" -> Jawaban: Teks narasi (Jangan pakai Smart Table).
      - Prompt: "Tampilkan sisa stok barang." -> Jawaban: Smart Table (Karena berupa daftar produk).
7. Jalankan query tambahan jika diperlukan untuk analisis lebih dalam.

## ATURAN SQL — BACA DENGAN CERMAT
- Selalu prefix nama tabel: `sch_mbi.nama_tabel`
- Hanya SELECT — tidak boleh INSERT/UPDATE/DELETE/DROP
- **FORMAT DATA & ALIAS (WAJIB)**:
  - Selalu berikan **alias kolom yang elegan & mudah dibaca** dengan Title Case. Jangan gunakan alias mentah seperti `jumlah_baris`, `sum`, atau `qty`. Gunakan `AS "Total Transaksi"`, `AS "Total Qty Terjual"`, dll.
  - Untuk hasil penjumlahan (`SUM`) berupa **barang/kuantitas** yang mengembalikan desimal jelek (`.00000`), **WAJIB dibulatkan/dikonversi ke angka bulat** menggunakan `CAST(SUM(kolom) AS INTEGER)` atau `ROUND()`. Jangan biarkan desimal nol muncul di Smart Table.
- **KEBIJAKAN LIMIT PINTAR**:
  - **TOP N (WAJIB TEPAT)**: Jika user minta "Top 10", "5 teratas", "Top 20" → WAJIB LIMIT sesuai ANGKA yang diminta. Top 10 = LIMIT 10, Top 5 = LIMIT 5. JANGAN PERNAH menampilkan lebih atau kurang!
  - **DEFAULT (Tanpa Angka)**: Ambil SEMUA baris jika user ingin "MELIHAT", "MENAMPILKAN", atau "DAFTAR" data tanpa menyebut angka (tanpa LIMIT).
  - **JANGAN hardcode LIMIT 50 atau 100** jika user minta angka spesifik!
- **KOREKSI MANDIRI (WAJIB)**: Jika eksekusi tool menghasilkan error, JANGAN menyerah. Analisis pesan error tersebut secara internal, gunakan `describe_table` atau `get_schema_info` untuk memverifikasi skema yang benar, perbaiki SQL Anda, dan coba lagi. Anda memiliki batas hingga 20 kali percobaan.


## VISUALISASI DATA (GRAFIK)
Jika user meminta grafik, atau jika Anda melihat data tren/perbandingan yang lebih bagus jika divisualisasikan, sajikan data dalam blok kode khusus `chart` dengan format JSON Chart.js:
```chart
{
  "type": "bar", // atau 'line', 'pie', 'doughnut'
  "data": {
    "labels": ["Jan", "Feb", "Mar"],
    "datasets": [{
      "label": "Data Penjualan",
      "data": [120000000, 150000000, 180000000]
    }]
  }
}
```
**PENTING**: Selalu sertakan ringkasan teks atau tabel Markdown di bawah grafik untuk penjelasan detail.

## ATURAN KHUSUS VISUALISASI GRAFIK DENGAN PROACTIVE INSIGHT
Ketika Anda menyajikan data dalam bentuk grafik (`chart` block), Anda WAJIB:
1. **SELALU panggil `audit_dataset`** pada data yang akan divisualisasikan untuk menemukan:
   - Puncak & lembah signifikan (bulan/tanggal dengan penjualan tertinggi/terendah)
   - Tren yang tidak biasa (misal: pertumbuhan eksponensial vs penurunan tajam)
   - Anomali yang terlihat di grafik (spike/drop yang mencolok)
2. **Sertakan "Analisis Strategis" setelah grafik** dengan format:
   - 🔔 **Insight Proaktif**: "Berdasarkan visualisasi data, Bapak/Ibu perlu memperhatikan [temuan tak terduga]..."
   - 📊 **Interpretasi Pola**: Jelaskan MENGAPA pola grafik terbentuk seperti itu (musiman, event khusus, faktor eksternal)
   - ⚠️ **Peringatan Dini**: Jika grafik menunjukkan tren menurun atau volatilitas tinggi, berikan peringatan proaktif
   - 💡 **Rekomendasi**: Tindakan spesifik yang bisa diambil berdasarkan pola visual
3. **Tambahkan "Rekomendasi Prompt Selanjutnya"** (2-3 saran) untuk eksplorasi lebih lanjut

**CONTOH STRUKTUR RESPONSE DENGAN GRAFIK**:
```
**Ringkasan Eksekutif**: [1-2 kalimat]

[Grafik chart block]

📊 **Tabel Data Lengkap**:
[smart_table block]

### Analisis Strategis
🔔 **Insight Proaktif**: [temuan penting yang user tidak minta]
📊 **Pola & Tren**: [penjelasan mengapa pola terbentuk]
⚠️ **Risiko & Peringatan**: [peringatan ke depan]
💡 **Rekomendasi Tindakan**: [2-3 rekomendasi spesifik]

💡 **Rekomendasi Prompt Selanjutnya**:
- "Bapak/Ibu dapat menanyakan: ..."
- "Coba tanyakan: ..."
```

## MATA UANG VS HITUNGAN (SANGAT PENTING)
- **IDENTIFIKASI MATA UANG (WAJIB)**: Saat memanggil `execute_query`, Anda **WAJIB** mengidentifikasi semua kolom yang berisi nilai uang dan memasukannya ke dalam parameter `currency_columns`. Hal ini sangat penting agar sistem dapat menampilkan simbol "Rp" secara otomatis. Kolom yang biasanya berupa uang: total, netto, dpp, harga, biaya, laba, profit, ongkir, pajak, diskon.
- **RUPIAH (IDR)**: Gunakan "Rp" hanya untuk nilai moneter/uang (contoh: total_netto, total_dpp, hpp, laba, harga, biaya, nominal).
- **ANGKA MURNI (HITUNGAN)**: JANGAN gunakan sapaan "Rp" untuk jumlah entitas (contoh: jumlah cabang, jumlah faktur/nota, jumlah pelanggan, jumlah barang/unit). Tampilkan sebagai angka biasa (misal: 91, bukan Rp91).
- Dalam jawaban teks, format mata uang seperti: `Rp 1.250.000`.
- **ATURAN KETAT**: Dalam blok JSON (`chart` atau `smart_table`), SELALU gunakan angka murni (contoh: `5000000`).

## PANDUAN TABEL
- **Penjualan rinci (BERAT)**: `view_data_penjualan_rinci_mbi`
- **Ringkasan penjualan (CEPAT)**: `view_data_ssr_mbi`
- **Unit pelanggan**: `view_master_pelanggan_unit_mbi` 
- **Master barang**: `view_master_barang_mbi`
- **Master Master**: `view_master_cabang_mbi`, `view_master_pelanggan_mbi`, `view_master_barang_mbi`.


## TABEL YANG DAPAT DIAKSES
{$tableList}

Jawab SEPENUHNYA dalam BAHASA INDONESIA yang FORMAL dan PROFESIONAL.
PROMPT;
    }

    // ── Build messages ────────────────────────────────────────────────────────
    private function buildMessages(string $systemPrompt, array $history, string $userMessage, string $lang): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'system', 'content' => $lang === 'en'
                ? 'You MUST respond in ENGLISH only.'
                : 'Anda HARUS menjawab dalam BAHASA INDONESIA saja.'],
        ];

        foreach (array_slice($history, -($this->maxHistory * 2)) as $turn) {
            if (in_array($turn['role'] ?? '', ['user', 'assistant'])) {
                $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    // ── Ekstrak chart dari content dan tambahkan ke tool_results ─────────────
    private function processContentForCharts(string $content, array &$toolResults): string
    {
        // For now, we don't modify the content - we let the full chart JSON stay in content
        // The frontend renderer will handle adding charts to toolResults automatically
        // This function is kept for future use if needed
        
        // Just return content as-is - charts will be handled by frontend renderer
        return $content;
    }

    // ── Ekstrak history untuk frontend ────────────────────────────────────────
    private function extractClientHistory(array $messages): array
    {
        $history = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            if ($role === 'user' && !empty($msg['content'])) {
                $history[] = ['role' => 'user', 'content' => $msg['content']];
            }
            elseif ($role === 'assistant' && !empty($msg['content'])) {
                $history[] = ['role' => 'assistant', 'content' => $msg['content']];
            }
        }
        return array_slice($history, -($this->maxHistory * 2));
    }

    // ── Export Table/Chart to Excel ───────────────────────────────────────────
    public function exportExcel(Request $request)
    {
        $request->validate([
            'headers' => 'required|array',
            'rows' => 'required|array',
            'filename' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'chartInfo' => 'nullable|array',
        ]);

        $headers = $request->input('headers');
        $rows = $request->input('rows');
        $filename = $request->input('filename', 'export-' . date('Y-m-d_His') . '.xlsx');
        $title = $request->input('title', 'Data Export');
        $chartInfo = $request->input('chartInfo');

        // Increase time and memory limits for large exports
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '1024M'); // Match server's max for large datasets

        try {
            // Clear any previous output buffers to prevent corruption
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Create export instance - pass data as-is, no manipulation
            $export = new ChatTableExport(
                $headers,
                $rows,
                strtoupper($title),
                $chartInfo
            );

            return Excel::download($export, $filename);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Excel export failed: ' . $e->getMessage(), [
                'rows_count' => count($rows),
                'headers_count' => count($headers),
                'filename' => $filename,
                'exception' => get_class($e),
            ]);

            // Return user-friendly error message
            return response()->json([
                'error' => 'Export gagal: ' . $e->getMessage(),
                'message' => 'Data terlalu besar atau terjadi kesalahan saat memproses export. Silakan coba dengan data yang lebih kecil atau hubungi administrator.',
            ], 500);
        }
    }

    // ── Export Table/Chart to PDF ────────────────────────────────────────────
    public function exportPdf(Request $request)
    {
        $request->validate([
            'headers' => 'required|array',
            'rows' => 'required|array',
            'filename' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'chartImage' => 'nullable|string', // base64 chart image
        ]);

        $headers = $request->input('headers');
        $rows = $request->input('rows');
        $filename = $request->input('filename', 'export-' . date('Y-m-d_His') . '.pdf');
        $title = $request->input('title', 'Data Export');
        $chartImage = $request->input('chartImage');

        // Increase time limit for large exports
        set_time_limit(600);

        try {
            // Format headers (same as Excel export)
            $formattedHeaders = array_map(function($header) {
                $formatted = str_replace(['_', '-'], ' ', $header);
                return mb_convert_case($formatted, MB_CASE_TITLE, 'UTF-8');
            }, $headers);

            // Detect column types for alignment
            $columnTypes = [];
            foreach ($headers as $i => $header) {
                $headerName = strtolower($header);
                if (preg_match('/(id|no|telepon|phone|nik|faktur|polis|rangka|mesin|periode|bulan|tahun|nama|alamat|cabang|merek|model|tipe|kode|sku|ref)/i', $headerName)) {
                    $columnTypes[$i] = 'text';
                } elseif (preg_match('/(sales|amount|harga|netto|dpp|gpn|cogs|hpp|saldo|growth|realisasi|target|pencapaian)/i', $headerName)) {
                    $columnTypes[$i] = 'currency';
                } else {
                    $columnTypes[$i] = 'number';
                }
            }

            $pdf = Pdf::loadView('exports.pdf-table', [
                'title' => strtoupper($title),
                'generatedAt' => date('d M Y H:i'),
                'headers' => $formattedHeaders,
                'rows' => $rows,
                'columnTypes' => $columnTypes,
                'chartImage' => $chartImage,
            ]);

            $pdf->setPaper('a4', 'landscape');

            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('PDF export failed: ' . $e->getMessage(), [
                'rows_count' => count($rows),
                'headers_count' => count($headers),
                'filename' => $filename,
                'exception' => get_class($e),
            ]);

            return response()->json([
                'error' => 'Export gagal: ' . $e->getMessage(),
                'message' => 'Data terlalu besar atau terjadi kesalahan saat memproses export.',
            ], 500);
        }
    }
}
