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
                $allowedDatabases[$c->code] = ['*' => ['*']];
            }
        } elseif ($user->roleModel) {
            if (method_exists($user->roleModel, 'getAllowedDatabases')) {
                $allowedDatabases = $user->roleModel->getAllowedDatabases();
            } else {
                foreach ($user->roleModel->permissions ?? [] as $perm) {
                    $db = $perm->database_code;
                    $schema = $perm->schema_name;
                    $tbl = $perm->table_name;

                    if ($db === '*') {
                        $conns = \App\Models\DatabaseConnection::active()->get();
                        foreach ($conns as $c) {
                            $allowedDatabases[$c->code] = ['*' => ['*']];
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
                        // Allow all tables in this specific schema
                        $allowedDatabases[$db][$schemaKey][] = '*';
                    }
                }
            }
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

        $systemPrompt = $detectedLang === 'en' 
            ? $this->buildSystemPrompt($allowedDatabases)
            : $this->buildSystemPromptId($allowedDatabases);

        $messages = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);
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
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ]
        );
    }

    private function runAgenticLoop(array $messages, $apiKey, string $lang, $model, array $allowedDatabases = [], $chatSessionId = null, $maxTokens = null): void
    {
        // Extract system prompt to be passed explicitly to providers
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
        $tools = ToolCallExecutor::getToolDefinitions();
        $loopCount = 0;
        $allTurnToolResults = [];

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            
            // Gunakan log resmi Laravel agar bisa dilihat di storage/logs/laravel.log
            Log::info("[Agentic] Loop #{$loopCount} - Model: " . $model->model_name);

            try {
                $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens, $systemPrompt);
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
            $toolCalls = $assistantMsg['tool_calls'] ?? [];
            $textContent = $assistantMsg['content'] ?? '';

            $messages[] = $assistantMsg;

            if (empty($toolCalls) || in_array($finishReason, ['stop', 'end_turn'])) {
                $finalContent = trim($textContent);
                if (empty($finalContent)) {
                    $finalContent = "Mohon maaf, sistem tidak memberikan respon. Silakan coba pertanyaan lain.";
                }

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
                if (ob_get_level() > 0) ob_flush(); flush();
                return;
            }

            foreach ($toolCalls as $toolCall) {
                $toolCallId = $toolCall['id'] ?? ('call_' . uniqid());
                $toolName = $toolCall['function']['name'] ?? '';
                $argsRaw = $toolCall['function']['arguments'] ?? '{}';
                $arguments = is_string($argsRaw) ? (json_decode($argsRaw, true) ?? []) : $argsRaw;

                Log::info("[Agentic] Executing Tool: {$toolName}");
                $toolResult = $this->toolExecutor->execute($toolName, $arguments);
                
                $decodedRes = json_decode($toolResult, true);
                $aiContent = $toolResult;
                if (is_array($decodedRes) && isset($decodedRes['rows']) && count($decodedRes['rows']) > 50) {
                    $aiContent = json_encode([
                        'rows_returned' => count($decodedRes['rows']),
                        'columns'       => $decodedRes['columns'] ?? [],
                        'rows'          => array_slice($decodedRes['rows'], 0, 50),
                        'message'       => "Data truncated. Showing 50 rows."
                    ]);
                }

                echo "data: " . json_encode([
                    'tool_call' => [
                        'name'      => $toolName,
                        'arguments' => $arguments,
                        'status'    => 'success',
                        'result'    => ['tool_name' => $toolName, 'data' => $decodedRes ?: $toolResult]
                    ]
                ]) . "\n\n";
                if (ob_get_level() > 0) ob_flush(); flush();
                
                $allTurnToolResults[] = ['tool_name' => $toolName, 'data' => $decodedRes ?: $toolResult];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name' => $toolName,
                    'content' => $aiContent,
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
        $messages = ChatMessage::where('chat_session_id', $session->id)->orderBy('created_at', 'asc')->get();
        return response()->json([
            'session' => $session,
            'history' => $messages,
            'pagination' => ['has_more' => false, 'oldest_cursor' => null]
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

    private function callAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens = 32768, string $systemPrompt = ''): ?array
    {
        $providerCode = $apiKey->provider->code;
        $maxTokens = $maxTokens ?? 32768;
        
        // Prepare tools and messages specifically for this provider
        $formattedTools = $this->formatToolsForProvider($providerCode, $tools);
        $formattedMessages = $this->formatMessagesForProvider($providerCode, $messages);

        if ($providerCode === 'gemini') {
            return $this->callGeminiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        }

        if ($providerCode === 'claude') {
            return $this->callClaudeApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        }

        if ($providerCode === 'custom') {
            return $this->callCustomApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        }

        return $this->callOpenAiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
    }

    private function formatToolsForProvider(string $providerCode, array $tools): array
    {
        if (empty($tools)) return [];

        if ($providerCode === 'gemini') {
            // Gemini expects function_declarations without the 'type: function' wrapper
            $geminiTools = [];
            foreach ($tools as $t) {
                $f = $t['function'] ?? $t;
                $geminiTools[] = [
                    'name' => $f['name'],
                    'description' => $f['description'],
                    'parameters' => $f['parameters']
                ];
            }
            return [['function_declarations' => $geminiTools]];
        }

        if ($providerCode === 'claude') {
            $claudeTools = [];
            foreach ($tools as $t) {
                $f = $t['function'] ?? $t;
                $claudeTools[] = [
                    'name' => $f['name'],
                    'description' => $f['description'],
                    'input_schema' => $f['parameters']
                ];
            }
            return $claudeTools;
        }

        // OpenAI and Custom usually use standard tools array
        return $tools;
    }

    private function formatMessagesForProvider(string $providerCode, array $messages): array
    {
        if ($providerCode === 'gemini') {
            $geminiMessages = [];
            foreach ($messages as $m) {
                if ($m['role'] === 'system') continue; // Handled separately in callGeminiApi

                $role = $m['role'];
                $geminiRole = ($role === 'assistant') ? 'model' : (($role === 'tool' || $role === 'function') ? 'function' : 'user');
                
                $parts = [];
                if ($role === 'tool') {
                    $geminiRole = 'user'; // Gemini REST API uses 'user' for function results
                    $parts[] = [
                        'functionResponse' => [
                            'name' => $m['name'] ?? 'query',
                            'response' => (object)['content' => $m['content']]
                        ]
                    ];
                } else {
                    if (isset($m['content']) && !empty($m['content'])) {
                        $parts[] = ['text' => (string)$m['content']];
                    }
                    if ($role === 'assistant' && !empty($m['tool_calls'])) {
                        foreach ($m['tool_calls'] as $tc) {
                            $f = $tc['function'] ?? $tc;
                            $args = is_string($f['arguments']) ? json_decode($f['arguments'], true) : $f['arguments'];
                            $parts[] = [
                                'functionCall' => [
                                    'name' => $f['name'],
                                    'args' => (object)($args ?? [])
                                ]
                            ];
                        }
                    }
                }
                $geminiMessages[] = ['role' => $geminiRole, 'parts' => $parts];
            }
            return $geminiMessages;
        }

        if ($providerCode === 'claude') {
            $claudeMessages = [];
            foreach ($messages as $m) {
                if ($m['role'] === 'system') continue;
                $claudeMessages[] = [
                    'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $m['content']
                ];
            }
            return $claudeMessages;
        }

        return $messages;
    }

    private function callOpenAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = '')
    {
        $payload = [
            'model' => $model->model_name,
            'messages' => $messages,
            'max_tokens' => (int)$maxTokens,
            'temperature' => 0.7,
        ];
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        $response = Http::timeout(600)->retry(3, 2000)->withHeaders(['Authorization' => 'Bearer ' . $apiKey->api_key])
            ->post('https://api.openai.com/v1/chat/completions', $payload);
        
        return $this->handleProviderResponse($response, 'openai');
    }

    private function callCustomApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = '')
    {
        $baseUrl = $apiKey->provider->base_url ?: 'https://api.openai.com/v1/chat/completions';
        $payload = [
            'model' => $model->model_name,
            'messages' => $messages,
            'max_tokens' => (int)$maxTokens,
            'temperature' => 0.7,
        ];
        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }
        $response = Http::timeout(600)->retry(3, 2000)->withHeaders(['Authorization' => 'Bearer ' . $apiKey->api_key])
            ->post($baseUrl, $payload);
            
        return $this->handleProviderResponse($response, 'custom');
    }

    private function callClaudeApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = '')
    {
        // Minimal Claude implementation via HTTP
        $payload = [
            'model' => $model->model_name,
            'max_tokens' => (int)$maxTokens,
            'messages' => $messages,
            'system' => $systemPrompt,
        ];
        if (!empty($tools)) $payload['tools'] = $tools;

        $response = Http::timeout(600)->retry(3, 2000)->withHeaders([
            'x-api-key' => $apiKey->api_key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', $payload);

        return $this->handleProviderResponse($response, 'claude');
    }

    private function callGeminiApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = '')
    {
        $currentModelName = $model->model_name;
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $currentModelName . ':generateContent?key=' . $apiKey->api_key;

        $payload = [
            'contents' => $messages,
            'generationConfig' => [
                'maxOutputTokens' => (int)$maxTokens, 
                'temperature' => 0.7
            ],
        ];

        if (!empty($systemPrompt)) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = Http::timeout(600)->retry(3, 2000)->post($url, $payload);

        // --- FALLBACK LOGIC ---
        // If 503 occurs (Overloaded/Busy) after retries, try with a more stable model (1.5-flash)
        if ($response->status() === 503 && $currentModelName !== 'gemini-1.5-flash') {
            Log::warning("[Agentic] Model {$currentModelName} is busy (503). Falling back to gemini-1.5-flash for this turn.");
            $fallbackUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey->api_key;
            $response = Http::timeout(600)->retry(2, 2000)->post($fallbackUrl, $payload);
        }
        // ----------------------

        return $this->handleProviderResponse($response, 'gemini');
    }

    private function handleProviderResponse($response, string $providerCode): ?array
    {
        if ($response->status() === 429) { 
             // Handle rate limit
             return null; 
        }
        
        if ($response->failed()) {
            Log::error("[Agentic] API Error ({$providerCode}): " . $response->body());
            return null;
        }

        $data = $response->json();
        
        // Normalize different response formats into OpenAI-like format for the loop
        if ($providerCode === 'gemini') {
            $candidate = $data['candidates'][0] ?? null;
            if (!$candidate) return null;

            $parts = $candidate['content']['parts'] ?? [];
            $text = ''; $toolCalls = [];
            foreach ($parts as $p) {
                if (isset($p['text'])) $text .= $p['text'];
                if (isset($p['functionCall'])) {
                    $toolCalls[] = [
                        'id' => 'call_' . uniqid(),
                        'type' => 'function',
                        'function' => [
                            'name' => $p['functionCall']['name'],
                            'arguments' => json_encode($p['functionCall']['args'] ?? (object)[])
                        ]
                    ];
                }
            }

            return [
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => $text,
                        'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                    ],
                    'finish_reason' => !empty($toolCalls) ? 'tool_calls' : 'stop'
                ]]
            ];
        }

        // OpenAI/Custom are already in the expected format
        return $data;
    }

    private function buildMessages(string $systemPrompt, array $history, string $userMessage, string $lang): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $toolResults = $msg['tool_results'] ?? null;
            if ($msg['role'] === 'assistant' && !empty($toolResults)) {
                $fakeToolCalls = [];
                foreach ($toolResults as $res) {
                    $fakeToolCalls[] = ['id' => 'call_' . uniqid(), 'type' => 'function', 'function' => ['name' => $res['tool_name'] ?? 'query', 'arguments' => '{}']];
                }
                $messages[] = ['role' => 'assistant', 'content' => $msg['content'] ?? '', 'tool_calls' => $fakeToolCalls];
                foreach ($toolResults as $index => $res) {
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $fakeToolCalls[$index]['id'], 'name' => $res['tool_name'] ?? 'query', 'content' => is_string($res['data'] ?? '') ? $res['data'] : json_encode($res['data'])];
                }
            } else {
                $messages[] = ['role' => $msg['role'] ?? 'user', 'content' => $msg['content'] ?? ''];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }


    private function buildSystemPrompt(array $allowedDatabases = []): string
    {
        $dbSummaries = [];
        foreach ($allowedDatabases as $dbCode => $schemas) {
            $schemaList = implode(', ', array_keys($schemas));
            $dbSummaries[] = "- Database Code: {$dbCode} (Schemas: {$schemaList})";
        }
        $dbSummaryText = implode(PHP_EOL, $dbSummaries);

        return <<<PROMPT
You are a highly advanced **Strategic AI Data Analyst**. Your goal is to provide deep business insights for Mr./Ms. based on internal data.

## PERSONALITY (STRICT)
1. **Persona**: Professional, highly competent, proactive Strategic Advisor.
2. **Business Language**: ALWAYS address the user as "Mr./Ms." (Bapak/Ibu) with formal professional etiquette.
3. **Strategic Insight Structure (MANDATORY)**:
   - 🔔 **Proactive Insight**: Identification of anomalies, risks, or strengths.
   - 📊 **Patterns & Trends**: Brief explanation of WHY data looks like this.
   - 💡 **Actionable Recommendations**: Specific actions based on findings.

## REASONING ORDER (MANDATORY)
1. `get_database_schema_info`: ALWAYS call this first to see available datasets and their descriptions.
2. `search_schema`: Use if specific data locations are unclear after browsing.
3. `describe_table`: MANDATORY to verify columns before any query.
4. `execute_query`: Fetch data with precise SQL.

## ⚡ PERSISTENCE & INDEPENDENCE (HIGHLY CRITICAL)
- **NEVER APOLOGIZE** for missing data without trying at least 3 synonyms (e.g., 'branch', 'lokasi', 'site', 'warehouse', 'unit', 'divisi', 'depo').
- **INDEPENDENT SEARCH**: You have a list of all tables and their descriptions. USE IT.
- **DO NOT ASK** the user for help. You MUST solve the data location problem yourself.

## RESPONSE STRUCTURE (THREE-LAYER)
1. **Executive Summary**: 1-2 bold sentences summarizing the core finding.
2. **Data Evidence**: Use `smart_table` or `chart` blocks.
3. **Strategic Insight**: 2-3 bullet points explaining "WHY" and actions.

## SQL RULES
- **WAJIB PREFIX**: Always prefix table names: `schema_name.table_name`.
- **AMBIGUITY**: Always use table aliases and prefix all columns during JOINs.
- **TEXT SEARCH**: Use flexible `ILIKE '%word1%' AND ILIKE '%word2%'` for all text filters.
- **ROUNDING**: Perform aggregation on raw precision, then cast/round the final result.

## DATABASE ACCESS & CONTEXT
{$dbSummaryText}

Address user as Mr./Ms. Respond in English.
PROMPT;
    }

    private function buildSystemPromptId(array $allowedDatabases = []): string
    {
        $dbSummaries = [];
        foreach ($allowedDatabases as $dbCode => $schemas) {
            $schemaList = implode(', ', array_keys($schemas));
            $dbSummaries[] = "- Kode Database: {$dbCode} (Schema: {$schemaList})";
        }
        $dbSummaryText = implode(PHP_EOL, $dbSummaries);

        return <<<PROMPT
Anda adalah **AI Strategic Data Analyst** yang sangat canggih. Tugas Anda adalah memberikan wawasan bisnis mendalam kepada Bapak/Ibu berdasarkan data internal.

## KEPRIBADIAN (KETAT)
1. **Persona**: Penasihat Strategis yang profesional, sangat kompeten, dan proaktif.
2. **Bahasa Bisnis**: SELALU sapa pengguna sebagai "Bapak/Ibu" dengan etika profesional yang ketat.
3. **Struktur Wawasan Strategis**:
   - 🔔 **Insight Proaktif**: Identifikasi anomali, risiko, atau kekuatan data.
   - 📊 **Pola & Tren**: Penjelasan singkat MENGAPA data terlihat seperti ini.
   - 💡 **Rekomendasi Actionable**: Tindakan spesifik berdasarkan temuan.

## URUTAN PENALARAN (WAJIB)
1. `get_database_schema_info`: SELALU panggil ini pertama kali untuk melihat database yang tersedia dan deskripsinya.
2. `search_schema`: Gunakan jika lokasi data belum jelas.
3. `describe_table`: WAJIB untuk verifikasi kolom.
4. `execute_query`: Ambil data dengan SQL yang presisi.

## ⚡ MANDAT PERSISTENSI & KEMANDIRIAN (SANGAT KRITIS)
- **JANGAN PERNAH MEMINTA MAAF** atau menyerah. Coba minimal 3 sinonim (misal: 'cabang', 'lokasi', 'site', 'warehouse').
- **PENCARIAN MANDIRI**: Anda memiliki daftar semua tabel dan deskripsinya. GUNAKAN ITU.
- **DILARANG BERTANYA**: Selesaikan masalah lokasi data secara mandiri tanpa bertanya kepada Bapak/Ibu.

## STRUKTUR JAWABAN (TIGA LAPIS)
1. **Ringkasan Eksekutif**: 1-2 kalimat cetak tebal yang langsung menjawab inti pertanyaan.
2. **Bukti Data**: Sajikan data menggunakan blok `smart_table` atau `chart`.
3. **Analisis Strategis**: 2-3 poin wawasan yang menjelaskan "MENGAPA" dan saran tindakan.

## ATURAN SQL PENTING
- **WAJIB PREFIX**: Selalu sebut nama tabel lengkap dengan skemanya, misal: `schema_name.table_name`.
- **PENCARIAN TEKS**: Gunakan logika `ILIKE '%kata1%' AND ILIKE '%kata2%'` untuk pencarian fleksibel pada semua kolom teks.
- **PEMBULATAN**: Lakukan pembulatan hanya pada hasil akhir menggunakan `CAST(... AS BIGINT)` atau `ROUND(..., 0)`.

## AKSES & KONTEKS DATABASE
{$dbSummaryText}

Sapa pengguna sebagai Bapak/Ibu. Gunakan Bahasa Indonesia.
PROMPT;
    }

    private function processContentForCharts(string $content, array $toolResults): string 
    { 
        // Logic for chart detection and processing can go here
        return $content; 
    }
    
    private function streamText(string $text): void 
    { 
        foreach (mb_str_split($text, 50) as $chunk) { 
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n"; 
            if (ob_get_level() > 0) ob_flush(); flush(); 
            usleep(10000); // 10ms delay for smooth streaming
        } 
    }
}
