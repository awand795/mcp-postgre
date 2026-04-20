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

        $detectedLang = $this->langDetector->detect($message);

        if (!$apiKey) {
            $errorMsg = $detectedLang === 'en'
                ? 'Apologies, AI analysis access is not yet configured. Please contact Administrator.'
                : 'Mohon maaf, akses layanan analisis AI belum dikonfigurasi. Harap hubungi Administrator Sistem.';
            return response()->json(['error' => $errorMsg], 403);
        }

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

    private function runAgenticLoop(array $messages, $apiKey, string $lang, $model, array $allowedDatabases = [], $chatSessionId = null, $maxTokens = null): void
    {
        if ($chatSessionId) {
            echo "data: " . json_encode(['chat_session_id' => $chatSessionId]) . "\n\n";
        }
        echo "data: " . json_encode(['chunk' => '', 'status' => 'thinking']) . "\n\n";
        ob_flush(); flush();

        $this->toolExecutor->setAllowedTables($allowedDatabases);
        $tools = ToolCallExecutor::getToolDefinitions();
        $loopCount = 0;
        $allTurnToolResults = [];

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            Log::info("[Agentic] Loop #{$loopCount} - User: " . Auth::user()->name);

            $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens);

            if (!$response || !isset($response['choices'][0]['message'])) {
                $errMsg = $lang === 'en'
                    ? "Analytical infrastructure experiencing high traffic. Contact Administrator."
                    : "Infrastruktur analisis sedang mengalami kepadatan tinggi. Harap hubungi Administrator.";
                $this->streamText($errMsg);
                echo "data: [DONE]\n\n";
                ob_flush(); flush();
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
                    $finalContent = "Mohon maaf, permintaan Anda tidak dapat diproses saat ini.";
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
                ob_flush(); flush();
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
                        'message'       => "Data truncated to 50 rows for AI."
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
                ob_flush(); flush();
                
                $allTurnToolResults[] = ['tool_name' => $toolName, 'data' => $decodedRes ?: $toolResult];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name' => $toolName,
                    'content' => $aiContent,
                ];
            }
            ob_flush(); flush();
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

    private function callAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens = null): ?array
    {
        $providerCode = $apiKey->provider->code;
        $maxTokens = $maxTokens ?? $this->maxTokens;
        
        if ($providerCode === 'gemini') {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model->model_name . ':generateContent?key=' . $apiKey->api_key;
            return $this->callGeminiApi($messages, $tools, $url, $maxTokens);
        }

        if ($providerCode === 'claude') {
            return $this->callClaudeApi($messages, $tools, $apiKey, $model, $maxTokens);
        }

        if ($model->model_name === 'gpt-5.4' || $providerCode === 'custom') {
            return $this->callCustomApi($messages, $tools, $apiKey, $model, $maxTokens);
        }

        return $this->callOpenAiApi($messages, $tools, $apiKey, $model, $maxTokens);
    }

    private function callOpenAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens)
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
        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $apiKey->api_key])->post('https://api.openai.com/v1/chat/completions', $payload);
        return $this->handleGenericResponse($response, $apiKey);
    }

    private function callClaudeApi(array $messages, array $tools, $apiKey, $model, $maxTokens)
    {
        $system = ''; $claudeMessages = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') $system = $m['content'];
            else $claudeMessages[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $m['content']];
        }
        $payload = ['model' => $model->model_name, 'max_tokens' => (int)$maxTokens, 'messages' => $claudeMessages, 'system' => $system];
        if (!empty($tools)) {
            $claudeTools = [];
            foreach ($tools as $t) {
                $f = isset($t['function']) ? $t['function'] : $t;
                $claudeTools[] = ['name' => $f['name'] ?? '', 'description' => $f['description'] ?? '', 'input_schema' => $f['parameters'] ?? ['type' => 'object', 'properties' => (object)[]]];
            }
            $payload['tools'] = $claudeTools;
        }
        $response = Http::withHeaders(['x-api-key' => $apiKey->api_key, 'anthropic-version' => '2023-06-01'])->post('https://api.anthropic.com/v1/messages', $payload);
        if ($response->failed()) return null;
        $data = $response->json();
        $content = ''; $toolCalls = [];
        foreach ($data['content'] as $block) {
            if ($block['type'] === 'text') $content .= $block['text'];
            if ($block['type'] === 'tool_use') {
                $toolCalls[] = ['id' => $block['id'], 'type' => 'function', 'function' => ['name' => $block['name'], 'arguments' => json_encode($block['input'])]];
            }
        }
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content, 'tool_calls' => !empty($toolCalls) ? $toolCalls : null], 'finish_reason' => $data['stop_reason'] === 'tool_use' ? 'tool_calls' : 'stop']]];
    }

    private function callCustomApi(array $messages, array $tools, $apiKey, $model, $maxTokens)
    {
        $cleanMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'tool') {
                $cleanMessages[] = ['type' => 'function_call_output', 'call_id' => $msg['tool_call_id'] ?? '', 'output' => (string)$msg['content']];
                continue;
            }
            $contentType = ($msg['role'] === 'assistant') ? 'output_text' : 'input_text';
            $clean = ['role' => $msg['role'], 'content' => [['type' => $contentType, 'text' => (string)$msg['content']]]];
            if ($msg['role'] === 'assistant' && !empty($msg['tool_calls'])) {
                $cleanMessages[] = $clean;
                foreach ($msg['tool_calls'] as $tc) {
                    $f = $tc['function'] ?? $tc;
                    $cleanMessages[] = ['type' => 'function_call', 'call_id' => $tc['id'] ?? '', 'name' => $f['name'] ?? '', 'arguments' => $f['arguments'] ?? '{}'];
                }
            } else {
                $cleanMessages[] = $clean;
            }
        }
        $payload = ['model' => $model->model_name, 'input' => $cleanMessages, 'tools' => $tools, 'max_output_tokens' => (int)$maxTokens, 'temperature' => 0.2];
        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $apiKey->api_key])->post('https://api.openai.com/v1/responses', $payload);
        return $this->handleGenericResponse($response, $apiKey);
    }

    private function handleGenericResponse($response, $apiKey)
    {
        if ($response->status() === 429) { $apiKey->update(['limit_reached' => true]); return null; }
        if ($response->failed()) { Log::error("[Agentic] API Error: " . $response->body()); return null; }
        return $response->json();
    }

    private function callGeminiApi(array $messages, array $tools, $url, $maxTokens)
    {
        $contents = []; $systemInstruction = null;
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') { $systemInstruction = ['parts' => [['text' => $msg['content']]]]; continue; }
            if ($msg['role'] === 'tool') {
                $contents[] = ['role' => 'function', 'parts' => [['functionResponse' => ['name' => $msg['name'] ?? '', 'response' => ['content' => $msg['content']]]]]];
                continue;
            }
            $parts = [];
            if (isset($msg['content']) && !empty($msg['content'])) $parts[] = ['text' => (string)$msg['content']];
            if ($msg['role'] === 'assistant' && !empty($msg['tool_calls'])) {
                foreach ($msg['tool_calls'] as $tc) {
                    $f = $tc['function'] ?? $tc;
                    $parts[] = ['functionCall' => ['name' => $f['name'] ?? '', 'args' => isset($f['arguments']) ? (is_string($f['arguments']) ? json_decode($f['arguments'], true) : $f['arguments']) : (object)[]]];
                }
            }
            if (!empty($parts)) $contents[] = ['role' => $msg['role'] === 'assistant' ? 'model' : 'user', 'parts' => $parts];
        }
        $declarations = [];
        foreach ($tools as $t) {
            $f = isset($t['function']) ? $t['function'] : $t;
            $declarations[] = ['name' => $f['name'] ?? '', 'description' => $f['description'] ?? '', 'parameters' => $f['parameters'] ?? ['type' => 'object', 'properties' => (object)[]]];
        }
        $payload = ['contents' => $contents, 'tools' => [['function_declarations' => $declarations]], 'generationConfig' => ['maxOutputTokens' => (int)$maxTokens, 'temperature' => 0.1]];
        if ($systemInstruction) $payload['system_instruction'] = $systemInstruction;

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($url, $payload);
        if ($response->failed()) { Log::error("[Gemini] Error: " . $response->body()); return null; }
        $data = $response->json();
        if (!isset($data['candidates'][0]['content'])) return null;
        $modelMsg = $data['candidates'][0]['content'];
        $resContent = ''; $toolCalls = [];
        foreach ($modelMsg['parts'] as $part) {
            if (isset($part['text'])) $resContent .= $part['text'];
            if (isset($part['functionCall'])) {
                $toolCalls[] = ['id' => 'call_' . uniqid(), 'type' => 'function', 'function' => ['name' => $part['functionCall']['name'], 'arguments' => json_encode($part['functionCall']['args'] ?? (object)[])]];
            }
        }
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $resContent, 'tool_calls' => !empty($toolCalls) ? $toolCalls : null], 'finish_reason' => $data['candidates'][0]['finishReason'] ?? 'stop']]];
    }

    private function buildMessages(string $systemPrompt, array $history, string $userMessage, string $lang): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $msg) {
            $toolResults = $msg['tool_results'] ?? null;
            if ($msg['role'] === 'assistant' && !empty($toolResults)) {
                $fakeToolCalls = [];
                foreach ($toolResults as $res) {
                    $fakeToolCalls[] = ['id' => 'call_' . uniqid(), 'type' => 'function', 'function' => ['name' => $res['tool_name'], 'arguments' => '{}']];
                }
                $messages[] = ['role' => 'assistant', 'content' => $msg['content'], 'tool_calls' => $fakeToolCalls];
                foreach ($toolResults as $index => $res) {
                    $messages[] = ['role' => 'tool', 'tool_call_id' => $fakeToolCalls[$index]['id'], 'name' => $res['tool_name'] ?? '', 'content' => is_string($res['data'] ?? '') ? $res['data'] : json_encode($res['data'])];
                }
            } else {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    private function processContentForCharts(string $content, array $toolResults): string { return $content; }
    private function streamText(string $text): void { foreach (mb_str_split($text, 30) as $chunk) { echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n"; ob_flush(); flush(); } }
    private function buildSystemPrompt(string $lang, array $allowedDatabases = []): string { return "You are DataBot..."; }
}
