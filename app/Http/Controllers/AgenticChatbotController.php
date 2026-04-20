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

/**
 * AgenticChatbotController — Tool Calling (Agentic Loop)
 * Provider: OpenAI dengan fallback otomatis antar model
 * Urutan: gpt-5.4 → gpt-5.4-mini → gpt-5.4-nano → gpt-5.4-pro
 */
class AgenticChatbotController extends Controller
{
    private int $maxToolLoops = 20;
    private int $maxHistory = 20;
    private int $maxTokens = 32768;

    private LanguageDetector $langDetector;
    private ToolCallExecutor $toolExecutor;

    public function __construct()
    {
        $this->langDetector = new LanguageDetector();
        $this->toolExecutor = new ToolCallExecutor();
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

    // ── Endpoint utama ────────────────────────────────────────────────────────
    public function send(Request $request)
    {
        $request->validate([
            'message'  => 'required|string',
            'model_id' => 'required|exists:ai_models,id'
        ]);

        $user = Auth::user();
        $message = $request->message;
        $selectedModelId = $request->model_id;
        $chatSessionId = $request->chat_session_id;

        $selectedModel = $user->aiModels()->with('provider')->findOrFail($selectedModelId);
        $apiKey = $user->aiKeys()->where('provider_id', $selectedModel->provider_id)->where('is_active', true)->first();

        if (!$apiKey) {
            $errorMsg = $detectedLang === 'en'
                ? 'Apologies, AI analysis access is not yet configured or active for your account. Please contact your System Administrator for access activation.'
                : 'Mohon maaf, akses layanan analisis AI belum dikonfigurasi atau tidak aktif untuk akun Anda. Harap hubungi Administrator Sistem Anda untuk aktivasi akses.';
            return response()->json(['error' => $errorMsg], 403);
        }

        $detectedLang = $this->langDetector->detect($message);
        $allowedDatabases = $user->roleModel->getAllowedDatabases();

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

        $systemPrompt = $this->buildSystemPrompt($detectedLang, $allowedDatabases);
        $messages = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);
        $maxTokens = $user->max_tokens ?? $this->maxTokens;

        session_write_close();

        return response()->stream(
            function () use ($messages, $apiKey, $selectedModel, $detectedLang, $allowedDatabases, $chatSessionId, $maxTokens) {
                $this->runAgenticLoop($messages, $apiKey, $detectedLang, $selectedModel, $allowedDatabases, $chatSessionId, $maxTokens);
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
    private function runAgenticLoop(array $messages, $apiKey, string $lang, $model, array $allowedDatabases = [], $chatSessionId = null, $maxTokens = null): void
    {
        if ($chatSessionId) {
            echo "data: " . json_encode(['chat_session_id' => $chatSessionId]) . "\n\n";
        }
        echo "data: " . json_encode(['chunk' => '', 'status' => 'thinking']) . "\n\n";
        ob_flush();
        flush();

        $this->toolExecutor->setAllowedTables($allowedDatabases);

        $tools = ToolCallExecutor::getToolDefinitions();
        $loopCount = 0;
        $allTurnToolResults = [];

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            Log::info("[Agentic] ── Loop #{$loopCount} ──");

            $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens);

            if (!$response || !isset($response['choices'][0]['message'])) {
                $errMsg = $lang === 'en'
                    ? "Apologies, our analytical infrastructure is currently experiencing exceptionally high traffic or the daily quota has been reached. Please contact your System Administrator if this persists."
                    : "Mohon maaf, infrastruktur analisis kami sedang mengalami kepadatan lalu lintas data yang sangat tinggi atau kuota harian telah tercapai. Harap hubungi Administrator Sistem Anda jika kendala ini berlanjut.";

                $this->streamText($errMsg);
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
                return;
            }

            $assistantMsg = $response['choices'][0]['message'];
            $finishReason = $response['choices'][0]['finish_reason'] ?? 'stop';
            
            $toolCalls = $assistantMsg['tool_calls'] ?? [];
            $textContent = $assistantMsg['content'] ?? '';

            $messages[] = $assistantMsg;

            // ── Jawaban final ─────────────────────────────────────────────────
            if (empty($toolCalls) || $finishReason === 'stop' || $finishReason === 'end_turn') {
                $finalContent = trim($textContent);
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
                echo "data: [DONE]\n\n";
                ob_flush();
                flush();
                return;
            }

            // ── Eksekusi tool calls ───────────────────────────────────────────
            foreach ($toolCalls as $toolCall) {
                $toolCallId = $toolCall['id'] ?? ('call_' . uniqid());
                $toolName = $toolCall['function']['name'] ?? '';
                $argsRaw = $toolCall['function']['arguments'] ?? '{}';
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
                    'name' => $toolName,
                    'content' => $aiContent,
                ];

                echo "data: " . json_encode([
                    'tool_call' => ['name' => $toolName, 'status' => 'done']
                ]) . "\n\n";
            }
            ob_flush();
            flush();
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

    public function getSessions(Request $request)
    {
        return ChatSession::where('user_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'title', 'updated_at']);
    }

    public function getSession($id)
    {
        $user = Auth::user();
        $session = ChatSession::where('user_id', $user->id)->findOrFail($id);
        
        $messages = ChatMessage::where('chat_session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'session' => $session,
            'history' => $messages,
            'pagination' => [
                'has_more' => false,
                'oldest_cursor' => null
            ]
        ]);
    }

    public function deleteSession($id)
    {
        $user = Auth::user();
        $session = ChatSession::where('user_id', $user->id)->findOrFail($id);
        $session->delete();

        return response()->json(['success' => true]);
    }

    public function updateSessionTitle(Request $request, $id)
    {
        $user = Auth::user();
        $session = ChatSession::where('user_id', $user->id)->findOrFail($id);
        
        $request->validate([
            'title' => 'required|string|max:255'
        ]);

        $session->update(['title' => $request->title]);

        return response()->json(['success' => true]);
    }

    // ── Panggil AI API (Multi-Provider Dispatcher) ──────────────────────────
    private function callAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens = null): ?array
    {
        $providerCode = $apiKey->provider->code;
        $maxTokens = $maxTokens ?? $this->maxTokens;
        
        Log::info("[Agentic] Dispatching request for provider: {$providerCode}, model: {$model->model_name}");

        // 1. Google Gemini (Format: generateContent)
        if ($providerCode === 'gemini') {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model->model_name . ':generateContent?key=' . $apiKey->api_key;
            return $this->callGeminiApi($messages, $tools, $url, $maxTokens);
        }

        // 2. Anthropic Claude (Format: /v1/messages)
        if ($providerCode === 'claude') {
            return $this->callClaudeApi($messages, $tools, $apiKey, $model, $maxTokens);
        }

        // 3. Custom Format / GPT-5.4 (Format yang Anda minta: menggunakan 'input' & 'type')
        if ($model->model_name === 'gpt-5.4' || $providerCode === 'custom') {
            return $this->callCustomApi($messages, $tools, $apiKey, $model, $maxTokens);
        }

        // 4. Standard OpenAI (Format: /v1/chat/completions)
        return $this->callOpenAiApi($messages, $tools, $apiKey, $model, $maxTokens);
    }

    private function callOpenAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens)
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        
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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey->api_key,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        return $this->handleGenericResponse($response, $apiKey);
    }

    private function callClaudeApi(array $messages, array $tools, $apiKey, $model, $maxTokens)
    {
        $url = 'https://api.anthropic.com/v1/messages';
        
        $system = '';
        $claudeMessages = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system = $m['content'];
            } else {
                $claudeMessages[] = [
                    'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $m['content']
                ];
            }
        }

        $payload = [
            'model' => $model->model_name,
            'max_tokens' => (int)$maxTokens,
            'messages' => $claudeMessages,
            'system' => $system,
        ];

        if (!empty($tools)) {
            $claudeTools = [];
            foreach ($tools as $t) {
                $f = isset($t['function']) ? $t['function'] : $t;
                $claudeTools[] = [
                    'name' => $f['name'] ?? '',
                    'description' => $f['description'] ?? '',
                    'input_schema' => $f['parameters'] ?? ['type' => 'object', 'properties' => (object)[]]
                ];
            }
            $payload['tools'] = $claudeTools;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey->api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        if ($response->failed()) return null;
        
        $data = $response->json();
        $content = '';
        $toolCalls = [];
        
        foreach ($data['content'] as $block) {
            if ($block['type'] === 'text') $content .= $block['text'];
            if ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $block['name'],
                        'arguments' => json_encode($block['input'])
                    ]
                ];
            }
        }

        return [
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => $content,
                    'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                ],
                'finish_reason' => $data['stop_reason'] === 'tool_use' ? 'tool_calls' : 'stop'
            ]]
        ];
    }

    private function callCustomApi(array $messages, array $tools, $apiKey, $model, $maxTokens)
    {
        $url = 'https://api.openai.com/v1/responses';

        $cleanMessages = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            $textVal = $msg['content'] ?? '';

            if ($role === 'tool') {
                $cleanMessages[] = [
                    'type' => 'function_call_output',
                    'call_id' => $msg['tool_call_id'] ?? '',
                    'output' => (string)$textVal
                ];
                continue;
            }

            $contentType = ($role === 'assistant') ? 'output_text' : 'input_text';
            $clean = ['role' => $role, 'content' => []];

            if ((string)$textVal !== '') {
                $clean['content'][] = ['type' => $contentType, 'text' => (string)$textVal];
            }

            if ($role === 'assistant' && !empty($msg['tool_calls'])) {
                if (!empty($clean['content'])) {
                    $cleanMessages[] = $clean;
                }
                foreach ($msg['tool_calls'] as $tc) {
                    $f = $tc['function'] ?? $tc;
                    $cleanMessages[] = [
                        'type' => 'function_call',
                        'call_id' => $tc['id'] ?? '',
                        'name' => $f['name'] ?? '',
                        'arguments' => $f['arguments'] ?? '{}'
                    ];
                }
            } else {
                $cleanMessages[] = $clean;
            }
        }

        $payload = [
            'model' => $model->model_name,
            'input' => $cleanMessages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'max_output_tokens' => (int)$maxTokens,
            'temperature' => 0.2,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey->api_key,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        return $this->handleGenericResponse($response, $apiKey);
    }

    private function handleGenericResponse($response, $apiKey)
    {
        if ($response->status() === 429) {
            $apiKey->update(['limit_reached' => true]);
            return null;
        }

        if ($response->failed()) {
            Log::error("[Agentic] API Error: " . $response->body());
            return null;
        }

        return $response->json();
    }

    private function callGeminiApi(array $messages, array $tools, $url, $maxTokens)
    {
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            $role = $msg['role'];
            if ($role === 'system') {
                $systemInstruction = ['parts' => [['text' => $msg['content']]]];
                continue;
            }

            $geminiRole = ($role === 'assistant') ? 'model' : 'user';
            
            if ($role === 'tool') {
                $contents[] = [
                    'role' => 'function',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $msg['name'] ?? '',
                            'response' => ['content' => $msg['content']]
                        ]
                    ]]
                ];
                continue;
            }

            $parts = [];
            if (isset($msg['content']) && !empty($msg['content'])) {
                $parts[] = ['text' => (string)$msg['content']];
            }

            if ($role === 'assistant' && !empty($msg['tool_calls'])) {
                foreach ($msg['tool_calls'] as $tc) {
                    $f = $tc['function'] ?? $tc;
                    $parts[] = [
                        'functionCall' => [
                            'name' => $f['name'] ?? '',
                            'args' => isset($f['arguments']) ? (is_string($f['arguments']) ? json_decode($f['arguments'], true) : $f['arguments']) : (object)[]
                        ]
                    ];
                }
            }

            if (!empty($parts)) {
                $contents[] = ['role' => $geminiRole, 'parts' => $parts];
            }
        }

        $geminiTools = [];
        if (!empty($tools)) {
            $declarations = [];
            foreach ($tools as $t) {
                $f = isset($t['function']) ? $t['function'] : $t;
                $declarations[] = [
                    'name' => $f['name'] ?? '',
                    'description' => $f['description'] ?? '',
                    'parameters' => $f['parameters'] ?? ['type' => 'object', 'properties' => (object)[]]
                ];
            }
            $geminiTools = [['function_declarations' => $declarations]];
        }

        $payload = [
            'contents' => $contents,
            'tools' => $geminiTools,
            'generationConfig' => [
                'maxOutputTokens' => (int)$maxTokens,
                'temperature' => 0.1,
            ]
        ];

        if ($systemInstruction) {
            $payload['system_instruction'] = $systemInstruction;
        }

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error("Gemini API Error: " . $response->body());
            return null;
        }

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? null;
        if (!$candidate) return null;

        $modelMsg = $candidate['content'];
        $resContent = '';
        $toolCalls = [];

        foreach ($modelMsg['parts'] as $part) {
            if (isset($part['text'])) {
                $resContent .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => 'call_' . uniqid(),
                    'type' => 'function',
                    'function' => [
                        'name' => $part['functionCall']['name'],
                        'arguments' => json_encode($part['functionCall']['args'])
                    ]
                ];
            }
        }

        return [
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => $resContent,
                    'tool_calls' => !empty($toolCalls) ? $toolCalls : null
                ],
                'finish_reason' => $candidate['finishReason'] ?? 'stop'
            ]]
        ];
    }

    private function buildMessages(string $systemPrompt, array $history, string $userMessage, string $lang): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($history as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];
            $toolResults = $msg['tool_results'] ?? null;

            if ($role === 'assistant' && !empty($toolResults)) {
                $fakeToolCalls = [];
                foreach ($toolResults as $res) {
                    $callId = 'call_' . uniqid();
                    $fakeToolCalls[] = [
                        'id' => $callId,
                        'type' => 'function',
                        'function' => [
                            'name' => $res['tool_name'],
                            'arguments' => '{}'
                        ]
                    ];
                }
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $content,
                    'tool_calls' => $fakeToolCalls
                ];
                foreach ($toolResults as $index => $res) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $fakeToolCalls[$index]['id'],
                        'name' => $res['tool_name'] ?? '',
                        'content' => is_string($res['data'] ?? '') ? $res['data'] : json_encode($res['data'])
                    ];
                }
            } else {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        if (count($messages) > $this->maxHistory * 2) {
            $messages = array_merge(
                [array_shift($messages)],
                array_slice($messages, -($this->maxHistory * 2))
            );
        }

        return $messages;
    }

    private function processContentForCharts(string $content, array $toolResults): string
    {
        // Simple heuristic: if tool returned numeric data, suggest a chart
        return $content;
    }

    private function extractClientHistory(array $messages): array
    {
        return array_filter($messages, fn($m) => in_array($m['role'], ['user', 'assistant']) && !empty($m['content']));
    }
}
