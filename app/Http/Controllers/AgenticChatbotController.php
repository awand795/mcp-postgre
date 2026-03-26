<?php

namespace App\Http\Controllers;

use App\Helpers\LanguageDetector;
use App\Services\ToolCallExecutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AgenticChatbotController — Tool Calling (Agentic Loop)
 * Provider: OpenAI dengan fallback otomatis antar model
 * Urutan: gpt-5.4 → gpt-5.2 → gpt-4o
 */
class AgenticChatbotController extends Controller
{
    private string $openaiUrl = 'https://api.openai.com/v1/chat/completions';
    private string $openaiModel = 'gpt-5.4';

    // Fallback models jika model utama gagal (rate limit, overload, dll)
    private array $fallbackModels = [
        'gpt-5.2',
        'gpt-4o',
    ];

    private int $maxToolLoops = 20;
    private int $maxHistory = 20;
    private int $maxTokens = 65536; // Massive tokens for unlimited data rendering

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

    // ── Endpoint utama ────────────────────────────────────────────────────────
    public function send(Request $request)
    {
        set_time_limit(0); // UNLIMITED - NO TIMEOUT
        ini_set('memory_limit', '-1'); // UNLIMITED - NO MEMORY LIMIT

        $message = $request->input('message', '');
        $history = $request->input('history', []);
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
        $messages = $this->buildMessages($systemPrompt, $history, $message, $detectedLang);

        session_write_close();

        return response()->stream(
            function () use ($messages, $openaiKey, $detectedLang, $allowedTables) {
            $this->runAgenticLoop($messages, $openaiKey, $detectedLang, $this->openaiModel, $allowedTables);
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
    private function runAgenticLoop(array $messages, string $openaiKey, string $lang, string $model, array $allowedTables = []): void
    {
        echo "data: " . json_encode(['chunk' => '', 'status' => 'thinking']) . "\n\n";
        ob_flush();
        flush();

        // FIX: Pass allowedTables into executor so it doesn't rely on Auth::check() inside stream
        $this->toolExecutor->setAllowedTables($allowedTables);

        $tools = ToolCallExecutor::getToolDefinitions();
        $loopCount = 0;

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

            $choice = $response['choices'][0] ?? null;
            $finishReason = $choice['finish_reason'] ?? 'stop';
            $messageObj = $choice['message'] ?? [];
            $toolCalls = $messageObj['tool_calls'] ?? [];

            $assistantMsg = [
                'role' => 'assistant',
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

                echo "data: " . json_encode([
                    'tool_call' => ['name' => $toolName, 'arguments' => $arguments, 'status' => 'running']
                ]) . "\n\n";
                ob_flush();
                flush();

                $toolResult = $this->toolExecutor->execute($toolName, $arguments);
                Log::info("[Agentic] ← Result: " . strlen($toolResult) . " chars");

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => $toolResult,
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
        $this->streamText($msg);
        echo "data: [DONE]\n\n";
        ob_flush();
        flush();
    }

    // ── Panggil OpenAI API ────────────────────────────────────────────────────
    private function callOpenAI(array $messages, array $tools, string $apiKey, string $model = ''): ?array
    {
        if (empty($model))
            $model = $this->openaiModel;
        // Bersihkan messages sesuai OpenAI spec
        $cleanMessages = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? '';
            $clean = ['role' => $role];

            if ($role === 'tool') {
                $clean['tool_call_id'] = $msg['tool_call_id'] ?? '';
                $clean['content'] = $msg['content'] ?? '';
            }
            elseif ($role === 'assistant') {
                if (!empty($msg['tool_calls'])) {
                    $clean['tool_calls'] = $msg['tool_calls'];
                }
                $clean['content'] = $msg['content'];
            }
            else {
                $clean['content'] = $msg['content'] ?? '';
            }

            $cleanMessages[] = $clean;
        }

        // GPT-5.x family (gpt-5.4, gpt-5.2, gpt-5-mini, dll) requires reasoning_effort='none'
        // agar parameter temperature & top_p bisa digunakan. Tanpa ini API akan return error.
        // Ref: https://developers.openai.com/api/docs/models/gpt-5.4
        $isGpt5 = str_starts_with($model, 'gpt-5');

        $payload = [
            'model' => $model,
            'messages' => $cleanMessages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'max_tokens' => $this->maxTokens,
        ];

        if ($isGpt5) {
            // reasoning_effort=none = default, aktifkan temperature & top_p
            $payload['reasoning_effort'] = 'none';
        }

        $payload['temperature'] = 0.2;
        $payload['top_p'] = 0.9;

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
            if (empty($decoded['choices'])) {
                Log::error("[Agentic] No choices in response");
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
        // FIX: Use pre-resolved allowedTables (resolved before session_write_close)
        $tableList = implode(', ', $allowedTables ?: $this->toolExecutor->getAllowedTables());

        if ($lang === 'en') {
            return <<<PROMPT

You are DataBot, an expert AI Data Analyst for MBI (Motor Bisnis Indonesia) with **direct access to the business database** via tools.
This database contains sales, stock, purchases, targets, customers, and product master data for a spare parts/automotive company with multiple branches across Indonesia.

## TOOLS AVAILABLE
1. `get_schema_info` — Get all tables and their columns at once. **Call this FIRST before writing any SQL.**
2. `list_tables`     — List accessible tables.
3. `describe_table`  — Get columns/types for a specific table.
4. `execute_query`   — Run a SQL SELECT query to retrieve business data.

## WORKFLOW
1. Call `get_schema_info` first to understand the data structure.
2. Write precise SQL. **CRITICAL: ONLY SELECT the columns you absolutely need.** Do not use `SELECT *` if the table has many columns, as it causes massive data overload.
3. Call `execute_query` with that SQL.
4. Analyze results and answer clearly in Markdown with tables where applicable. **NEVER BE LAZY.** If the user asks for 50 rows, you MUST write all 50 rows in the Markdown table. Do not summarize or tell the user to check manually.
5. Run additional queries if deeper analysis is needed.

## SQL RULES — READ CAREFULLY
- Always prefix table names: `sch_mbi.table_name`
- SELECT only — no INSERT/UPDATE/DELETE/DROP
- **ANTI-LIMIT POLICY**: No row limit required. You can retrieve as many rows as needed to answer the user fully.
- **Select relevant columns**: While there is no limit, only select columns needed for the answer to maintain high performance.
- Text filter: use `ILIKE '%keyword%'`
- **Year filter**: `WHERE periode_tahun = '2025'`
- **Month filter**: `WHERE periode_bulan = '12'` ← use 2-digit string ('01'=Jan, '12'=Dec)
- **Year + Month combined**: `WHERE periode_tahun = '2025' AND periode_bulan = '12'`
- **Period column** (some tables use `periode` instead of separate tahun/bulan): format is 'YYYY-MM' e.g. `WHERE periode = '2025-12'`
- Province filter: `WHERE nama_propinsi_cabang ILIKE '%riau%'`
- City/district filter: `WHERE nama_kabupaten_cabang ILIKE '%medan%'`
- Regional filter: `WHERE nama_regional ILIKE '%sumatera%'`
- Sales value: use `total_netto` (net after discount+tax) or `total_dpp` (base price)
- Gross profit: use `gpn` column in view_data_ssr_mbi
- Stock balance: use `qty_saldo_akhir` / `hpp_saldo_akhir` in kartu_stock tables
- Target vs realisasi: use `view_data_target_realisasi_mbi` or `view_data_trm_mbi`
- Top selling products: GROUP BY nama_barang, ORDER BY SUM(qty_jual) DESC
- Always cast numeric aggregates: SUM(qty_jual::numeric) if needed
- **Total/Overall count**: ALWAYS run a separate `SELECT COUNT(*) FROM sch_mbi.table WHERE ...` BEFORE querying detailed data. Never use `rows_returned` as the total count—it only represents the returned rows (limited).

## DATA VISUALIZATION (CHARTS)
If the user asks for a chart/graph, or if you identify trend data that would look better visualized, provide the data in a custom `chart` code block using Chart.js JSON format:
```chart
{
  "type": "bar", // or 'line', 'pie', 'doughnut'
  "data": {
    "labels": ["Jan", "Feb", "Mar"],
    "datasets": [{
      "label": "2025 Sales",
      "data": [120, 150, 180],
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

## TOOLS YANG TERSEDIA
1. `get_schema_info` — Ambil semua tabel dan kolomnya sekaligus. **Panggil ini PERTAMA sebelum menulis SQL apapun.**
2. `list_tables`     — Lihat daftar tabel yang bisa diakses.
3. `describe_table`  — Detail kolom tabel tertentu.
4. `execute_query`   — Ambil data bisnis dari database.

## ALUR KERJA
1. Panggil `get_schema_info` untuk memahami struktur data.
2. Tulis SQL yang tepat berdasarkan schema. **PENTING: HANYA SELECT kolom yang benar-benar dibutuhkan.** Jangan gunakan `SELECT *` karena akan membuat data yang dikembalikan terlalu besar.
3. Panggil `execute_query` dengan SQL tersebut.
4. Analisis hasilnya dan jawab dalam Markdown. **JANGAN MALAS.** Jika user meminta 50 data pelanggan, TAMPILKAN SEMUA 50 data tersebut dalam tabel Markdown. Jangan merangkum atau menyuruh user mengecek secara menyeluruh.
5. Jalankan query tambahan jika diperlukan untuk analisis lebih dalam.

## ATURAN SQL — BACA DENGAN CERMAT
- Selalu prefix nama tabel: `sch_mbi.nama_tabel`
- Hanya SELECT — tidak boleh INSERT/UPDATE/DELETE/DROP
- **KEBIJAKAN ANTI-LIMIT**: Tidak ada batasan jumlah baris. Anda dapat mengambil data sebanyak apa pun yang dibutuhkan untuk menjawab user sepenuhnya.
- **Pilih kolom relevan**: Meskipun tidak ada limit, pilihlah kolom yang benar-benar dibutuhkan agar performa tetap maksimal.
- Filter teks: gunakan `ILIKE '%keyword%'`
- **Filter tahun**: `WHERE periode_tahun = '2025'`
- **Filter bulan**: `WHERE periode_bulan = '12'` ← gunakan string 2 digit ('01'=Jan, '12'=Des)
- **Filter tahun + bulan**: `WHERE periode_tahun = '2025' AND periode_bulan = '12'`
- **Kolom periode** (beberapa tabel pakai `periode` bukan tahun/bulan terpisah): format 'YYYY-MM', contoh: `WHERE periode = '2025-12'`
- Filter provinsi: `WHERE nama_propinsi_cabang ILIKE '%jawa barat%'`
- Filter kota/kabupaten: `WHERE nama_kabupaten_cabang ILIKE '%medan%'`
- Filter regional: `WHERE nama_regional ILIKE '%sumatera%'`
- Nilai penjualan: gunakan `total_netto` (setelah diskon+PPN) atau `total_dpp` (harga dasar)
- Laba kotor: gunakan kolom `gpn` di view_data_ssr_mbi
- Saldo stok: gunakan `qty_saldo_akhir` / `hpp_saldo_akhir` di tabel kartu_stock
- Target vs realisasi: gunakan `view_data_target_realisasi_mbi` atau `view_data_trm_mbi`
- Produk terlaris: GROUP BY nama_barang, ORDER BY SUM(qty_jual) DESC
- **Total/jumlah keseluruhan**: SELALU jalankan `SELECT COUNT(*) FROM sch_mbi.tabel WHERE ...` terpisah SEBELUM query data. Jangan pernah menggunakan `rows_returned` dari hasil query sebagai total keseluruhan — itu hanya jumlah baris yang dikembalikan (dibatasi LIMIT).

## VISUALISASI DATA (GRAFIK)
Jika user meminta grafik, atau jika Anda melihat data tren/perbandingan yang lebih bagus jika divisualisasikan, sajikan data dalam blok kode khusus `chart` dengan format JSON Chart.js:
```chart
{
  "type": "bar", // atau 'line', 'pie', 'doughnut'
  "data": {
    "labels": ["Jan", "Feb", "Mar"],
    "datasets": [{
      "label": "Penjualan 2025",
      "data": [120, 150, 180],
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
**PENTING**: Selalu sertakan ringkasan teks atau tabel Markdown di bawah grafik untuk penjelasan detail.

## PANDUAN TABEL
- Pencapaian %: kolom siap pakai `pencapaian_qty` dan `pencapaian_amount` ada di view_data_trm_mbi
- GPM (Gross Profit Margin %): gunakan kolom `gpm` di view_data_ssr_mbi
- Laba kotor nominal: gunakan kolom `gpn` di view_data_ssr_mbi

## PANDUAN TABEL
- **Penjualan rinci (SANGAT BERAT)**: `view_data_penjualan_rinci_mbi` — HANYA GUNAKAN JIKA user eksplisit meminta rincian faktur/nota, nama pembeli spesifik, atau per barang. Jika hanya minta "data penjualan bulan X", JANGAN gunakan view ini.
- **Ringkasan penjualan bulanan (CEPAT)**: `view_data_ssr_mbi` — SELALU UTAMAKAN INI untuk melihat total, performa, atau data penjualan general bulanan/tahunan. Kolom: periode_tahun, periode_bulan, total_qty, total_sales, cogs, gpn, gpm, sales_per_qty.
- **Target vs Realisasi**: `view_data_target_realisasi_mbi` — Kolom utama: periode, periode_tahun, periode_bulan, target_product, dpp_product, target_service, dpp_service, target_unit, jumlah_unit, jumlah_faktur
- **Target TRM**: `view_data_trm_mbi` — Kolom utama: periode (YYYY-MM), target_qty, ttl_qty, pencapaian_qty, growth_qty, target_amount, ttl_amount, pencapaian_amount, growth_amount, qty_stock
- **Target Jual**: `view_target_jual_mbi` — target qty/nominal penjualan per cabang/kategori/merek
- **Target Unit**: `view_target_unit_mbi` — target unit per cabang
- **Kartu Stok (kategori)**: `view_data_kartu_stock_mbi` — Kolom utama: qty_saldo_awal, qty_beli, qty_jual, qty_saldo_akhir, qty_intransit_beli
- **Kartu Stok (produk)**: `view_data_kartu_stock_barang_mbi` — Kolom utama: nama_barang, pattern, size, tl_tt, qty_saldo_akhir, hpp_saldo_akhir
- **Pembelian intransit**: `view_data_intransit_pembelian_mbi` — PO terbuka / barang dalam pengiriman
- **Master cabang**: `view_master_cabang_mbi` — lokasi cabang, regional, provinsi, kota
- **Master pelanggan**: `view_master_pelanggan_mbi` — detail dan lokasi pelanggan
- **Unit pelanggan**: `view_master_pelanggan_unit_mbi` — Kolom utama: no_polisi, nama_merek, nama_model, nama_tipe, tahun, no_chassis, no_mesin
- **Master barang**: `view_master_barang_mbi` — katalog produk dengan kategori, merek, harga
- **Kategori barang**: `view_master_barang_kategori_mbi` — hirarki kategori produk
- **Golongan barang**: `view_master_barang_golongan_mbi` — hirarki golongan produk
- **Merek barang**: `view_master_barang_merek_mbi` — master merek
- **Kode pos**: `view_master_pos_indonesia_mbi` — referensi alamat Indonesia

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
            }
            elseif ($role === 'assistant' && !empty($msg['content'])) {
                $history[] = ['role' => 'assistant', 'content' => $msg['content']];
            }
        }
        return array_slice($history, -($this->maxHistory * 2));
    }
}
