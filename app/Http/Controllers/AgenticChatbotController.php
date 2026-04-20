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
use Exception;

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

        $systemPrompt = $this->buildSystemPrompt($detectedLang, $allowedDatabases);
        $messages = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);
        $maxTokens = $user->max_tokens ?? $this->maxTokens;

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
                $response = $this->callAiApi($messages, $tools, $apiKey, $model, $maxTokens);
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

    private function callAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens = null): ?array
    {
        $providerCode = $apiKey->provider->code;
        $maxTokens = $maxTokens ?? $this->maxTokens;
        
        $modelName = $model->model_name;

        if ($providerCode === 'gemini') {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $modelName . ':generateContent?key=' . $apiKey->api_key;
            return $this->callGeminiApi($messages, $tools, $url, $maxTokens);
        }

        if ($providerCode === 'claude') {
            return $this->callClaudeApi($messages, $tools, $apiKey, $model, $maxTokens);
        }

        if ($providerCode === 'custom') {
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
        // Many custom providers (like Groq, OpenRouter, or internal proxies) 
        // use the standard OpenAI /chat/completions format.
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
        
        // Use the generic OpenAI-style chat completions endpoint
        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $apiKey->api_key])
            ->post('https://api.openai.com/v1/chat/completions', $payload);
            
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
            
            $role = $msg['role'] ?? 'user';
            $geminiRole = 'user';
            if ($role === 'assistant') $geminiRole = 'model';
            elseif ($role === 'tool' || $role === 'function') $geminiRole = 'function';

            $parts = [];

            if ($role === 'tool' || $role === 'function') {
                $parts[] = [
                    'functionResponse' => [
                        'name' => $msg['name'] ?? 'query',
                        'response' => [
                            'content' => $msg['content']
                        ]
                    ]
                ];
            } else {
                if (isset($msg['content']) && !empty($msg['content'])) {
                    $parts[] = ['text' => (string)$msg['content']];
                }
                
                if ($role === 'assistant' && !empty($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        $f = $tc['function'] ?? $tc;
                        $args = [];
                        if (isset($f['arguments'])) {
                            $args = is_string($f['arguments']) ? json_decode($f['arguments'], true) : $f['arguments'];
                        }
                        
                        $parts[] = [
                            'functionCall' => [
                                'name' => $f['name'] ?? '',
                                'args' => (object)($args ?? [])
                            ]
                        ];
                    }
                }
            }

            if (!empty($parts)) {
                $lastIdx = count($contents) - 1;
                if ($lastIdx >= 0 && $contents[$lastIdx]['role'] === $geminiRole) {
                    $contents[$lastIdx]['parts'] = array_merge($contents[$lastIdx]['parts'], $parts);
                } else {
                    $contents[] = ['role' => $geminiRole, 'parts' => $parts];
                }
            }
        }
        $declarations = [];
        foreach ($tools as $t) {
            $f = isset($t['function']) ? $t['function'] : $t;
            $declarations[] = ['name' => $f['name'] ?? '', 'description' => $f['description'] ?? '', 'parameters' => $f['parameters'] ?? ['type' => 'object', 'properties' => (object)[]]];
        }
        $payload = ['contents' => $contents, 'tools' => [['function_declarations' => $declarations]], 'generationConfig' => ['maxOutputTokens' => (int)$maxTokens, 'temperature' => 0.1]];
        if ($systemInstruction) $payload['system_instruction'] = $systemInstruction;

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($url, $payload);
        if ($response->failed()) { Log::error("[Gemini] API Error: " . $response->status() . " - " . $response->body()); return null; }
        
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
- **⚡ PERSISTENCE MANDATE (HIGHLY CRITICAL)**: If a search for a specific keyword (e.g., "cabang") using `search_schema` returns 0 results, **DO NOT STOP**. You must be independent (independent) and proactive:
    1. **TRY SYNONYMS**: Immediately try searching for common business synonyms (e.g., 'branch', 'lokasi', 'site', 'warehouse', 'unit', 'divisi', 'depo').
    2. **CROSS-LINGUAL SEARCH**: Try searching for both Indonesian and English terms (e.g., if 'cabang' fails, try 'branch').
    3. **MANUAL INSPECTION**: If keyword searches fail, call `get_database_schema_info` to see all table names. Identify the most likely tables for sales or transactions (e.g., tables containing 'penjualan', 'sales', 'trx') and call `describe_table` on them to manually inspect their columns and comments for hidden data.
- Never mention terms like "Database", "Query", "Tool", or "SQL" to the user. Refer to them as "Data System" or "Internal Analysis".

## TOOLS AVAILABLE
1. `get_database_schema_info`       — Get all tables and columns available to you. Call this FIRST if you don't know the exact structure.
2. `search_schema`                  — Search for tables or columns by keyword across all databases. Use this if you are unsure where specific data (like "discounts") is stored.
3. `describe_table`                 — Get specific data types, columns, INDEX info, and FOREIGN KEY relationships for a table.
4. `get_column_values`              — Get unique values (DISTINCT) from a column. Use this to see actual data content for category/status columns before writing filter queries.
5. `get_view_definition`            — Get DDL/logics behind a View. Use this if the table is a VIEW to understand its underlying structure.
6. `get_table_preview`              — Get 5 sample rows from a table to understand the actual data content and format.
7. `execute_query`                  — Run SQL SELECT on a specific database code. Remember to prefix table names with the schema name!
8. `get_erp_guidance`               — Search and display ERP operational guides (how to use ERP features/modules). Trigger when user asks "how to" or needs a tutorial for the ERP system. 
9. `get_erp_menu_navigation`        — Get ERP menu location/path. Use when user asks "where is X menu?", "dimana menu Y?", "how to access Z module?".
10. `fetch_erp_guidance_from_web`    — Get ERP guidance step-by-step from specific web URL.

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
1. `get_database_schema_info` (to understand available DBs and tables)
2. `search_schema` (ONLY if you need to find where specific data is located)
3. `describe_table` (MANDATORY to verify columns and FOREIGN KEY relationships)
4. `get_table_preview` (HIGHLY RECOMMENDED to understand data content and format)
5. `execute_query` (to fetch raw data from DB)
6. Generate Strategic Insight based on fetched data
7. Offer Proactive Exploration Suggestions

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
- **⚡ MANDAT PERSISTENSI (SANGAT PENTING)**: Jika pencarian kata kunci tertentu (misal: "cabang") menggunakan `search_schema` menghasilkan 0 hasil, **JANGAN MENYERAH**. Anda harus independen (independent) dan proaktif:
    1. **COBA SINONIM**: Segera coba cari sinonim bisnis umum (misal: 'branch', 'lokasi', 'site', 'warehouse', 'unit', 'divisi', 'depo').
    2. **PENCARIAN LINTAS BAHASA**: Coba cari istilah dalam Bahasa Indonesia dan Bahasa Inggris (misal: jika 'cabang' gagal, cari 'branch').
    3. **INSPEKSI MANUAL**: Jika pencarian kata kunci gagal, panggil `get_database_schema_info` untuk melihat semua nama tabel. Identifikasi tabel yang paling relevan dengan transaksi atau data Master (misal: tabel yang mengandung 'penjualan', 'sales', 'trx') dan panggil `describe_table` pada tabel tersebut untuk memeriksa kolom dan komentarnya secara manual guna menemukan data yang tersembunyi.
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
2. search_schema (HANYA JIKA Anda butuh mencari letak info tertentu)
3. describe_table (WAJIB jika Anda belum memverifikasi nama kolom dan tipe data untuk tabel spesifik yang akan di-query)
4. get_table_preview (SANGAT DISARANKAN untuk memahami isi data)
5. execute_query (untuk menarik data mentah)
6. Hasilkan Insight Strategis berdasar data
7. Berikan Rekomendasi Eksplorasi

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

    private function processContentForCharts(string $content, array $toolResults): string { return $content; }
    private function streamText(string $text): void { foreach (mb_str_split($text, 30) as $chunk) { echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n"; if (ob_get_level() > 0) ob_flush(); flush(); } }
}
