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

        // FIX: Simpan pesan USER ke database sebelum streaming dimulai.
        // Sebelumnya hanya pesan assistant yang disimpan, sehingga history
        // tidak lengkap dan saat chat di-reload, pesan user hilang.
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role'            => 'user',
            'content'         => $message,
            'tool_results'    => null,
        ]);

        // Update session title jika ini bukan session baru
        // (session baru sudah di-set titlenya saat create di atas)
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
            $toolCalls    = $assistantMsg['tool_calls'] ?? [];
            $textContent  = $assistantMsg['content'] ?? '';

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
                $toolResult = $this->toolExecutor->execute($toolName, $arguments);

                $decodedRes = json_decode($toolResult, true);
                $aiContent  = $toolResult;
                if (is_array($decodedRes) && isset($decodedRes['rows']) && count($decodedRes['rows']) > 50) {
                    $aiContent = json_encode([
                        'rows_returned' => count($decodedRes['rows']),
                        'columns'       => $decodedRes['columns'] ?? [],
                        'rows'          => array_slice($decodedRes['rows'], 0, 50),
                        'instruction'   => "ANALYST NOTE: Results are truncated for display. If the user asked for a 'total' or 'summary', you MUST ensure your SQL uses SUM() and GROUP BY only on identity columns (like branch name) to avoid seeing individual rows. NEVER repeat technical 'truncated' strings to the user."
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
                    'role'         => 'tool',
                    'tool_call_id' => $toolCallId,
                    'name'         => $toolName,
                    'content'      => $aiContent,
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
        $before = request('before'); // cursor: created_at timestamp

        $query = ChatMessage::where('chat_session_id', $session->id)
            ->orderBy('created_at', 'desc') // ambil dari belakang dulu untuk pagination
            ->limit($limit + 1);            // ambil 1 ekstra untuk deteksi has_more

        if ($before) {
            $query->where('created_at', '<', $before);
        }

        $messages     = $query->get();
        $hasMore      = $messages->count() > $limit;
        $messages     = $messages->take($limit)->sortBy('created_at')->values(); // kembalikan urutan ASC
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

    /**
     * Export tabel ke Excel (.xlsx)
     * Dipanggil dari tombol Export Excel di smart table frontend.
     */
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

        // Pastikan rows adalah array of arrays (bukan array of objects dari JSON)
        $normalizedRows = array_map(function ($row) {
            return is_array($row) ? array_values($row) : (array) $row;
        }, $rows);

        $export = new \App\Exports\ChatTableExport($headers, $normalizedRows, $title, null, $currencyColumns);

        return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
    }

    /**
     * Export tabel ke PDF
     * Dipanggil dari tombol Export PDF di smart table dan chart frontend.
     */
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

        // Normalise rows
        $normalizedRows = array_map(function ($row) {
            return is_array($row) ? array_values($row) : (array) $row;
        }, $rows);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.pdf-table', [
            'title'           => $title,
            'headers'         => $headers,
            'rows'            => $normalizedRows,
            'currencyColumns' => $currencyColumns,
            'generatedAt'     => now()->format('d M Y H:i'),
            // Variabel tambahan yang dibutuhkan view
            'colCount'        => count($headers),
            'fontSize'        => count($headers) > 10 ? 7 : (count($headers) > 7 ? 8 : 9),
            'chartImage'      => null,
            'columnTypes'     => array_map(function($header) use ($currencyColumns) {
                $normalized = strtolower(preg_replace('/[\s_]+/', '_', $header));
                $normCols   = array_map(fn($c) => strtolower(preg_replace('/[\s_]+/', '_', $c)), $currencyColumns);
                if (in_array($normalized, $normCols)) return 'currency';
                if (preg_match('/(qty|jumlah|count|total|amount|nilai)/i', $header)) return 'number';
                return 'text';
            }, $headers),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    private function callAiApi(array $messages, array $tools, $apiKey, $model, $maxTokens = 32768, string $systemPrompt = ''): ?array
    {
        $providerCode = $apiKey->provider->code;
        $maxTokens    = $maxTokens ?? 32768;

        $formattedTools    = $this->formatToolsForProvider($providerCode, $tools);
        $formattedMessages = $this->formatMessagesForProvider($providerCode, $messages);

        if ($providerCode === 'gemini')  return $this->callGeminiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        if ($providerCode === 'claude')  return $this->callClaudeApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        if ($providerCode === 'custom')  return $this->callCustomApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
        if ($providerCode === 'mistral') return $this->callMistralApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);

        return $this->callOpenAiApi($formattedMessages, $formattedTools, $apiKey, $model, $maxTokens, $systemPrompt);
    }

    private function formatToolsForProvider(string $providerCode, array $tools): array
    {
        if (empty($tools)) return [];

        if ($providerCode === 'gemini') {
            $geminiTools = [];
            foreach ($tools as $t) {
                $f = isset($t['function']) ? $t['function'] : $t;
                $geminiTools[] = [
                    'name'        => $f['name'],
                    'description' => $f['description'],
                    'parameters'  => $f['parameters']
                ];
            }
            return [['function_declarations' => $geminiTools]];
        }

        if ($providerCode === 'claude') {
            $claudeTools = [];
            foreach ($tools as $t) {
                $f = isset($t['function']) ? $t['function'] : $t;
                $claudeTools[] = [
                    'name'         => $f['name'],
                    'description'  => $f['description'],
                    'input_schema' => $f['parameters']
                ];
            }
            return $claudeTools;
        }

        // Standard OpenAI format (OpenAI, Mistral, Custom)
        $standardTools = [];
        foreach ($tools as $t) {
            if (isset($t['function'])) {
                $standardTools[] = $t;
            } else {
                $standardTools[] = [
                    'type'     => 'function',
                    'function' => [
                        'name'        => $t['name'],
                        'description' => $t['description'] ?? '',
                        'parameters'  => $t['parameters'] ?? (object)[],
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
                if ($m['role'] === 'system') continue;

                $role = $m['role'];
                $parts = [];

                if ($role === 'tool') {
                    $parts[] = [
                        'functionResponse' => [
                            'name'     => $m['name'] ?? 'query',
                            'response' => (object)['content' => $m['content']]
                        ]
                    ];
                    $geminiMessages[] = ['role' => 'user', 'parts' => $parts];
                    continue;
                }

                if (!empty($m['content'])) {
                    $parts[] = ['text' => (string)$m['content']];
                }

                if (!empty($m['tool_calls'])) {
                    foreach ($m['tool_calls'] as $tc) {
                        $args = $tc['function']['arguments'] ?? '{}';
                        $parts[] = [
                            'functionCall' => [
                                'name' => $tc['function']['name'],
                                'args' => is_string($args) ? (json_decode($args, true) ?? (object)[]) : $args
                            ]
                        ];
                    }
                }

                if (!empty($parts)) {
                    $geminiMessages[] = ['role' => ($role === 'assistant') ? 'model' : 'user', 'parts' => $parts];
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
                            'tool_use_id' => $m['tool_call_id'] ?? ('call_' . uniqid()),
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
                            'id'    => $tc['id'] ?? ('call_' . uniqid()),
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

        // Mistral / OpenAI / Custom — sudah dalam format yang benar
        return $messages;
    }

    private function buildMessages(string $systemPrompt, array $history, string $userMessage, string $lang): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Batasi history agar context window tidak meluap
        $recentHistory = array_slice($history, -$this->maxHistory);

        foreach ($recentHistory as $msg) {
            $toolResults = $msg['tool_results'] ?? null;
            if ($msg['role'] === 'assistant' && !empty($toolResults)) {
                $fakeToolCalls = [];
                foreach ($toolResults as $res) {
                    $fakeToolCalls[] = [
                        'id'       => 'call_' . uniqid(),
                        'type'     => 'function',
                        'function' => ['name' => $res['tool_name'] ?? 'query', 'arguments' => '{}']
                    ];
                }
                $messages[] = ['role' => 'assistant', 'content' => $msg['content'] ?? '', 'tool_calls' => $fakeToolCalls];
                foreach ($toolResults as $index => $res) {
                    // FIX: Truncate large tool results in history to avoid context overflow
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
                        'tool_call_id' => $fakeToolCalls[$index]['id'],
                        'name'         => $res['tool_name'] ?? 'query',
                        'content'      => $toolContent,
                    ];
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
    // FIX v2: Hapus contoh query dengan nama kolom placeholder (kolom_hpp_aktual, dll)
    //         yang menyebabkan AI menebak nama kolom alih-alih memanggil describe_table.
    //         Tambah seksi KAMUS ISTILAH BISNIS → NAMA KOLOM dan CHECKPOINT WAJIB.
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

## KONTEKS WAKTU (SANGAT PENTING):
- **Tanggal Sekarang**: {$currentTime}
- **Penting**: Pastikan Anda menyadari bahwa hari ini adalah tahun 2026. Analisis data tahun 2025 adalah data historis (masa lalu), bukan masa depan.

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
3. `describe_table` — Dapatkan tipe data kolom presisi untuk tabel tertentu. WAJIB DIPANGGIL sebelum execute_query.
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
- **PENCARIAN MANDIRI**: Anda memiliki daftar semua tabel BESERTA KOLOMNYA. GUNAKAN ITU.
- **JANGAN BERTANYA** kepada Bapak/Ibu. Temukan data secara mandiri.
- **PRINSIP KECEPATAN**: Setelah `execute_query`, SEGERA sajikan data + insight.

## 🔴 LARANGAN MUTLAK — TEBAK NAMA KOLOM (PALING KRITIS)

**INI ADALAH ATURAN TERPENTING. PELANGGARAN = HASIL SALAH.**

Kata-kata bisnis yang diucapkan user (seperti "HPP", "netto", "diskon", "profit", "omzet") adalah **ISTILAH BISNIS**, bukan nama kolom di database.

**JANGAN PERNAH** langsung menulis query dengan nama kolom seperti:
- `hpp`, `total_hpp`, `harga_pokok` — karena nama kolom asli mungkin `val_cost`, `amount_cogs`, `biaya_pokok`, atau nama lain sama sekali
- `netto`, `total_netto`, `net_sales` — karena nama kolom asli mungkin `val_netto`, `amount_net`, `sales_net`, dll
- `diskon`, `total_diskon`, `disc` — karena nama kolom asli mungkin `val_disc`, `potongan`, `rebate`, dll
- `profit`, `laba`, `margin` — biasanya bukan kolom, melainkan hasil kalkulasi dari 2 kolom lain
- `periode_bulan`, `periode_tahun`, `bulan`, `tahun` — nama kolom tanggal asli harus dari describe_table

**CHECKPOINT WAJIB SEBELUM execute_query:**
Tanyakan pada diri sendiri: *"Apakah SETIAP nama kolom yang saya gunakan di query ini berasal dari hasil `describe_table` yang baru saya panggil di loop ini?"*
- Jika YA → boleh execute_query
- Jika TIDAK atau RAGU → WAJIB panggil describe_table terlebih dahulu

## ⚠️ PROTOKOL WAJIB — URUTAN LANGKAH (tidak boleh dilewati)

1. Panggil `get_database_schema_info` → dapatkan daftar database, schema, dan nama tabel.
2. **Scan nama tabel** dalam hasil. Identifikasi 1–2 tabel yang paling relevan dengan pertanyaan user.
3. Panggil `describe_table` dengan **nama EKSAK** dari hasil langkah 2 — WAJIB, tidak boleh dilewati.
4. Baca daftar kolom dari hasil `describe_table`. Identifikasi kolom yang sesuai dengan kebutuhan query (kolom tanggal, kolom nilai uang, kolom filter cabang/dealer, dll).
5. Jika perlu cek isi data kolom kategori → panggil `get_column_values`. Skip jika timeout.
6. Bangun query SQL menggunakan **hanya nama kolom yang muncul di hasil describe_table langkah 3**.
7. Panggil `execute_query` dengan query yang sudah terverifikasi.
8. Sajikan hasil + Insight Strategis.

**ATURAN TAMBAHAN:**
- `describe_table` WAJIB selalu menerima nama yang spesifik dan eksak. **JANGAN sekali pun menggunakan "*".**
- Jika `is_eager_loaded: true` dan kolom sudah lengkap di hasil schema → boleh skip describe_table hanya jika yakin kolom sudah cukup detail. Jika ragu → tetap panggil describe_table.
- Panggil `search_schema` paling banyak SATU KALI per pertanyaan — hanya jika benar-benar tidak bisa identifikasi tabel dari hasil schema.
- **JANGAN panggil describe_table lebih dari 3 kali per pertanyaan.**

## ATURAN SQL PENTING — BACA DENGAN SEKSAMA
- **WAJIB PREFIX**: Selalu sebut nama tabel lengkap dengan skemanya: `schema_name.table_name`.
- **AMBIGUITAS KOLOM (JOIN)**: Selalu gunakan Alias Tabel yang unik dan beri awalan pada semua kolom di SELECT dan WHERE.
- SELECT saja — dilarang INSERT/UPDATE/DELETE/DROP.
- **PENALARAN RUMUS BISNIS (WAJIB)**: Saat menghitung Profit/Laba, WAJIB identifikasi kolom Net Sales dan HPP dari hasil `describe_table`. Profit = SUM(kolom_netsales_aktual) - SUM(kolom_hpp_aktual). Nama kolom ini harus berasal dari describe_table, bukan tebakan.
- **PENCARIAN TEKS (FUZZY MATCH)**: JANGAN gunakan `= 'X'` atau `ILIKE '%Kata1 Kata2%'` yang kaku. WAJIB pecah kata kunci: `kolom ILIKE '%kata1%' AND kolom ILIKE '%kata2%'`.
- **ALIAS (WAJIB)**: Gunakan alias yang elegan dengan Title Case: `AS "Total Penjualan Bersih"`.
- **PEMBULATAN AGREGAT (WAJIB)**: Lakukan `SUM()` pada nilai asli, lalu bulatkan hanya pada hasil akhir: `ROUND(SUM(kolom), 0)`.
- **OPTIMASI FILTER TANGGAL (WAJIB)**: SELALU gunakan BETWEEN pada kolom DATE/TIMESTAMP aktual dari describe_table. JANGAN pernah gunakan nama kolom tebakan seperti `periode_bulan` atau `periode_tahun`.
- **SMART LIMIT**: Ambil SEMUA baris jika user minta "lihat", "tampilkan". Gunakan LIMIT hanya jika user minta angka spesifik.
- **KOREKSI MANDIRI (WAJIB)**: Jika tool apapun mengembalikan field `MANDATORY_AI_ACTION`, WAJIB ikuti instruksi tersebut. JANGAN menyerah.
- **⛔ ATURAN GROUP BY (SANGAT KRITIS)**: Jika user meminta "total", "jumlah", atau ringkasan PER ENTITAS (per cabang, per dealer, per bulan), maka:
  - GROUP BY hanya boleh menggunakan kolom IDENTITAS/DIMENSI (seperti nama_cabang, nama_dealer, periode_bulan, tgl_faktur).
  - **SANGAT DILARANG** memasukkan kolom NILAI/MONETER (hrg_pokok, total_netto, hrg_jual, diskon, profit) ke dalam klausa GROUP BY.
  - Jika user meminta campuran detail (misal: "tampilkan hpp dan total hpp"), Anda **WAJIB** memprioritaskan ringkasan agregat (SUM) dan **TIDAK BOLEH** menyertakan harga satuan di GROUP BY.
  - Contoh BENAR: `SELECT nama_cabang, SUM(hrg_pokok) AS "Total HPP" ... GROUP BY nama_cabang`
  - Contoh SALAH: `GROUP BY nama_cabang, hrg_pokok, total_netto` ← Ini akan merusak hasil karena data tampil per transaksi, bukan per cabang.

- **🚫 LARANGAN ISTILAH TEKNIS**: DILARANG KERAS mengulangi pesan teknis sistem seperti "Data truncated", "Showing 50 rows", "Tool results", atau internal log lainnya kepada Bapak/Ibu. Gunakan bahasa analis bisnis yang elegan, contoh: *"Menampilkan sampel 50 transaksi teratas untuk Bapak/Ibu..."* atau *"Ringkasan di bawah ini mencakup performa utama..."*.

## 🚨 PROTOKOL TIMEOUT & HASIL KOSONG — WAJIB DIIKUTI (KRITIS UNTUK MISTRAL)

Jika `get_column_values` mengembalikan `warning` atau field `MANDATORY_AI_ACTION`:
- **JANGAN tunggu, retry, atau berdebat** — langsung lewati dan lanjutkan ke `describe_table`.
- `get_column_values` tidak bisa dipakai pada VIEW besar — ini normal, bukan error fatal.

Jika `execute_query` mengembalikan `"error": "QUERY_TIMEOUT"` atau `rows: []` dengan field `MANDATORY_AI_ACTION`:
1. **DILARANG KERAS** menyimpulkan "data tidak tersedia" atau "belum tercatat".
2. **DILARANG** memberi rekomendasi kepada user untuk mencoba bulan/periode lain.
3. **WAJIB** panggil `describe_table` untuk tabel yang sama guna mendapatkan nama kolom DATE/TIMESTAMP yang TERVERIFIKASI.
4. **WAJIB** buat ulang `execute_query` dengan kolom-kolom yang benar dari describe_table.
5. Ulangi minimal **3 kali** sebelum boleh menyatakan ada kendala teknis kepada user.
6. Jika setelah 3 kali masih gagal: *"Saya sedang melakukan penyesuaian teknis pada sistem analisis. Mohon coba beberapa saat lagi."*

## IDENTIFIKASI MATA UANG (KRITIS)
- Saat memanggil `execute_query`, WAJIB identifikasi kolom uang (price, netto, total, amount, fee, hpp, cost) ke dalam parameter `currency_columns`.
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

    // ─────────────────────────────────────────────────────────────────────────
    // SYSTEM PROMPT — ENGLISH
    // FIX v2: Removed placeholder column name examples that caused AI to guess
    //         column names. Added BUSINESS TERM → COLUMN NAME warning section
    //         and mandatory pre-query checkpoint.
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

## TIME CONTEXT (CRITICAL):
- **Current Date**: {$currentTime}
- **Important**: Be aware that today is in the year 2026. Analyzing data from 2025 is historical data, not future data.

## AVAILABLE DATABASES FOR THIS USER:
{$dbSummaryText}

## PERSONA & STYLE
- **Persona**: Expert Data Analyst, professional, objective, and highly meticulous.
- **Language**: Professional Business English.
- **Tone**: Polite, executive, and informative. Always address the user as "Mr./Ms.".
- **Response Structure (MANDATORY)**:
    1. **Executive Summary**: 1-2 bold sentences summarizing the core finding directly.
    2. **Visualization/Data (Optional)**: Use Smart Table or Chart. If result is ONLY 1 aggregate value, SKIP THIS SECTION.
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

- **⛔ GROUP BY RULE (CRITICAL)**: When a user asks for "totals" or "summaries" PER ENTITY (by branch, by dealer, by month):
  - GROUP BY must ONLY include IDENTITY/DIMENSION columns (e.g., branch_name, dealer_name, month).
  - **STRICTLY PROHIBITED**: Adding MONETARY/VALUE columns (price, cost, cogs, netto, profit) to the GROUP BY clause.
  - If a user asks for both individual values and totals (e.g., "show cost and total cost"), you **MUST** prioritize the aggregate summary and **NOT** include the individual cost in the GROUP BY.
  - CORRECT Example: `SELECT branch_name, SUM(cost) AS "Total Cost" ... GROUP BY branch_name`
  - INCORRECT Example: `GROUP BY branch_name, price, netto` ← This breaks the summary by showing one row per price value.

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
```smart_table
{}
```
```chart
{"type": "bar", "data": {"labels":["A"],"datasets":[{"label":"Data","data":[10]}]}}
```

## PROMPT RECOMMENDATIONS
End EVERY analysis with 3-4 specific next prompt suggestions relevant to the current data.

Respond ENTIRELY in ENGLISH.
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
        if ($response->status() === 429) {
            return null;
        }

        if ($response->failed()) {
            Log::error("[Agentic] API Error ({$providerCode}): " . $response->body());
            return null;
        }

        $data = $response->json();

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

        // Mistral / OpenAI / Custom — sudah dalam format yang benar
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
        $response = Http::timeout(600)->retry(3, 2000)->post($url, $payload);

        if ($response->status() === 503 && $currentModelName !== 'gemini-1.5-flash') {
            Log::warning("[Agentic] Model {$currentModelName} busy (503). Falling back to gemini-1.5-flash.");
            $fallbackUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey->api_key;
            $response = Http::timeout(600)->retry(2, 2000)->post($fallbackUrl, $payload);
        }
        return $this->handleProviderResponse($response, 'gemini');
    }

    private function callCustomApi(array $messages, array $tools, $apiKey, $model, $maxTokens, string $systemPrompt = ''): ?array
    {
        $baseUrl = rtrim($apiKey->provider->base_url ?? 'https://api.openai.com', '/');
        $url = $baseUrl . '/chat/completions';
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
            ->withToken($apiKey->api_key)
            ->post($url, $payload);
        return $this->handleProviderResponse($response, 'custom');
    }
}
