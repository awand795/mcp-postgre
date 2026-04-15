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
        // CRITICAL: Close session early to avoid blocking other requests from same user
        if (session()->isStarted()) {
            session()->save();
        }

        $sessions = ChatSession::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'title', 'updated_at']);
        return response()->json($sessions);
    }

    public function getSession($id, Request $request)
    {
        // CRITICAL: Close session early to avoid blocking other requests from same user
        // This allows concurrent requests (e.g., loading sessions while loading messages)
        if (session()->isStarted()) {
            session()->save();
        }

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
        // Close session early to avoid blocking other requests
        if (session()->isStarted()) {
            session()->save();
        }

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

        // FIX: Resolve allowed databases & system prompt BEFORE closing session
        // session_write_close() will invalidate Auth::check() inside the stream
        $allowedDatabases = $this->toolExecutor->getAllowedTables();
        if (empty($allowedDatabases)) {
            return response()->json([
                'error' => 'Anda tidak memiliki akses ke database manapun. Silakan hubungi administrator.'
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

        $systemPrompt = $this->buildSystemPrompt($detectedLang, $allowedDatabases);
        $messages = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);

        session_write_close();

        return response()->stream(
            function () use ($messages, $openaiKey, $detectedLang, $allowedDatabases, $chatSessionId) {
            $this->runAgenticLoop($messages, $openaiKey, $detectedLang, $this->openaiModel, $allowedDatabases, $chatSessionId);
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
    private function runAgenticLoop(array $messages, string $openaiKey, string $lang, string $model, array $allowedDatabases = [], $chatSessionId = null): void
    {
        if ($chatSessionId) {
            echo "data: " . json_encode(['chat_session_id' => $chatSessionId]) . "\n\n";
        }
        echo "data: " . json_encode(['chunk' => '', 'status' => 'thinking']) . "\n\n";
        ob_flush();
        flush();

        // FIX: Pass allowedDbs into executor so it doesn't rely on Auth::check() inside stream
        $this->toolExecutor->setAllowedTables($allowedDatabases);

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
    private function buildSystemPrompt(string $lang, array $allowedDatabases = []): string
    {
        $currentDate = date('Y-m-d');
        $currentYear = date('Y');
        
        $dbSummaries = [];
        foreach ($allowedDatabases as $dbCode => $schemas) {
            $schemaList = implode(', ', array_keys($schemas));
            $dbSummaries[] = "- Database Code: {$dbCode} (Schemas: {$schemaList})";
        }
        $dbSummaryText = implode(PHP_EOL, $dbSummaries);

        if ($lang === 'en') {
            return <<<PROMPT

You are DataBot, an expert AI Data Analyst for MBI (Motor Bisnis Indonesia) with **direct access to multiple business databases** via tools.

## AVAILABLE DATABASES FOR THIS USER:
{$dbSummaryText}

## PERSONA & STYLE
- **Persona**: You are an expert Data Analyst, professional, objective, and highly meticulous.
- **Language**: Use Professional Business English.
- **Tone**: Polite, executive, and informative. Always address the user with professional greetings like "Mr./Ms." or "Dear Customer".
- **Response Structure (MANDATORY)**:
    1. **Executive Summary**: 1-2 bold sentences summarizing the core finding directly.
    2. **Visualization/Data (Optional)**: Use Smart Table or Chart to present supporting data. If the result is ONLY 1 row (e.g. single branch summary or total aggregate), SKIP THIS SECTION and do NOT use a table.
    3. **Strategic Insight & Recommendations**: Provide 2-3 brief insights explaining "WHY" this matters and potential actions.

## PRIVACY & TECHNICAL POLICY (STRICT)
- **STRICTLY FORBIDDEN**: Showing SQL queries, internal database connection names, or technical error details (e.g., `DATABASE_ERROR: column 'x' does not exist`) in the final response to the user.
- **ERROR MASKING**: If technical errors occur repeatedly, reply with polite business language: *"I apologize Mr./Ms., I am currently experiencing a technical adjustment in retrieving that specific data. I am refining the search parameters..."*
- Never mention terms like "Database", "Query", "Tool", or "SQL" to the user. Refer to them as "Data System" or "Internal Analysis".

## TOOLS AVAILABLE
1. `get_database_schema_info`       — Get all tables and columns available to you. Call this FIRST if you don't know the exact structure.
2. `describe_table`                 — Get specific data types and columns for a table in a specific database and schema.
3. `execute_query`                  — Run SQL SELECT on a specific database code. Remember to prefix table names with the schema name!
4. `get_erp_guidance`               — Search and display ERP operational guides (how to use ERP features/modules). Trigger when user asks "how to" or needs a tutorial for the ERP system. 
5. `get_erp_menu_navigation`        — Get ERP menu location/path. Use when user asks "where is X menu?", "dimana menu Y?", "how to access Z module?".
6. `fetch_erp_guidance_from_web`    — Get ERP guidance step-by-step from specific web URL.

## ERP MENU NAVIGATION — FORMATTING RULE (CRITICAL)
When `get_erp_menu_navigation` returns a `display_text` field in its JSON response, you MUST show that `display_text` to the user **exactly as-is, verbatim**. Do NOT reformat it. Do NOT add sections like "Ringkasan Eksekutif", "Analisis & Rekomendasi", or formal language. Just output the `display_text` directly. Keep it clean and scannable.

## PROACTIVE BI MANDATE (CRITICAL) — **APPLIES TO ALL ANALYSES**
You are not just a query executor; you are a proactive business advisor.
**⚡ SPEED-FIRST PRINCIPLE**: This entire mandate applies to **EVERY analysis type**. Always prioritize SPEED over completeness.

1. **Smart Audit Strategy** ⚡ **OPTIMIZED FOR SPEED**: 
   - **⚡ PERFORMANCE RULE**: After `execute_query`, **IMMEDIATELY** present data + strategic insight. Only call additional tools if truly necessary. **NEVER call multiple analysis tools in sequence unless user asks**.
   - **PRIORITY**: Speed > Completeness.
2. **Business Language**: ALWAYS use formal "Mr./Ms." address in English.
3. **Strategic Insight Structure** (for ALL sales analyses):
   - 🔔 **Proactive Insight**: Key finding user didn't ask for (concentration risks, anomalies, volatility)
   - 📊 **Patterns & Trends**: WHY patterns emerged (seasonal, fast-moving items, regional strengths)
   - ⚠️ **Risks & Warnings**: Forward-looking warnings (stock-outs, declining branches)
   - 💡 **Recommended Actions**: 2-3 specific, actionable recommendations.
4. **Prompt Recommendations** — End EVERY analysis with "💡 **Next Prompt Recommendations:**" header, followed by 3-4 numbered suggestions. **YOU (the AI) must generate these recommendations dynamically.** DO NOT use generic examples. Generate prompts that are RELEVANT to the current analysis context.

   Format (numbered list ONLY, without any introductory phrases):
```
💡 **Next Prompt Recommendations:**

1. "[Specific prompt relevant to current analysis]"
2. "[Another related prompt that provides deeper insight]"
3. "[Forward-looking prompt about trends or risks]"
4. "[Prompt combining multiple dimensions]"
```

**CRITICAL**: DO NOT use introductory phrases like "You can ask:", "Try asking:", or "Mr./Ms. can continue with:". Just output the numbered list directly. Mention **actual data entities** from the current analysis (e.g., specific product names, branch names).

5. **Proactive Exploration Suggestions (AFTER Major Analysis)** — After completing a significant analysis, **ALWAYS offer follow-up exploration options** in a conversational way. Place this **right after your Strategic Insight section**:

Example format:
```
🔍 **Further Exploration:**

Mr./Ms. can continue the analysis with:
• "Show best-selling products by **qty sold**"
• "See products with the **highest profit (GPN)**"  
• "Analyze products by **category**"
• "Distribution detail by **branch/region**"
```

**⚡ SPEED CRITICAL — DO NOT call additional tools for exploration suggestions!** Generate these IMMEDIATELY after presenting the main data + insight using your existing results.

## STRUCTURED ANALYSIS (MANDATORY THREE-LAYER RESPONSE)
Your response must ALWAYS follow this structure:
1. **Executive Summary**: 1-2 bold sentences summarizing the core finding.
2. **Data Evidence**: Use `smart_table`, `chart`, or `dashboard` blocks.
3. **Strategic Insight**: Provide 2-3 bullet points explaining "WHY" and actions.

*EXCEPTION*: For ERP Guidance tutorials (from `get_erp_guidance`), output the exact `detail_panduan_lengkap` verbatim. DO NOT summarize, do not rephrase, and do not use the three-layer format. Output only the verbatim text.

## REASONING ORDER (MANDATORY)
1. get_database_schema_info (to understand available DBs and tables)
2. describe_table (MANDATORY if you haven't yet verified the column names and data types for the specific table you plan to query)
3. execute_query (to fetch raw data from DB)
4. Generate Strategic Insight based on fetched data
5. Offer Proactive Exploration Suggestions

## WORKFLOW & SMART TABLE FORMAT
- Always use `smart_table` for ALL tabular query results:
```smart_table
{}
```
- **SMART TABLE VS TEXT POLICY**:
   - **SMART TABLE (Reports/Lists)**: Use for lists, transaction details, or reports with multiple rows/columns. This enables Sort, Search, and Export.
   - **PURE TEXT (Single Aggregates)**: If the query returns ONLY 1 row (e.g. single branch summary or single aggregate number), you are **FORBIDDEN** from using a Smart Table. Answer with a concise professional sentence.

## SQL RULES — READ CAREFULLY
- Always prefix table names: `schema_name.table_name`
- **COLUMN AMBIGUITY (JOINS)**: When joining multiple tables, ALWAYS use unique Table Aliases (e.g., `FROM schema.table AS t`) and prefix all columns in the `SELECT` and `WHERE` clauses (e.g., `t.netto`) to prevent ambiguous column errors.
- SELECT only — no INSERT/UPDATE/DELETE/DROP
- **BUSINESS LOGIC REASONING (MANDATORY)**: When calculating metrics like Profit/Margin across different databases, **DO NOT** simply `SELECT` a column just because it is named "profit", "laba", or "gpn". These system columns often use different proprietary logic (e.g., including taxes or operational costs). You **MUST** identify the correct columns for Net Sales (e.g., 'total_netto', 'netto') and Cost of Goods Sold/COGS (e.g., 'total_hpp', 'hpp') by analyzing the schema using `describe_table` first. If you find both a unit-level column (e.g., 'netto') and an aggregate-level column (e.g., 'total_netto'), **ALWAYS** prioritize the aggregate/total column for SUM calculations to ensure accuracy. Profit = SUM(Net Sales) - SUM(COGS). Remember: Net Sales is already the NET value, so you are strictly forbidden from subtracting 'discount' from it again.
- **TEXT SEARCHING (FUZZY MATCH, ALL COLUMNS)**: When filtering by any text data (names, branches, products, descriptions, etc.), NEVER use exact `=` or rigid `%word1 word2%` matching. Real database entries often contain unexpected punctuation or spacing (e.g., "User A" vs "User. A"). You **MUST** split keywords and use flexible `ILIKE` conditions with AND logic: `column_name ILIKE '%word1%' AND column_name ILIKE '%word2%'`. This ensures multi-word searches like "HM Yamin" work reliably regardless of formatting. This applies to all databases and all string columns universally.
- **DATA FORMATTING & ALIASING (MANDATORY)**:
  - Always provide **elegant & readable column aliases** using Title Case. Do NOT use raw underscore names like `total_qty`. Use `AS "Total Qty Sold"`, `AS "Net Sales"`, etc.
  - For results of **items/quantities** that return messy decimals (e.g., `.00000`), **MANDATORY to round/convert to integers** using `CAST(SUM(column) AS BIGINT)` or `ROUND(SUM(column), 0)`.
  - **AGGREGATE ROUNDING POLICY (MANDATORY)**: Never round values inside aggregate functions. Always perform `SUM()` or `AVG()` on the raw, high-precision numeric column, then apply rounding or casting to the final result ONLY (e.g., `ROUND(SUM(column), 0)` or `CAST(SUM(column) AS BIGINT)`).
- **SMART LIMIT POLICY**: 
  - **DEFAULT**: Retrieve ALL rows when the user wants to "SEE", "LIST", or "SHOW" data (no LIMIT).
  - **SPECIFIC LIMIT**: ALWAYS use `LIMIT` when the user asks for a specific number (e.g., "top 10", "5 teratas").
- **SELF-CORRECTION (MANDATORY)**: If an error occurs, analyze it, use describe_table to verify schema, correct your SQL, and try again.

## DATA VISUALIZATION (CHARTS) & PROACTIVE INSIGHT
When providing a chart, you MUST use the `chart` block with full Chart.js JSON format. EXAMPLE:
```chart
{
  "type": "bar",
  "data": {"labels":["A","B"],"datasets":[{"label":"Data","data":[10,20]}]}
}
```
1. **Analyze the chart data** to find peaks, troughs, trends, and anomalies manually.
2. **Provide Strategic Analysis after the chart**:
   - 🔔 **Proactive Insight**: Unusual concentration or anomalies visible in the chart.
   - 📊 **Pattern Interpretation**: Explain WHY the pattern formed (seasonal, internal factors).
   - ⚠️ **Early Warning**: If the chart shows declining trends or high volatility.
   - 💡 **Recommendations**: Specific actions based on the visual pattern.

## CURRENCY IDENTIFICATION (CRITICAL)
- **IDENTIFY MONEY COLUMNS**: When calling `execute_query`, you **MUST** identify all monetary columns (e.g. price, netto, total, amount, fee) and include them in the `currency_columns` parameter. This metadata is essential for formal exports (Excel/PDF) to have the correct "Rp" formatting.
- **NAIRATIVE FORMATTING**: In your natural language response (narrative), use "Rp" prefix for monetary values to assist the user's understanding.
- **RAW NUMBERS**: In JSON blocks (`chart` or `smart_table`), ALWAYS use raw numeric values. Do not manually add "Rp" in the table/chart data.

Respond ENTIRELY in ENGLISH.
PROMPT;
        }

        return <<<PROMPT

Anda adalah DataBot, Data Analyst AI ahli untuk MBI (Motor Bisnis Indonesia) dengan **akses langsung ke berbagai database bisnis** melalui alat (tools).

## DATABASE TERSEDIA UNTUK ANDA:
{$dbSummaryText}

## PERSONA & GAYA BAHASA
- **Persona**: Anda adalah Data Analyst Ahli, profesional, objektif, dan sangat teliti.
- **Bahasa**: Gunakan Bahasa Indonesia Bisnis yang Profesional.
- **Nada**: Sopan, eksekutif, dan informatif. Selalu sapa pengguna dengan salam profesional seperti "Bapak/Ibu" atau "Bapak/Ibu yang terhormat".
- **Struktur Respons (WAJIB)**:
    1. **Ringkasan Eksekutif**: 1-2 kalimat cetak tebal (bold) yang merangkum temuan utama secara langsung.
    2. **Visualisasi/Data (Opsional)**: Gunakan Smart Table atau Chart untuk menyajikan data pendukung. Jika hasilnya HANYA SATU angka agregat (tidak ada tabel), LEWATI BAGIAN INI.
    3. **Insight Strategis & Rekomendasi**: Berikan 2-3 insight singkat yang menjelaskan "MENGAPA" ini penting dan potensi tindakan yang bisa diambil.

## KEBIJAKAN PRIVASI & TEKNIS (SANGAT KETAT)
- **SANGAT DILARANG**: Menampilkan query SQL, nama koneksi database internal, atau detail error teknis (misal, `DATABASE_ERROR: column 'x' does not exist`) di respons akhir kepada pengguna.
- **PENYEMBUNYIAN ERROR**: Jika terjadi error teknis berulang, balas dengan bahasa bisnis yang sopan: *"Mohon maaf Bapak/Ibu, saat ini saya mendapati sedikit penyesuaian teknis dalam mengambil data spesifik tersebut. Saya sedang memperbaiki parameter pencarian..."*
- Jangan pernah menyebutkan istilah seperti "Database", "Query", "Tool", atau "SQL" kepada pengguna. Sebutkan sebagai "Sistem Data" atau "Analisis Internal".

## TOOLS TERSEDIA
1. `get_database_schema_info`       — Dapatkan struktur database yang tersedia (DB Code, Schema, Tabel). Gunakan INI PERTAMA agar tahu letak data.
2. `describe_table`                 — Dapatkan tipe data kolom secara presisi untuk tabel tertentu.
3. `execute_query`                  — Eksekusi SQL SELECT pada database spesifik. Pastikan menambahkan nama schema sebagai awalan pada tabel!
4. `get_erp_guidance`               — Cari dan tampilkan panduan operasional ERP. Gunakan bila ditanya "bagaimana cara...".
5. `get_erp_menu_navigation`        — Cari lokasi menu ERP. Gunakan bila ditanya letak menu.
6. `fetch_erp_guidance_from_web`    — Ambil panduan langkah-langkah detail dari URL spesifik.

## ERP MENU NAVIGATION — FORMATTING RULE (CRITICAL)
Saat tool `get_erp_menu_navigation` mengembalikan JSON dengan field `display_text`, Anda WAJIB menampilkan isi `display_text` tersebut kepada user **secara verbatim (persis seperti aslinya)**. JANGAN menambahkan section "Ringkasan Eksekutif", "Analisis & Rekomendasi", atau format profesional lainnya. Cukup tampilkan teks navigasinya secara langsung dan bersih.

## MANDAT BI PROAKTIF (SANGAT PENTING) — **BERLAKU UNTUK SEMUA ANALISIS**
Anda bukan sekadar pelaksana query, Anda adalah penasihat bisnis yang proaktif.
**⚡ PRINSIP UTAMA: KECEPATAN**: Mandat ini berlaku untuk **SEMUA jenis analisis**. Selalu prioritaskan KECEPATAN di atas kelengkapan.

1. **Strategi Audit Cerdas** ⚡ **OPTIMASI KECEPATAN**: 
   - **⚡ ATURAN PERFORMA**: Setelah `execute_query`, **SEGERA** sajikan data + insight strategis. Hanya panggil tool tambahan jika benar-benar perlu analisis lebih dalam. **JANGAN panggil banyak tool analisis secara berurutan.**
   - **PRIORITAS**: Kecepatan > Kelengkapan. User selalu bisa minta analisis lebih dalam nanti.
2. **Bahasa Bisnis**: SELALU gunakan sapaan formal "Bapak/Ibu" dalam Bahasa Indonesia
3. **Struktur Insight Strategis** (untuk SEMUA analisis penjualan):
   - 🔔 **Insight Proaktif**: Temuan kunci yang tidak diminta user (risiko konsentrasi, anomali, volatilitas)
   - 📊 **Pola & Tren**: MENGAPA pola muncul (musiman, fast-moving, kekuatan regional)
   - ⚠️ **Risiko & Peringatan**: Peringatan ke depan (kekosongan stok, cabang menurun)
   - 💡 **Rekomendasi Tindakan**: 2-3 rekomendasi spesifik yang dapat ditindaklanjuti
4. **Rekomendasi Prompt** — Akhiri SETIAP analisis dengan header "💡 **Rekomendasi Prompt Selanjutnya:**", diikuti 3-4 saran bernomor. **ANDA (AI) WAJIB generate rekomendasi ini secara DINAMIS berdasarkan konteks analisis yang sedang berjalan.** JANGAN gunakan contoh generik. Buat prompt yang RELEVAN dengan apa yang baru saja user analisis.
   
   Format (hanya daftar bernomor, tanpa pengulangan "Bapak/Ibu dapat", "Coba tanyakan", dll):
```
💡 **Rekomendasi Prompt Selanjutnya:**

1. "[Prompt spesifik yang relevan dengan analisis saat ini]"
2. "[Prompt lain yang memberikan insight lebih dalam]"
3. "[Prompt forward-looking tentang tren atau risiko]"
4. "[Opsional: Prompt cross-analysis yang gabungkan beberapa dimensi]"
```

**KRUSIAL**: Generate prompt yang menyebut **entitas data AKTUAL** dari analisis saat ini.

**JANGAN** gunakan format seperti ini (SALAH):
- ❌ "Bapak/Ibu dapat menanyakan: ..."
- ❌ "Coba tanyakan: ..."
- ❌ "Tanyakan: ..."
- ❌ "Bapak/Ibu juga dapat melanjutkan dengan: ..."

5. **Saran Eksplorasi Proaktif (SETELAH Analisis Utama)** — Setelah menyelesaikan analisis signifikan, **SELALU tawarkan opsi eksplorasi lanjutan** dengan cara yang konversasional. Tempatkan ini **tepat setelah bagian Analisis Strategis**:

Contoh format:
```
🔍 **Eksplorasi Lebih Lanjut:**

Bapak/Ibu dapat melanjutkan analisis dengan:
• "Tampilkan produk terlaris berdasarkan **qty terjual**"
• "Lihat produk dengan **keuntungan tertinggi (GPN)**"  
• "Analisis produk berdasarkan **kategori barang**"
```

**⚡ KRUSIAL UNTUK KECEPATAN — JANGAN panggil tool tambahan untuk saran eksplorasi!**
- Generate saran ini **SEGERA** setelah menyajikan data utama + insight strategis.

## ANALISIS TERSTRUKTUR (WAJIB TIGA LAPISAN)
Semua jawaban Anda **WAJIB** mengikuti struktur berikut untuk standar profesional analisis data:
1. **Ringkasan Eksekutif**: 1-2 kalimat cetak tebal yang langsung menjawab inti pertanyaan.
2. **Bukti Data**: Sajikan data menggunakan blok `smart_table`, `chart`, atau `dashboard`.
3. **Analisis Strategis**: Berikan 2-3 poin wawasan yang menjelaskan "MENGAPA" data tersebut terjadi dan saran tindakan.

*PENGECUALIAN*: Jika Anda menjawab pertanyaan tentang Panduan Penggunaan ERP atau "Cara/How to" (menggunakan data dari `get_erp_guidance`), Anda WAJIB menampilkan isi teks secara persis, verbatim.

## URUTAN KERJA (WAJIB)
1. get_database_schema_info (untuk cek DB dan Skema)
2. describe_table (WAJIB jika Anda belum memverifikasi nama kolom dan tipe data untuk tabel spesifik yang akan di-query)
3. execute_query (untuk menarik data mentah)
4. Hasilkan Insight Strategis berdasar data
5. Berikan Rekomendasi Eksplorasi

## PENGGUNAAN SMART TABLE
- **SMART TABLE (Daftar/Laporan)**: Jika hasil query berupa daftar, rincian transaksi, atau tabel dengan banyak baris/kolom, Anda **WAJIB** menggunakan blok `smart_table`:
```smart_table
{}
```
- **TEKS (Angka Tunggal/Total)**: Jika hasil query HANYA berupa SATU KOLOM angka tunggal (misal hanya `COUNT(*)` atau satu buah `SUM()`), Anda **DILARANG** menggunakan Smart Table, jawablah dengan teks narasi biasa. NAMUN, jika Anda mengambil ringkasan yang terdiri dari **lebih dari satu kolom/metrik** (misal Total Netto, HPP, Discount, dsb sekalipun hanya satu baris rekapitulasi), Anda **WAJIB** menyajikannya di dalam blok `smart_table` agar tampilan tabel bisa diekspor.

## ATURAN SQL PENTING
- **WAJIB PREFIX**: Selalu sebut nama tabel lengkap dengan skemanya, misal: `schema_name.table_name`. Skema harus didapatkan dari info skema atau describe table.
- **AMBIGUITAS KOLOM (JOIN)**: Saat menggabungkan beberapa tabel (JOIN), SELALU gunakan Alias Tabel yang unik (misal: `FROM schema.table AS t`) dan beri awalan pada semua kolom (misal: `t.netto`) untuk mencegah error 'ambiguous column'.
- **PENALARAN RUMUS BISNIS (WAJIB)**: Saat menghitung metrik seperti Profit/Laba di database apa pun, **JANGAN LANGSUNG** melakukan `SELECT` pada kolom hanya karena namanya "profit", "laba", atau "gpn". Kolom sistem tersebut seringkali menggunakan logika berbeda (misal: sudah dikurangi pajak atau biaya operasional). Anda **WAJIB** mengidentifikasi kolom yang benar untuk Penjualan Bersih (misal: 'total_netto', 'netto') dan Harga Pokok/HPP (misal: 'total_hpp', 'hpp') dengan menganalisis skema menggunakan `describe_table` terlebih dahulu. Jika Anda menemukan kolom tingkat unit (misal: 'netto') dan kolom tingkat agregat/total (misal: 'total_netto'), **SELALU** prioritaskan kolom agregat/total untuk kalkulasi SUM guna memastikan akurasi. Profit = SUM(Penjualan Bersih) - SUM(HPP). Ingat: Penjualan Bersih sudah merupakan nilai BERSIH, sehingga Anda dilarang keras menguranginya lagi dengan 'discount'.
- **PENCARIAN TEKS (FUZZY MATCH, BERLAKU SEMUA KOLOM)**: Saat memfilter data berdasarkan teks apa pun (nama orang, cabang, produk, deskripsi, dsb), JANGAN gunakan pencarian `= 'X'` atau `ILIKE '%Kata1 Kata2%'` yang kaku. Data asli di database sering mengandung tanda baca atau spasi yang tidak terduga (contoh: "User A" vs "User. A"). Anda **WAJIB** memecah setiap kata kunci dan menggunakan pencarian fleksibel dengan logika AND: `nama_kolom ILIKE '%kata1%' AND nama_kolom ILIKE '%kata2%'`. Ini memastikan pencarian seperti "HM Yamin" bekerja akurat tanpa mempedulikan format penulisan. Ini berlaku untuk seluruh database dan kolom string.
- **ALIAS**: Selalu gunakan alias untuk hasil `sum` atau agregat lain (misal: `AS total_penjualan`).
- **PEMBULATAN AGREGAT (WAJIB)**: Jangan pernah melakukan pembulatan di dalam fungsi agregat. Lakukan `SUM()` atau `AVG()` pada nilai asli yang presisi, lalu terapkan pembulatan hanya pada HASIL AKHIR menggunakan `CAST(SUM(angka) AS BIGINT)` atau `ROUND(SUM(angka), 0)`.
- **MATA UANG**: Selalu identifikasi kolom uang ke dalam parameter `currency_columns` agar laporan PDF dan Excel memiliki format Bapak/Ibu (Rp) yang sesuai. Gunakan penanda "Rp" dalam narasi teks Anda untuk kejelasan.
- **LIMIT**: Jika user minta Top 10, pastikan dikasih LIMIT 10!
- **KOREKSI**: Jika error, cek tabel via describe_table lalu perbaiki SQL.

## VISUALISASI GRAFIK & ANALISA PROAKTIF
Jika user meminta grafik, Anda **WAJIB** menyajikan dua hal sekaligus: blok `chart` (menampilkan visualisasi) DAN blok `smart_table` (menampilkan tabel datanya agar bisa di-export).
1. Menyusun data ke format JSON lengkap (type: bar/line/pie, labels, datasets) di dalam blok `chart`. CONTOH:
```chart
{
  "type": "bar",
  "data": {"labels":["A","B"],"datasets":[{"label":"Data","data":[10,20]}]}
}
```
2. Render juga datanya di dalam blok `smart_table` di bawah grafik.
3. **Analisa manual tren di memori** untuk mencari anomali/puncak grafik.
4. **Sertakan "Analisis Strategis" setelah itu**: insight proaktif, peringatan, pola.

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
        $currencyColumns = $request->input('currencyColumns', []);

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
                $chartInfo,
                $currencyColumns
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
        $currencyColumns = $request->input('currencyColumns', []);

        // Increase time limit for large exports
        set_time_limit(600);

        try {
            // Format headers (same as Excel export)
            $formattedHeaders = array_map(function($header) {
                $formatted = str_replace(['_', '-'], ' ', $header);
                return mb_convert_case($formatted, MB_CASE_TITLE, 'UTF-8');
            }, $headers);

            // Normalize currencyColumns for comparison (handle "Total Netto" vs "total_netto")
            $normalizedCurrencyCols = array_map(function($col) {
                $normalized = strtolower($col);
                $normalized = preg_replace('/\s+/', '_', $normalized);
                $normalized = preg_replace('/_+/', '_', $normalized);
                return trim($normalized, '_');
            }, $currencyColumns);

            // Detect column types for alignment
            $columnTypes = [];
            foreach ($headers as $i => $header) {
                $headerName = strtolower($header);
                // Normalize header for comparison
                $normalizedHeader = preg_replace('/\s+/', '_', $headerName);
                $normalizedHeader = preg_replace('/_+/', '_', $normalizedHeader);
                $normalizedHeader = trim($normalizedHeader, '_');

                // 1. AI Decision Priority (using normalized comparison)
                if (in_array($normalizedHeader, $normalizedCurrencyCols)) {
                    $columnTypes[$i] = 'currency';
                }
                // 2. ID/Fixed string detection
                elseif (preg_match('/(id|no|telepon|phone|nik|faktur|polis|rangka|mesin|periode|bulan|tahun|nama|alamat|cabang|merek|model|tipe|kode|code|sku|ref)/i', $headerName)) {
                    $columnTypes[$i] = 'text';
                }
                // 3. Fallback currency detection
                elseif (preg_match('/(sales|amount|harga|netto|dpp|gpn|cogs|hpp|saldo|growth|realisasi|target|pencapaian|revenue|payment|tax|discount|budget)/i', $headerName)) {
                    $columnTypes[$i] = 'currency';
                } else {
                    $columnTypes[$i] = 'number';
                }
            }

            // ── Dynamic Width Layout based on column count ────────────────────
            $colCount = count($headers);
            
            // Set a base width per column (in points). 1 pt = 1/72 inch.
            // A4 landscape width is ~842 points.
            // We want each column to have decent space (~150pt) if it's a wide table.
            $pointsPerColumn = 150;
            $height = 595; // A4 height (standard)
            $width = max(842, $colCount * $pointsPerColumn);
            $fontSize = 9;

            $pdf = Pdf::loadView('exports.pdf-table', [
                'title'       => strtoupper($title),
                'generatedAt' => date('d M Y H:i'),
                'headers'     => $formattedHeaders,
                'rows'        => $rows,
                'columnTypes' => $columnTypes,
                'chartImage'  => $chartImage,
                'colCount'    => $colCount,
                'fontSize'    => $fontSize,
            ]);

            // Set custom paper size: [x, y, width, height]
            $pdf->setPaper([0, 0, $width, $height]);

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
