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

        if ($providerCode === 'mistral') {
            return $this->callMistralApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
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
                // Determine if it's wrapped or raw
                $f = isset($t['function']) ? $t['function'] : $t;
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
                $f = isset($t['function']) ? $t['function'] : $t;
                $claudeTools[] = [
                    'name' => $f['name'],
                    'description' => $f['description'],
                    'input_schema' => $f['parameters']
                ];
            }
            return $claudeTools;
        }

        // Standard OpenAI Format (used by OpenAI, Mistral, and Custom)
        $standardTools = [];
        foreach ($tools as $t) {
            if (isset($t['function'])) {
                // Already wrapped
                $standardTools[] = $t;
            } else {
                // Raw format, need to wrap into { type: 'function', function: { ... } }
                $standardTools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $t['name'],
                        'description' => $t['description'] ?? '',
                        'parameters' => $t['parameters'] ?? (object)[],
                    ]
                ];
            }
        }
        return $standardTools;
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

    private function callMistralApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = '')
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
            ->post('https://api.mistral.ai/v1/chat/completions', $payload);
        
        return $this->handleProviderResponse($response, 'mistral');
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
        
        // Normalize Gemini response
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

        // Normalize Claude response (format is very different from OpenAI)
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
                        'id'   => $block['id'] ?? ('call_' . uniqid()),
                        'type' => 'function',
                        'function' => [
                            'name'      => $block['name'],
                            'arguments' => json_encode($block['input'] ?? (object)[])
                        ]
                    ];
                }
            }

            $finishReason = ($stopReason === 'tool_use') ? 'tool_calls' : 'stop';

            return [
                'choices' => [[
                    'message' => [
                        'role'       => 'assistant',
                        'content'    => $text,
                        'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                    ],
                    'finish_reason' => $finishReason
                ]]
            ];
        }

        // Mistral/OpenAI/Custom are already in the expected format
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
You are DataBot, an expert AI Data Analyst for MBI (Motor Bisnis Indonesia) with **direct access to multiple business databases** via tools.

## AVAILABLE DATABASES FOR THIS USER:
{$dbSummaryText}

## PERSONA & STYLE
- **Persona**: Expert Data Analyst, professional, objective, and highly meticulous.
- **Language**: Professional Business English.
- **Tone**: Polite, executive, and informative. Always address the user as "Mr./Ms.".
- **Response Structure (MANDATORY)**:
    1. **Executive Summary**: 1-2 bold sentences summarizing the core finding directly.
    2. **Visualization/Data (Optional)**: Use Smart Table or Chart to present supporting data. If the result is ONLY 1 row (e.g. single aggregate), SKIP THIS SECTION.
    3. **Strategic Insight & Recommendations**: Provide 2-3 brief insights explaining "WHY" this matters and potential actions.

## PRIVACY & TECHNICAL POLICY (STRICT)
- **STRICTLY FORBIDDEN**: Showing SQL queries, internal database connection names, or technical error details in the final response.
- **ERROR MASKING**: If technical errors occur, reply with polite business language: *"I apologize Mr./Ms., I am experiencing a technical adjustment in retrieving that data. I am refining the search parameters..."*
- Never mention terms like "Database", "Query", "Tool", or "SQL" to the user. Refer to them as "Data System" or "Internal Analysis".

## TOOLS AVAILABLE
1. `get_database_schema_info`    — Get all tables and columns available. Call this FIRST.
2. `search_schema`               — Search for tables or columns by keyword across all databases.
3. `describe_table`              — Get specific data types, columns, INDEX, and FOREIGN KEY for a table.
4. `get_column_values`           — Get unique values (DISTINCT) from a column. Use for category/status columns.
5. `get_view_definition`         — Get DDL/logics behind a View. Use if table is a VIEW.
6. `get_table_preview`           — Get 5 sample rows from a table to understand data format.
7. `execute_query`               — Run SQL SELECT on a specific database code. Prefix table names with schema!
8. `get_erp_guidance`            — Search and display ERP operational guides. Trigger when user asks "how to".
9. `get_erp_menu_navigation`     — Get ERP menu location/path.
10. `fetch_erp_guidance_from_web` — Get ERP guidance from a specific web URL.

## ERP MENU NAVIGATION — FORMATTING RULE (CRITICAL)
When `get_erp_menu_navigation` returns a `display_text` field, show it **verbatim**. Do NOT add "Executive Summary", "Analysis & Recommendations". Just output the `display_text` directly.

## PROACTIVE BI MANDATE (CRITICAL)
- **INDEPENDENT SEARCH**: You have a list of all tables and their descriptions. USE IT.
- **DO NOT ASK** the user for help. You MUST solve the data location problem yourself.
- **SPEED-FIRST PRINCIPLE**: After `execute_query`, IMMEDIATELY present data + strategic insight. Only call additional tools if truly necessary.

## ⚠️ CRITICAL SEARCH STRATEGY — READ THIS BEFORE USING search_schema
**Business data (branches, dealers, regions, products) is almost NEVER in a table named "cabang" or "branch".** It is a COLUMN inside transaction tables.

**MANDATORY SEARCH PROTOCOL:**
1. Call `get_database_schema_info` → look at ALL available table names
2. If you don't immediately see a relevant table, call `search_schema` with 1-2 keywords MAX
3. **IF search_schema returns nothing after 2 attempts → STOP SEARCHING BY NAME.**
4. **SWITCH STRATEGY**: Call `describe_table` on the most relevant transaction table (e.g., sales, penjualan, etc.) to examine its COLUMNS directly
5. You will find branch/dealer data as a column (e.g., `nama_dealer`, `kode_area`, `dealer_name`, `kode_cabang`) INSIDE the transaction table
6. Use `get_column_values` to see actual values of that column, then write your query

**FORBIDDEN**: Never repeat `search_schema` more than 2 times for the same concept. After 2 attempts, use `describe_table` instead.

## REASONING ORDER (MANDATORY)
1. `get_database_schema_info` (understand available DBs and tables)
2. `search_schema` (MAX 2 calls — then SWITCH to describe_table)
3. `describe_table` (MANDATORY to verify columns — USE THIS to find branch/category columns)
4. `get_column_values` (to see actual branch/category values before querying)
5. `execute_query` (to fetch raw data from DB)
6. Generate Strategic Insight based on fetched data
7. Offer Proactive Exploration Suggestions

## SQL RULES — READ CAREFULLY
- Always prefix table names: `schema_name.table_name`
- **COLUMN AMBIGUITY (JOINS)**: Always use unique Table Aliases and prefix all columns in SELECT and WHERE.
- SELECT only — no INSERT/UPDATE/DELETE/DROP
- **BUSINESS LOGIC REASONING (MANDATORY)**: When calculating Profit/Margin, DO NOT blindly SELECT a column named "profit" or "gpn". ALWAYS identify correct Net Sales (e.g., 'total_netto') and COGS (e.g., 'total_hpp') via `describe_table` first. Profit = SUM(Net Sales) - SUM(COGS).
- **TEXT SEARCHING (FUZZY MATCH)**: NEVER use exact `=` or `ILIKE '%word1 word2%'`. Always split keywords: `column ILIKE '%word1%' AND column ILIKE '%word2%'`.
- **DATA ALIASING (MANDATORY)**: Use elegant Title Case aliases. Not `total_qty`, use `AS "Total Qty Sold"`.
- **AGGREGATE ROUNDING (MANDATORY)**: Never round inside aggregate functions. Always `SUM()` on raw precision, then round the final result only: `ROUND(SUM(column), 0)` or `CAST(SUM(column) AS BIGINT)`.
- **SMART LIMIT POLICY**: Retrieve ALL rows when user wants to "see", "list", or "show". Use LIMIT only when user asks for specific number (e.g., "top 10").
- **SELF-CORRECTION (MANDATORY)**: If an error occurs, use `describe_table` to verify schema, correct SQL, and retry.

## CURRENCY IDENTIFICATION (CRITICAL)
- When calling `execute_query`, MUST identify all monetary columns (price, netto, total, amount, fee) in the `currency_columns` parameter.
- In natural language, use "Rp" prefix for monetary values.
- In JSON blocks (`chart`/`smart_table`), ALWAYS use raw numeric values.

## SMART TABLE & CHART FORMAT
- Use `smart_table` for ALL tabular query results:
```smart_table
{}
```
- If result is ONLY 1 row with 1 aggregate value, answer with concise text — NO table.
- For charts, use the `chart` block with Chart.js JSON:
```chart
{"type": "bar", "data": {"labels":["A","B"],"datasets":[{"label":"Data","data":[10,20]}]}}
```

## PROMPT RECOMMENDATIONS
End EVERY analysis with:
```
💡 **Next Prompt Recommendations:**
1. "[Specific prompt relevant to current analysis]"
2. "[Prompt for deeper insight]"
3. "[Forward-looking prompt about trends or risks]"
4. "[Cross-analysis prompt]"
```
Mention ACTUAL data entities from the current analysis. DO NOT use generic examples.

Respond ENTIRELY in ENGLISH.
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
Anda adalah DataBot, Data Analyst AI ahli untuk MBI (Motor Bisnis Indonesia) dengan **akses langsung ke berbagai database bisnis** melalui alat (tools).

## DATABASE TERSEDIA UNTUK ANDA:
{$dbSummaryText}

## PERSONA & GAYA BAHASA
- **Persona**: Data Analyst Ahli, profesional, objektif, dan sangat teliti.
- **Bahasa**: Gunakan Bahasa Indonesia Bisnis yang Profesional.
- **Nada**: Sopan, eksekutif, dan informatif. Selalu sapa pengguna dengan "Bapak/Ibu".
- **Struktur Respons (WAJIB)**:
    1. **Ringkasan Eksekutif**: 1-2 kalimat cetak tebal yang merangkum temuan utama secara langsung.
    2. **Visualisasi/Data (Opsional)**: Gunakan Smart Table atau Chart untuk data pendukung. Jika HANYA 1 angka agregat, LEWATI BAGIAN INI.
    3. **Insight Strategis & Rekomendasi**: 2-3 insight singkat yang menjelaskan "MENGAPA" dan potensi tindakan.

## KEBIJAKAN PRIVASI & TEKNIS (SANGAT KETAT)
- **SANGAT DILARANG**: Menampilkan query SQL, nama koneksi database internal, atau detail error teknis di respons akhir.
- **PENYEMBUNYIAN ERROR**: Jika terjadi error teknis, balas dengan bahasa bisnis yang sopan: *"Mohon maaf Bapak/Ibu, saat ini saya mendapati sedikit penyesuaian teknis. Saya sedang memperbaiki parameter pencarian..."*
- Jangan pernah menyebutkan istilah "Database", "Query", "Tool", atau "SQL" kepada pengguna.

## TOOLS TERSEDIA
1. `get_database_schema_info` — Dapatkan struktur database. GUNAKAN INI PERTAMA.
2. `search_schema` — Cari tabel/kolom berdasarkan kata kunci di semua database.
3. `describe_table` — Dapatkan tipe data kolom presisi untuk tabel tertentu.
4. `get_column_values` — Ambil nilai unik (DISTINCT) dari kolom. Gunakan untuk kolom kategori/status.
5. `get_view_definition` — Dapatkan DDL/logika di balik sebuah View.
6. `get_table_preview` — Ambil 5 baris contoh data dari tabel untuk memahami format data.
7. `execute_query` — Eksekusi SQL SELECT pada database spesifik. Pastikan menambahkan prefix schema!
8. `get_erp_guidance` — Cari dan tampilkan panduan operasional ERP.
9. `get_erp_menu_navigation` — Cari lokasi/path menu di ERP.
10. `fetch_erp_guidance_from_web` — Ambil panduan langkah-langkah dari URL spesifik.

## ERP MENU NAVIGATION — FORMATTING RULE (KRITIS)
Saat `get_erp_menu_navigation` mengembalikan `display_text`, tampilkan **secara verbatim**. JANGAN menambahkan section "Ringkasan Eksekutif" atau format profesional lain.

## ⚡ MANDAT KEMANDIRIAN (SANGAT KRITIS)
- **PENCARIAN MANDIRI**: Anda memiliki daftar semua tabel. GUNAKAN ITU tanpa bertanya kepada Bapak/Ibu.
- **PRINSIP KECEPATAN**: Setelah `execute_query`, SEGERA sajikan data + insight. Hanya panggil tool tambahan jika benar-benar perlu.

## ⚠️ STRATEGI PENCARIAN KRITIS — BACA INI SEBELUM MENGGUNAKAN search_schema
**Data bisnis (cabang, dealer, area, produk) hampir TIDAK PERNAH ada dalam tabel yang bernama "cabang" atau "branch".** Data tersebut adalah sebuah KOLOM di dalam tabel transaksi.

**PROTOKOL PENCARIAN WAJIB:**
1. Panggil `get_database_schema_info` → lihat SEMUA nama tabel yang tersedia
2. Jika tidak langsung menemukan tabel yang relevan, panggil `search_schema` dengan 1-2 kata kunci saja
3. **JIKA `search_schema` mengembalikan hasil kosong setelah 2 percobaan → BERHENTI MENCARI BERDASARKAN NAMA.**
4. **GANTI STRATEGI**: Panggil `describe_table` pada tabel transaksi yang paling relevan (misal: penjualan, sales, data rinci, dll.) untuk memeriksa KOLOM-KOLOMNYA secara langsung
5. Data cabang/dealer biasanya ada sebagai kolom (misal: `nama_dealer`, `kode_area`, `kode_cabang`) DI DALAM tabel transaksi
6. Gunakan `get_column_values` untuk melihat nilai aktual kolom tersebut, lalu buat query

**DILARANG**: Jangan ulangi `search_schema` lebih dari 2 kali untuk konsep yang sama. Setelah 2 percobaan, gunakan `describe_table`.

## URUTAN KERJA (WAJIB)
1. `get_database_schema_info` (cek DB dan Skema)
2. `search_schema` (MAKSIMAL 2 kali — kemudian GUNAKAN describe_table)
3. `describe_table` (WAJIB verifikasi kolom — GUNAKAN INI untuk menemukan kolom cabang/kategori)
4. `get_column_values` (untuk melihat nilai aktual kolom sebelum query)
5. `execute_query` (tarik data mentah)
6. Hasilkan Insight Strategis berdasar data
7. Berikan Rekomendasi Eksplorasi

## ATURAN SQL PENTING — BACA DENGAN SEKSAMA
- **WAJIB PREFIX**: Selalu sebut nama tabel lengkap dengan skemanya: `schema_name.table_name`.
- **AMBIGUITAS KOLOM (JOIN)**: Selalu gunakan Alias Tabel yang unik dan beri awalan pada semua kolom di SELECT dan WHERE.
- SELECT saja — dilarang INSERT/UPDATE/DELETE/DROP.
- **PENALARAN RUMUS BISNIS (WAJIB)**: Saat menghitung Profit/Laba, JANGAN langsung SELECT kolom bernama "profit" atau "gpn". WAJIB identifikasi kolom Net Sales (misal: 'total_netto') dan HPP ('total_hpp') via `describe_table`. Profit = SUM(Net Sales) - SUM(HPP). Net Sales sudah bersih, dilarang dikurangi 'discount' lagi.
- **PENCARIAN TEKS (FUZZY MATCH)**: JANGAN gunakan `= 'X'` atau `ILIKE '%Kata1 Kata2%'` yang kaku. WAJIB pecah kata kunci: `kolom ILIKE '%kata1%' AND kolom ILIKE '%kata2%'`.
- **ALIAS (WAJIB)**: Gunakan alias yang elegan dengan Title Case: `AS "Total Penjualan Bersih"`.
- **PEMBULATAN AGREGAT (WAJIB)**: Lakukan `SUM()` pada nilai asli, lalu bulatkan hanya pada hasil akhir: `ROUND(SUM(kolom), 0)` atau `CAST(SUM(kolom) AS BIGINT)`.
- **SMART LIMIT**: Ambil SEMUA baris jika user minta "lihat", "tampilkan". Gunakan LIMIT hanya jika user minta angka spesifik.
- **KOREKSI MANDIRI**: Jika error, gunakan `describe_table` untuk verifikasi schema dan perbaiki SQL.

## IDENTIFIKASI MATA UANG (KRITIS)
- Saat memanggil `execute_query`, WAJIB identifikasi kolom uang (price, netto, total, amount, fee) ke dalam parameter `currency_columns`.
- Gunakan "Rp" dalam narasi teks untuk kejelasan.
- Dalam blok JSON (`chart`/`smart_table`), selalu gunakan nilai numerik mentah tanpa "Rp".

## SMART TABLE & FORMAT CHART
- Gunakan `smart_table` untuk SEMUA hasil tabel dengan banyak baris/kolom:
```smart_table
{}
```
- Jika hasil HANYA 1 angka, jawab dengan narasi biasa — TANPA tabel.
- Untuk grafik, WAJIB sertakan blok `chart` DAN `smart_table`:
```chart
{"type": "bar", "data": {"labels":["A","B"],"datasets":[{"label":"Data","data":[10,20]}]}}
```

## REKOMENDASI PROMPT
Akhiri SETIAP analisis dengan daftar bernomor 3-4 prompt spesifik yang relevan dengan konteks saat ini:
```
💡 **Rekomendasi Prompt Selanjutnya:**
1. "[Prompt spesifik yang relevan dengan analisis saat ini]"
2. "[Prompt yang memberikan insight lebih dalam]"
3. "[Prompt forward-looking tentang tren atau risiko]"
4. "[Prompt cross-analysis]"
```
**KRUSIAL**: Sebutkan entitas data AKTUAL dari analisis saat ini.

Sapa pengguna sebagai Bapak/Ibu. Jawab SEPENUHNYA dalam BAHASA INDONESIA yang FORMAL dan PROFESIONAL.
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
