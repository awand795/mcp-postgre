<?php

namespace App\Http\Controllers;

use App\Helpers\LanguageDetector;
use App\Services\ToolCallExecutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AgenticChatbotController — Opsi A: Tool Calling (Agentic Loop)
 *
 * Provider chain:
 *   1. NVIDIA NIM  — gratis jika key valid + model tersedia
 *   2. Groq        — gratis tier generous, SANGAT DIREKOMENDASIKAN sebagai primary jika NVIDIA bermasalah
 *   3. OpenRouter  — last resort
 *
 * Untuk aktifkan Groq:
 *   1. Daftar gratis di https://console.groq.com
 *   2. Buat API Key
 *   3. Tambahkan ke .env: GROQ_API_KEY=gsk_xxxxx
 */
class AgenticChatbotController extends Controller
{
    // ── NVIDIA NIM ────────────────────────────────────────────────────────────
    private string $nvidiaUrl = 'https://integrate.api.nvidia.com/v1/chat/completions';
    // Model diurutkan dari yang paling sering tersedia gratis
    private array $nvidiaModels = [
        'meta/llama-3.3-70b-instruct',
        'meta/llama-3.1-8b-instruct',
        'meta/llama-3.2-3b-instruct',
        'mistralai/mistral-nemo-12b-instruct',
        'nvidia/llama-3.1-nemotron-nano-8b-instruct',
    ];

    // ── Groq (DIREKOMENDASIKAN sebagai fallback utama) ─────────────────────
    private string $groqUrl = 'https://api.groq.com/openai/v1/chat/completions';
    private array $groqModels = [
        'llama-3.3-70b-versatile',    // Best, support tool calling
        'llama-3.1-70b-versatile',    // Alternatif
        'llama-3.1-8b-instant',       // Cepat, support tools
        'mixtral-8x7b-32768',         // Support tools
    ];

    // ── OpenRouter (last resort, berbayar) ────────────────────────────────────
    private string $openrouterUrl = 'https://openrouter.ai/api/v1/chat/completions';
    private array $openrouterModels = [
        'mistralai/mistral-7b-instruct:free',
        'meta-llama/llama-3.1-70b-instruct:free',
    ];

    private int $maxToolLoops = 8;
    private int $maxHistory   = 10;
    private int $maxTokens    = 2048;

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

        $message       = $request->input('message', '');
        $history       = $request->input('history', []);
        $nvidiaKey     = env('NVIDIA_API_KEY');
        $groqKey       = env('GROQ_API_KEY');
        $openrouterKey = env('OPENROUTER_API_KEY');

        Log::info("[Agentic] New message: " . substr($message, 0, 100));

        if (!$nvidiaKey && !$groqKey && !$openrouterKey) {
            return response()->json([
                'error' => 'Tidak ada API key yang valid. Isi salah satu: NVIDIA_API_KEY, GROQ_API_KEY, atau OPENROUTER_API_KEY di .env'
            ]);
        }

        $detectedLang = $this->langDetector->detect($message);
        $systemPrompt = $this->buildSystemPrompt($detectedLang);
        $messages     = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);

        session_write_close();

        return response()->stream(
            function () use ($messages, $nvidiaKey, $groqKey, $openrouterKey, $detectedLang) {
                $this->runAgenticLoop($messages, $nvidiaKey, $groqKey, $openrouterKey, $detectedLang);
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
    private function runAgenticLoop(
        array   $messages,
        ?string $nvidiaKey,
        ?string $groqKey,
        ?string $openrouterKey,
        string  $lang
    ): void {
        echo "data: " . json_encode(['chunk' => '', 'status' => 'thinking']) . "\n\n";
        ob_flush(); flush();

        $tools     = ToolCallExecutor::getToolDefinitions();
        $loopCount = 0;

        while ($loopCount < $this->maxToolLoops) {
            $loopCount++;
            Log::info("[Agentic] ── Loop #{$loopCount} ──");

            [$response, $providerUsed] = $this->callBestProvider(
                $messages, $tools, $nvidiaKey, $groqKey, $openrouterKey
            );

            if (!$response) {
                // Tampilkan pesan error yang lebih informatif
                $errMsg = $lang === 'en'
                    ? "⚠️ All AI providers failed. Please check:\n"
                      . "- **NVIDIA**: Key may need refresh at https://build.nvidia.com\n"
                      . "- **Groq** (recommended, free): Register at https://console.groq.com and add GROQ_API_KEY to .env\n"
                      . "- Check `storage/logs/laravel.log` for detailed errors."
                    : "⚠️ Semua layanan AI gagal merespons. Silakan periksa:\n"
                      . "- **NVIDIA**: Key mungkin perlu diperbarui di https://build.nvidia.com\n"
                      . "- **Groq** (rekomendasi, gratis): Daftar di https://console.groq.com lalu tambahkan GROQ_API_KEY ke .env\n"
                      . "- Cek detail error di `storage/logs/laravel.log`";

                $this->streamText($errMsg);
                echo "data: [DONE]\n\n";
                ob_flush(); flush();
                return;
            }

            Log::info("[Agentic] Provider: {$providerUsed}");

            $choice       = $response['choices'][0] ?? null;
            $finishReason = $choice['finish_reason'] ?? 'stop';
            $messageObj   = $choice['message'] ?? [];
            $toolCalls    = $messageObj['tool_calls'] ?? [];

            // Tambah respons AI ke percakapan
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
                        ? "I couldn't generate an answer. Please rephrase your question."
                        : "Tidak bisa menghasilkan jawaban. Coba ulangi pertanyaan dengan kalimat berbeda.";
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
            ? "Reached maximum query iterations. Please rephrase your question."
            : "Sudah mencapai batas iterasi query. Coba ulangi pertanyaan dengan kalimat berbeda.";
        $this->streamText($msg);
        echo "data: [DONE]\n\n";
        ob_flush(); flush();
    }

    // ── Coba semua provider, return [response, providerName] ──────────────────
    private function callBestProvider(
        array   $messages,
        array   $tools,
        ?string $nvidiaKey,
        ?string $groqKey,
        ?string $openrouterKey
    ): array {
        // 1. NVIDIA NIM
        if ($nvidiaKey) {
            foreach ($this->nvidiaModels as $model) {
                $result = $this->callAPI($this->nvidiaUrl, $nvidiaKey, $model, $messages, $tools);
                if ($result !== null) {
                    return [$result, "NVIDIA/{$model}"];
                }
            }
        }

        // 2. Groq
        if ($groqKey) {
            foreach ($this->groqModels as $model) {
                $result = $this->callAPI($this->groqUrl, $groqKey, $model, $messages, $tools);
                if ($result !== null) {
                    return [$result, "Groq/{$model}"];
                }
            }
        }

        // 3. OpenRouter
        if ($openrouterKey) {
            foreach ($this->openrouterModels as $model) {
                $result = $this->callAPI($this->openrouterUrl, $openrouterKey, $model, $messages, $tools, true);
                if ($result !== null) {
                    return [$result, "OpenRouter/{$model}"];
                }
            }
        }

        return [null, 'none'];
    }

    // ── Panggil satu model API ────────────────────────────────────────────────
    private function callAPI(
        string $apiUrl,
        string $apiKey,
        string $model,
        array  $messages,
        array  $tools,
        bool   $isOpenRouter = false
    ): ?array {
        // Bersihkan messages
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
                // Content bisa null saat ada tool_calls (valid per OpenAI spec)
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

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($isOpenRouter) {
            $headers[] = 'HTTP-Referer: ' . env('APP_URL', 'http://localhost');
            $headers[] = 'X-Title: MCP Chatbot';
        }

        Log::info("[Agentic] Trying: {$apiUrl} | {$model}");

        try {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                Log::error("[Agentic] cURL [{$model}]: {$curlErr}");
                return null;
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                Log::error("[Agentic] HTTP {$httpCode} [{$model}]: " . substr($body, 0, 300));
                return null;
            }

            $decoded = json_decode($body, true);
            if (!$decoded || isset($decoded['error'])) {
                Log::error("[Agentic] API err [{$model}]: " . substr($body, 0, 200));
                return null;
            }
            if (empty($decoded['choices'])) {
                Log::error("[Agentic] No choices [{$model}]");
                return null;
            }

            return $decoded;

        } catch (\Throwable $e) {
            Log::error("[Agentic] Exception [{$model}]: " . $e->getMessage());
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
    private function buildSystemPrompt(string $lang): string
    {
        $tableList = implode(', ', $this->toolExecutor->getAllowedTables());

        if ($lang === 'en') {
            return <<<PROMPT
You are DataBot, an expert AI Data Analyst with **direct access to a PostgreSQL database** via tools.

## TOOLS AVAILABLE
1. `get_schema_info` — Get all tables and their columns at once. **Call this FIRST.**
2. `list_tables`     — List accessible tables.
3. `describe_table`  — Get columns/types for a specific table.
4. `execute_query`   — Run a SQL SELECT query.

## WORKFLOW
1. Call `get_schema_info` first to understand the schema.
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
Anda adalah DataBot, AI Analis Data yang memiliki **akses langsung ke database PostgreSQL** melalui tools.

## TOOLS YANG TERSEDIA
1. `get_schema_info` — Ambil semua tabel dan kolomnya sekaligus. **Panggil ini PERTAMA.**
2. `list_tables`     — Lihat daftar tabel yang bisa diakses.
3. `describe_table`  — Detail kolom tabel tertentu.
4. `execute_query`   — Jalankan SQL SELECT.

## ALUR KERJA
1. Panggil `get_schema_info` untuk memahami schema database.
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
