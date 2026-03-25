<?php

namespace App\Http\Controllers;

use App\Helpers\LanguageDetector;
use App\Services\ToolCallExecutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AgenticChatbotController — Tool Calling (Agentic Loop)
 * Provider: OpenAI dengan fallback otomatis antar model
 * Urutan: gpt-4o-mini → gpt-4o → gpt-4-turbo
 */
class AgenticChatbotController extends Controller
{
    private string $openaiUrl = 'https://api.openai.com/v1/chat/completions';
    private string $openaiModel = 'gpt-4o-mini';

    // Fallback models jika model utama gagal (rate limit, overload, dll)
    private array $fallbackModels = [
        'gpt-4o',
        'gpt-4-turbo',
    ];

    private int $maxToolLoops = 8;
    private int $maxHistory   = 10;
    private int $maxTokens    = 4096; // Ditingkatkan: 2048 sering terpotong untuk analisis bisnis panjang

    private LanguageDetector $langDetector;
    private ToolCallExecutor  $toolExecutor;

    public function __construct()
    {
        $this->langDetector = new LanguageDetector();
        $this->toolExecutor = new ToolCallExecutor();
    }

    public function index()
    {
        return view('chatbot');
    }

    // ── Endpoint utama ────────────────────────────────────────────────────────
    public function send(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $message   = $request->input('message', '');
        $history   = $request->input('history', []);
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

        $systemPrompt = $this->buildSystemPrompt($detectedLang, $allowedTables);
        $messages     = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);

        session_write_close();

        return response()->stream(
            function () use ($messages, $openaiKey, $detectedLang, $allowedTables) {
                $this->runAgenticLoop($messages, $openaiKey, $detectedLang, $this->openaiModel, $allowedTables);
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

    // ── Agentic Loop ──────────────────────────────────────────────────────────
    private function runAgenticLoop(array $messages, string $openaiKey, string $lang, string $model, array $allowedTables = []): void
    {
        echo "data: " . json_encode(['chunk' => '', 'status' => 'thinking']) . "\n\n";
        ob_flush(); flush();

        // FIX: Pass allowedTables into executor so it doesn't rely on Auth::check() inside stream
        $this->toolExecutor->setAllowedTables($allowedTables);

        $tools     = ToolCallExecutor::getToolDefinitions();
        $loopCount = 0;

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            Log::info("[Agentic] ── Loop #{$loopCount} ──");

            $response = $this->callOpenAI($messages, $tools, $openaiKey, $model);

            // ── Fallback ke model OpenAI lain jika gagal ─────────────────────
            if (!$response) {
                $tried    = [$model];
                $fallback = null;

                foreach ($this->fallbackModels as $fbModel) {
                    if (in_array($fbModel, $tried)) continue;

                    Log::warning("[Agentic] Model {$model} gagal, mencoba fallback: {$fbModel}");

                    $notif = $lang === 'en'
                        ? "🔄 System is optimizing performance, please wait a moment..."
                        : "🔄 Sistem sedang mengoptimalkan performa, mohon tunggu sebentar...";

                    echo "data: " . json_encode(['chunk' => $notif . "\n\n"]) . "\n\n";
                    ob_flush(); flush();

                    $fallback = $this->callOpenAI($messages, $tools, $openaiKey, $fbModel);
                    $tried[]  = $fbModel;

                    if ($fallback) {
                        $model    = $fbModel;   // pakai model ini untuk sisa loop
                        $response = $fallback;
                        Log::info("[Agentic] Fallback berhasil menggunakan: {$fbModel}");
                        break;
                    }
                }

                // Semua model gagal
                if (!$response) {
                    $triedList  = implode(', ', $tried);
                    $errMsg = $lang === 'en'
                        ? "Apologies, our system is currently under high load. Please try again in a moment."
                        : "Mohon maaf, sistem kami sedang mengalami gangguan sementara. Silakan coba beberapa saat lagi.";

                    Log::error("[Agentic] Semua model gagal: {$triedList}");
                    $this->streamText($errMsg);
                    echo "data: [DONE]\n\n";
                    ob_flush(); flush();
                    return;
                }
            }

            $choice       = $response['choices'][0] ?? null;
            $finishReason = $choice['finish_reason'] ?? 'stop';
            $messageObj   = $choice['message'] ?? [];
            $toolCalls    = $messageObj['tool_calls'] ?? [];

            $assistantMsg = [
                'role'    => 'assistant',
                'content' => $messageObj['content'] ?? null,
            ];
            if (!empty($toolCalls)) {
                $assistantMsg['tool_calls'] = $toolCalls;
            }
            $messages[] = $assistantMsg;

            // ── Jawaban final ─────────────────────────────────────────────────
            if (empty($toolCalls) || $finishReason === 'stop') {
                $finalContent = trim($messageObj['content'] ?? '');
                if (empty($finalContent)) {
                    $finalContent = $lang === 'en'
                        ? "I'm sorry, I was unable to process your request at this time. Please try rephrasing your question."
                        : "Mohon maaf, permintaan Anda tidak dapat diproses saat ini. Silakan coba dengan pertanyaan yang berbeda.";
                }
                $this->streamText($finalContent);
                echo "data: " . json_encode(['history' => $this->extractClientHistory($messages)]) . "\n\n";
                echo "data: [DONE]\n\n";
                ob_flush(); flush();
                return;
            }

            // ── Eksekusi tool calls ───────────────────────────────────────────
            foreach ($toolCalls as $toolCall) {
                $toolCallId = $toolCall['id'] ?? ('call_' . uniqid());
                $toolName   = $toolCall['function']['name'] ?? '';
                $argsRaw    = $toolCall['function']['arguments'] ?? '{}';
                $arguments  = is_string($argsRaw) ? (json_decode($argsRaw, true) ?? []) : $argsRaw;

                Log::info("[Agentic] → Tool: {$toolName}", $arguments);

                echo "data: " . json_encode([
                    'tool_call' => ['name' => $toolName, 'arguments' => $arguments, 'status' => 'running']
                ]) . "\n\n";
                ob_flush(); flush();

                $toolResult = $this->toolExecutor->execute($toolName, $arguments);
                Log::info("[Agentic] ← Result: " . strlen($toolResult) . " chars");

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content'      => $toolResult,
                ];

                echo "data: " . json_encode([
                    'tool_call' => ['name' => $toolName, 'status' => 'done']
                ]) . "\n\n";
                ob_flush(); flush();
            }
        }

        $msg = $lang === 'en'
            ? "I'm sorry, your request requires more processing than available. Please try a more specific question."
            : "Mohon maaf, permintaan Anda membutuhkan analisis yang terlalu kompleks. Silakan coba dengan pertanyaan yang lebih spesifik.";
        $this->streamText($msg);
        echo "data: [DONE]\n\n";
        ob_flush(); flush();
    }

    // ── Panggil OpenAI API ────────────────────────────────────────────────────
    private function callOpenAI(array $messages, array $tools, string $apiKey, string $model = ''): ?array
    {
        if (empty($model)) $model = $this->openaiModel;
        // Bersihkan messages sesuai OpenAI spec
        $cleanMessages = [];
        foreach ($messages as $msg) {
            $role  = $msg['role'] ?? '';
            $clean = ['role' => $role];

            if ($role === 'tool') {
                $clean['tool_call_id'] = $msg['tool_call_id'] ?? '';
                $clean['content']      = $msg['content'] ?? '';
            } elseif ($role === 'assistant') {
                if (!empty($msg['tool_calls'])) {
                    $clean['tool_calls'] = $msg['tool_calls'];
                }
                $clean['content'] = $msg['content'];
            } else {
                $clean['content'] = $msg['content'] ?? '';
            }

            $cleanMessages[] = $clean;
        }

        $payload = [
            'model'       => $model,
            'messages'    => $cleanMessages,
            'tools'       => $tools,
            'tool_choice' => 'auto',
            'max_tokens'  => $this->maxTokens,
            'temperature' => 0.2,
            'top_p'       => 0.9,
        ];

        Log::info("[Agentic] Calling OpenAI: {$model}");

        try {
            $ch = curl_init($this->openaiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                Log::error("[Agentic] cURL error: {$curlErr}");
                return null;
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                // ── LANGKAH 1: Logging detail error OpenAI ─────────────────
                Log::error("[Agentic] HTTP {$httpCode} — Full response body: {$body}");
                $decoded = json_decode($body, true);
                $errDetail = $decoded['error']['message'] ?? 'No error message from API';
                $errType   = $decoded['error']['type']   ?? 'unknown';
                $errCode   = $decoded['error']['code']   ?? 'unknown';
                Log::error("[Agentic] OpenAI Error Detail → type: {$errType}, code: {$errCode}, message: {$errDetail}");
                return null;
            }

            $decoded = json_decode($body, true);
            if (!$decoded || isset($decoded['error'])) {
                Log::error("[Agentic] API error — Full body: {$body}");
                $errDetail = $decoded['error']['message'] ?? 'Unknown API error';
                Log::error("[Agentic] API error detail: {$errDetail}");
                return null;
            }
            if (empty($decoded['choices'])) {
                Log::error("[Agentic] No choices in response");
                return null;
            }

            return $decoded;

        } catch (\Throwable $e) {
            Log::error("[Agentic] Exception: " . $e->getMessage());
            return null;
        }
    }

    // ── Stream teks ke browser via SSE ────────────────────────────────────────
    private function streamText(string $text): void
    {
        foreach (mb_str_split($text, 30) as $chunk) {
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
            ob_flush(); flush();
        }
    }

    // ── System prompt ─────────────────────────────────────────────────────────
    private function buildSystemPrompt(string $lang, array $allowedTables = []): string
    {
        // FIX: Use pre-resolved allowedTables (resolved before session_write_close)
        $tableList = implode(', ', $allowedTables ?: $this->toolExecutor->getAllowedTables());

        if ($lang === 'en') {
            return <<<PROMPT
You are DataBot, an expert AI Data Analyst with **direct access to a business database** via tools.

## TOOLS AVAILABLE
1. `get_schema_info` — Get all tables and their columns at once. **Call this FIRST.**
2. `list_tables`     — List accessible tables.
3. `describe_table`  — Get columns/types for a specific table.
4. `execute_query`   — Run a SQL SELECT query to retrieve business data.

## WORKFLOW
1. Call `get_schema_info` first to understand the business data structure.
2. Write precise SQL using correct column names.
3. Call `execute_query` with that SQL.
4. Analyze results and answer clearly in Markdown.
5. Run more queries if needed.

## SQL RULES
- Always prefix: `sch_mbi.table_name`
- SELECT only — no INSERT/UPDATE/DELETE
- List queries: always `LIMIT 50`
- Text filter: `ILIKE '%keyword%'`
- Year: `WHERE periode_tahun = '2025'`
- Province: `WHERE nama_propinsi_cabang ILIKE '%riau%'`
- City/district: `WHERE nama_kabupaten_cabang ILIKE '%medan%'`
- Product prediction for a city = top historical sellers there (GROUP BY nama_barang, ORDER BY SUM(qty_jual) DESC LIMIT 10)

## ACCESSIBLE TABLES
{$tableList}

Respond ENTIRELY in ENGLISH.
PROMPT;
        }

        return <<<PROMPT
Anda adalah DataBot, AI Analis Data yang memiliki **akses langsung ke data bisnis perusahaan** melalui tools.

## TOOLS YANG TERSEDIA
1. `get_schema_info` — Ambil semua tabel dan kolomnya sekaligus. **Panggil ini PERTAMA.**
2. `list_tables`     — Lihat daftar tabel yang bisa diakses.
3. `describe_table`  — Detail kolom tabel tertentu.
4. `execute_query`   — Ambil data bisnis dari database.

## ALUR KERJA
1. Panggil `get_schema_info` untuk memahami struktur data bisnis.
2. Tulis SQL yang tepat berdasarkan nama kolom yang ada.
3. Panggil `execute_query` dengan SQL tersebut.
4. Analisis hasilnya dan jawab dalam Markdown yang rapi.
5. Jalankan query tambahan jika diperlukan.

## ATURAN SQL
- Selalu prefix: `sch_mbi.nama_tabel`
- Hanya SELECT — tidak boleh INSERT/UPDATE/DELETE
- Query list: selalu `LIMIT 50`
- Filter teks: `ILIKE '%keyword%'`
- Filter tahun: `WHERE periode_tahun = '2025'`
- Filter provinsi: `WHERE nama_propinsi_cabang ILIKE '%sumatera utara%'`
- Filter kota/kabupaten: `WHERE nama_kabupaten_cabang ILIKE '%medan%'`
- Prediksi produk laku di kota = produk terlaris historis di kota itu (GROUP BY nama_barang, ORDER BY SUM(qty_jual) DESC LIMIT 10)

## TABEL YANG DAPAT DIAKSES
{$tableList}

Jawab SEPENUHNYA dalam BAHASA INDONESIA.
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

    // ── Ekstrak history untuk frontend ────────────────────────────────────────
    private function extractClientHistory(array $messages): array
    {
        $history = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            if ($role === 'user' && !empty($msg['content'])) {
                $history[] = ['role' => 'user', 'content' => $msg['content']];
            } elseif ($role === 'assistant' && !empty($msg['content'])) {
                $history[] = ['role' => 'assistant', 'content' => $msg['content']];
            }
        }
        return array_slice($history, -($this->maxHistory * 2));
    }
}
