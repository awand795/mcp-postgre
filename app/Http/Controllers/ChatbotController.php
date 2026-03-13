<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';

    private array $models = [
        'nvidia/llama-3.1-nemotron-70b-instruct', // Rekomendasi 1: Nemotron
        'qwen/qwen-2.5-72b-instruct',           // Rekomendasi 2: Qwen
        'meta-llama/llama-3.3-70b-instruct',
    ];

    private int $maxHistoryTurns = 10;

    // Cache nama kolom tanggal di tabel transaksi (auto-detect)
    private ?string $colTanggal = null;

    public function index()
    {
        return view('chatbot');
    }

    // ── Deteksi nama kolom tanggal di tabel transaksi ─────────────────────────
    // Beberapa database pakai 'tanggal', 'tanggal_transaksi', 'created_at', dll.
    private function getColTanggal(): string
    {
        if ($this->colTanggal !== null) {
            return $this->colTanggal;
        }

        $this->colTanggal = cache()->remember('col_tanggal_transaksi', 3600, function () {
            try {
                $cols = DB::select("
                    SELECT column_name FROM information_schema.columns
                    WHERE table_name = 'transaksi' AND table_schema = 'public'
                    AND data_type IN ('date','timestamp','timestamp with time zone','timestamp without time zone')
                    ORDER BY ordinal_position
                    LIMIT 1
                ");
                if (!empty($cols)) {
                    Log::info("Auto-detected tanggal column: " . $cols[0]->column_name);
                    return $cols[0]->column_name;
                }
            } catch (\Exception $e) {
                Log::error("getColTanggal error: " . $e->getMessage());
            }
            return 'tanggal'; // default fallback
        });

        return $this->colTanggal;
    }

    // ── Deteksi nama kolom total bayar di tabel transaksi ────────────────────
    private function getColTotalBayar(): string
    {
        return cache()->remember('col_total_bayar', 3600, function () {
            try {
                $cols = DB::select("
                    SELECT column_name FROM information_schema.columns
                    WHERE table_name = 'transaksi' AND table_schema = 'public'
                    AND column_name IN ('total_bayar','total','grand_total','amount','total_pembayaran')
                    ORDER BY ordinal_position LIMIT 1
                ");
                if (!empty($cols)) return $cols[0]->column_name;
            } catch (\Exception $e) {}
            return 'total_bayar';
        });
    }

    public function send(Request $request)
    {
        set_time_limit(300);
        $message = $request->input('message');
        $history = $request->input('history', []);
        $apiKey  = env('OPENROUTER_API_KEY') ?: env('NVIDIA_API_KEY');

        Log::info("Chatbot send: ", ['message' => $message]);

        if (!$apiKey) {
            return response()->json(['response' => "Error: OPENROUTER_API_KEY atau NVIDIA_API_KEY tidak dikonfigurasi di .env"]);
        }

        $needsData = $this->messageNeedsDatabase($message);
        $dbContext = '';
        $docContext = '';

        $needsDocs = $this->messageNeedsDocs($message);

        // Prioritaskan dokumen jika user secara eksplisit menanyakan tutorial/panduan
        if ($needsDocs) {
            $docContext = $this->fetchRelevantDocs($message);
            // Jika dokumen ditemukan, abaikan pencarian database untuk menghindari kebingungan model
            if (!empty($docContext)) {
                $needsData = false;
            }
        }

        if ($needsData) {
            $dbContext = $this->fetchRelevantData($message);
        }

        $schemaContext = $this->getSchemaContext();
        $systemPrompt  = $this->buildSystemPrompt($schemaContext, $dbContext, $docContext);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        $trimmedHistory = array_slice($history, -($this->maxHistoryTurns * 2));
        foreach ($trimmedHistory as $turn) {
            if (isset($turn['role'], $turn['content'])) {
                $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $aiResponse = $this->callAI($messages, $apiKey);

        if ($aiResponse === null) {
            // Jika AI gagal tapi ada data, format langsung dari data
            if ($dbContext) {
                return response()->json([
                    'response' => $this->formatContextAsResponse($dbContext),
                    'history'  => [],
                ]);
            }
            return response()->json([
                'response' => "Maaf, semua model AI sedang tidak tersedia. Coba beberapa saat lagi.",
                'history'  => [],
            ]);
        }

        $messages[] = ['role' => 'assistant', 'content' => $aiResponse];

        return response()->json([
            'response' => $aiResponse,
            'history'  => $this->extractHistoryForClient($messages),
        ]);
    }

    // ── Panggil AI dengan auto-fallback ───────────────────────────────────────
    private function callAI(array $messages, string $apiKey): ?string
    {
        foreach ($this->models as $model) {
            try {
                Log::info("Trying model: {$model}");
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept'        => 'application/json',
                ])
                ->timeout(90)
                ->post($this->apiUrl, [
                    'model'       => $model,
                    'messages'    => $messages,
                    'max_tokens'  => 4096,
                    'temperature' => 0.3,
                    'top_p'       => 0.90,
                ]);

                if ($response->successful()) {
                    $content = $response->json()['choices'][0]['message']['content'] ?? null;
                    if (!empty($content)) {
                        Log::info("Model {$model} succeeded.");
                        return $content;
                    }
                }
                Log::warning("Model {$model} failed: " . $response->status());
            } catch (\Exception $e) {
                Log::warning("Model {$model} exception: " . $e->getMessage());
            }
        }
        return null;
    }

    // ── Query database & kembalikan sebagai konteks ───────────────────────────
    private function fetchRelevantData(string $message): string
    {
        $lower         = mb_strtolower($message);
        $wilayahFilter = $this->extractWilayahFilter($lower);
        $results       = [];

        try {
            $queries = $this->selectQueries($lower, $wilayahFilter);

            foreach ($queries as $label => $sql) {
                try {
                    if (!preg_match('/^\s*select/i', $sql)) continue;
                    if (!preg_match('/\blimit\b/i', $sql)) {
                        $sql = rtrim($sql, ';') . ' LIMIT 50';
                    }

                    $rows = DB::select($sql);
                    $results[$label] = !empty($rows) ? $rows : ['info' => 'Tidak ada data.'];
                    Log::info("Query '{$label}': " . (is_array($rows) ? count($rows) : 0) . " rows");

                } catch (\Exception $e) {
                    Log::error("Query '{$label}' error: " . $e->getMessage());
                    $results[$label] = ['error' => $e->getMessage()];
                }
            }
        } catch (\Exception $e) {
            Log::error("fetchRelevantData: " . $e->getMessage());
        }

        if (empty($results)) return '';

        $ctx  = "=== DATA NYATA DARI DATABASE ===\n";
        if ($wilayahFilter) $ctx .= "Filter wilayah: '{$wilayahFilter}'\n";
        $ctx .= "Gunakan HANYA data di bawah. Jangan mengarang.\n\n";

        foreach ($results as $label => $rows) {
            $ctx .= "--- {$label} ---\n";
            if (isset($rows['error'])) { $ctx .= "ERROR: {$rows['error']}\n\n"; continue; }
            if (isset($rows['info']))  { $ctx .= "{$rows['info']}\n\n"; continue; }

            $first   = (array) $rows[0];
            $headers = array_keys($first);
            $ctx .= "| " . implode(" | ", $headers) . " |\n";
            $ctx .= "| " . implode(" | ", array_fill(0, count($headers), "---")) . " |\n";
            foreach (array_slice($rows, 0, 30) as $row) {
                $vals = array_map(fn($v) => $v ?? '-', array_values((array)$row));
                $ctx .= "| " . implode(" | ", $vals) . " |\n";
            }
            $ctx .= "\nTotal: " . count($rows) . " baris\n\n";
        }

        return $ctx;
    }

    // ── Bangun semua query berdasarkan kata kunci ─────────────────────────────
    private function selectQueries(string $lower, string $wilayahFilter = ''): array
    {
        $queries = [];
        $tgl     = $this->getColTanggal();      // nama kolom tanggal yang benar
        $bayar   = $this->getColTotalBayar();   // nama kolom total bayar yang benar
        $hasW    = !empty($wilayahFilter);
        $safe    = $hasW ? addslashes($wilayahFilter) : '';

        // WHERE clause untuk filter wilayah (digunakan bersama WHERE lain)
        $wAnd = $hasW ? "AND (LOWER(pb.provinsi) LIKE '%{$safe}%' OR LOWER(pb.kota) LIKE '%{$safe}%')" : '';
        // WHERE clause untuk filter wilayah (standalone, tanpa kondisi lain)
        $wWhere = $hasW ? "WHERE (LOWER(pb.provinsi) LIKE '%{$safe}%' OR LOWER(pb.kota) LIKE '%{$safe}%')" : '';

        // ── Produk terlaris ──────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['produk', 'terlaris', 'best seller', 'paling laku', 'banyak terjual', 'laris'])) {
            $join  = $hasW ? "JOIN pembeli pb ON tr.id_pembeli = pb.id_pembeli" : "";
            $where = $hasW ? "WHERE 1=1 {$wAnd}" : "";
            $label = $hasW ? "Produk Terlaris di " . ucwords($wilayahFilter) : "Produk Terlaris";
            $queries[$label] = "
                SELECT p.nama_produk, k.nama_kategori,
                    SUM(dt.qty) as total_terjual,
                    SUM(dt.qty * dt.harga_satuan) as total_pendapatan,
                    ROUND(SUM(dt.qty * dt.harga_satuan) * 100.0 / SUM(SUM(dt.qty * dt.harga_satuan)) OVER (), 2) as persen_revenue
                FROM produk p
                JOIN kategori k ON p.id_kategori = k.id_kategori
                JOIN detail_transaksi dt ON p.id_produk = dt.id_produk
                JOIN transaksi tr ON dt.id_transaksi = tr.id_transaksi
                {$join}
                {$where}
                GROUP BY p.nama_produk, k.nama_kategori
                ORDER BY total_terjual DESC LIMIT 10";
        }

        // ── Pelanggan terbaik / terloyal ─────────────────────────────────────
        if ($this->hasKeyword($lower, ['pelanggan', 'pembeli', 'customer', 'loyal', 'setia', 'terbaik', 'terloyal'])) {
            $label = $hasW ? "Pelanggan Terbaik di " . ucwords($wilayahFilter) : "Pelanggan Terbaik";
            $queries[$label] = "
                SELECT pb.nama_pembeli, pb.kota, pb.provinsi,
                    COUNT(DISTINCT tr.id_transaksi) as total_transaksi,
                    SUM(tr.{$bayar}) as total_belanja,
                    ROUND(AVG(tr.{$bayar}), 0) as rata_rata_belanja,
                    MAX(tr.{$tgl}) as transaksi_terakhir
                FROM pembeli pb
                JOIN transaksi tr ON pb.id_pembeli = tr.id_pembeli
                {$wWhere}
                GROUP BY pb.nama_pembeli, pb.kota, pb.provinsi
                ORDER BY total_belanja DESC LIMIT 10";
        }

        // ── Revenue per wilayah ──────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['wilayah', 'provinsi', 'kota', 'daerah', 'region', 'area'])) {
            $queries['Revenue per Wilayah'] = "
                SELECT pb.provinsi,
                    COUNT(DISTINCT pb.id_pembeli) as jumlah_pelanggan,
                    COUNT(DISTINCT tr.id_transaksi) as jumlah_transaksi,
                    SUM(tr.{$bayar}) as total_revenue,
                    ROUND(AVG(tr.{$bayar}), 0) as aov
                FROM pembeli pb
                JOIN transaksi tr ON pb.id_pembeli = tr.id_pembeli
                GROUP BY pb.provinsi
                ORDER BY total_revenue DESC";
        }

        // ── Revenue trend / bulanan ──────────────────────────────────────────
        if ($this->hasKeyword($lower, ['tren', 'trend', 'revenue', 'pendapatan', 'omzet', 'per bulan', 'bulanan', 'penjualan bulan'])) {
            if ($hasW) {
                $label = "Revenue Bulanan di " . ucwords($wilayahFilter);
                $queries[$label] = "
                    SELECT TO_CHAR(tr.{$tgl}, 'YYYY-MM') as bulan,
                        COUNT(DISTINCT tr.id_transaksi) as jumlah_transaksi,
                        SUM(tr.{$bayar}) as total_revenue,
                        ROUND(AVG(tr.{$bayar}), 0) as avg_order_value
                    FROM transaksi tr
                    JOIN pembeli pb ON tr.id_pembeli = pb.id_pembeli
                    WHERE 1=1 {$wAnd}
                    GROUP BY TO_CHAR(tr.{$tgl}, 'YYYY-MM')
                    ORDER BY bulan DESC LIMIT 12";
            } else {
                $queries['Revenue per Bulan'] = "
                    SELECT TO_CHAR({$tgl}, 'YYYY-MM') as bulan,
                        COUNT(DISTINCT id_transaksi) as jumlah_transaksi,
                        SUM({$bayar}) as total_revenue,
                        ROUND(AVG({$bayar}), 0) as avg_order_value,
                        COUNT(DISTINCT id_pembeli) as unique_pelanggan
                    FROM transaksi
                    GROUP BY TO_CHAR({$tgl}, 'YYYY-MM')
                    ORDER BY bulan DESC LIMIT 12";
            }
        }

        // ── Kategori ─────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['kategori', 'category', 'jenis produk'])) {
            $join  = $hasW ? "JOIN pembeli pb ON tr.id_pembeli = pb.id_pembeli" : "";
            $where = $hasW ? "WHERE 1=1 {$wAnd}" : "";
            $label = $hasW ? "Kategori Terlaris di " . ucwords($wilayahFilter) : "Penjualan per Kategori";
            $queries[$label] = "
                SELECT k.nama_kategori,
                    COUNT(DISTINCT p.id_produk) as jumlah_produk,
                    SUM(dt.qty) as total_terjual,
                    SUM(dt.qty * dt.harga_satuan) as total_pendapatan
                FROM kategori k
                JOIN produk p ON k.id_kategori = p.id_kategori
                JOIN detail_transaksi dt ON p.id_produk = dt.id_produk
                JOIN transaksi tr ON dt.id_transaksi = tr.id_transaksi
                {$join}
                {$where}
                GROUP BY k.nama_kategori
                ORDER BY total_pendapatan DESC";
        }

        // ── RFM ──────────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['rfm', 'recency', 'frequency', 'monetary', 'segmen pelanggan', 'segmentasi'])) {
            $label = $hasW ? "RFM di " . ucwords($wilayahFilter) : "Analisis RFM";
            $queries[$label] = "
                SELECT pb.nama_pembeli,
                    MAX(tr.{$tgl}) as last_purchase,
                    CURRENT_DATE - MAX(tr.{$tgl}) as recency_days,
                    COUNT(DISTINCT tr.id_transaksi) as frequency,
                    SUM(tr.{$bayar}) as monetary,
                    CASE
                        WHEN CURRENT_DATE - MAX(tr.{$tgl}) <= 30 AND COUNT(DISTINCT tr.id_transaksi) >= 3 THEN 'Champions'
                        WHEN CURRENT_DATE - MAX(tr.{$tgl}) <= 60 AND COUNT(DISTINCT tr.id_transaksi) >= 2 THEN 'Loyal'
                        WHEN CURRENT_DATE - MAX(tr.{$tgl}) <= 90 THEN 'At Risk'
                        ELSE 'Lost'
                    END as rfm_segment
                FROM pembeli pb
                JOIN transaksi tr ON pb.id_pembeli = tr.id_pembeli
                {$wWhere}
                GROUP BY pb.nama_pembeli
                ORDER BY monetary DESC LIMIT 20";
        }

        // ── Metode pembayaran ─────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['metode bayar', 'pembayaran', 'payment', 'cara bayar', 'transfer', 'tunai', 'kredit'])) {
            $join  = $hasW ? "JOIN pembeli pb ON tr.id_pembeli = pb.id_pembeli" : "";
            $where = $hasW ? "WHERE 1=1 {$wAnd}" : "";
            $label = $hasW ? "Metode Pembayaran di " . ucwords($wilayahFilter) : "Metode Pembayaran";
            $queries[$label] = "
                SELECT tr.metode_bayar,
                    COUNT(*) as jumlah_transaksi,
                    SUM(tr.{$bayar}) as total_revenue,
                    ROUND(AVG(tr.{$bayar}), 0) as avg_transaksi,
                    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 2) as persen_penggunaan
                FROM transaksi tr
                {$join}
                {$where}
                GROUP BY tr.metode_bayar
                ORDER BY jumlah_transaksi DESC";
        }

        // ── Diskon ───────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['diskon', 'discount', 'promo', 'potongan'])) {
            // Cek apakah kolom diskon ada
            $queries['Efektivitas Diskon'] = "
                SELECT CASE WHEN diskon > 0 THEN 'Ada Diskon' ELSE 'Tanpa Diskon' END as status_diskon,
                    COUNT(*) as jumlah_transaksi,
                    ROUND(AVG(total_harga), 0) as rata_nilai,
                    SUM({$bayar}) as total_revenue,
                    ROUND(AVG(diskon), 2) as rata_diskon_persen
                FROM transaksi
                GROUP BY CASE WHEN diskon > 0 THEN 'Ada Diskon' ELSE 'Tanpa Diskon' END";
        }

        // ── Dead stock ────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['dead stock', 'tidak laku', 'stok mati', 'tidak terjual', 'slow moving'])) {
            $queries['Dead Stock'] = "
                SELECT p.nama_produk, k.nama_kategori, p.harga,
                    COALESCE(SUM(dt.qty), 0) as total_terjual,
                    MAX(tr.{$tgl}) as terakhir_terjual
                FROM produk p
                JOIN kategori k ON p.id_kategori = k.id_kategori
                LEFT JOIN detail_transaksi dt ON p.id_produk = dt.id_produk
                LEFT JOIN transaksi tr ON dt.id_transaksi = tr.id_transaksi
                GROUP BY p.nama_produk, k.nama_kategori, p.harga
                HAVING COALESCE(SUM(dt.qty), 0) = 0
                    OR MAX(tr.{$tgl}) < CURRENT_DATE - INTERVAL '90 days'
                ORDER BY terakhir_terjual ASC NULLS FIRST";
        }

        // ── Cross-sell ────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['cross sell', 'cross-sell', 'kombinasi', 'sering dibeli bersama', 'bundle'])) {
            $queries['Cross-Sell'] = "
                SELECT p1.nama_produk as produk_a, p2.nama_produk as produk_b,
                    COUNT(*) as frekuensi_bersamaan
                FROM detail_transaksi dt1
                JOIN detail_transaksi dt2 ON dt1.id_transaksi = dt2.id_transaksi AND dt1.id_produk < dt2.id_produk
                JOIN produk p1 ON dt1.id_produk = p1.id_produk
                JOIN produk p2 ON dt2.id_produk = p2.id_produk
                GROUP BY p1.nama_produk, p2.nama_produk
                ORDER BY frekuensi_bersamaan DESC LIMIT 10";
        }

        // ── ABC Analysis ──────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['abc', 'pareto', '80/20'])) {
            $queries['ABC Analysis'] = "
                SELECT nama_produk, total_pendapatan,
                    ROUND(total_pendapatan * 100.0 / SUM(total_pendapatan) OVER (), 2) as persen,
                    ROUND(SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / SUM(total_pendapatan) OVER (), 2) as kumulatif,
                    CASE
                        WHEN SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / SUM(total_pendapatan) OVER () <= 80 THEN 'A - Prioritas'
                        WHEN SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / SUM(total_pendapatan) OVER () <= 95 THEN 'B - Menengah'
                        ELSE 'C - Rendah'
                    END as kategori_abc
                FROM (
                    SELECT p.nama_produk, SUM(dt.qty * dt.harga_satuan) as total_pendapatan
                    FROM produk p JOIN detail_transaksi dt ON p.id_produk = dt.id_produk
                    GROUP BY p.nama_produk
                ) sub ORDER BY total_pendapatan DESC LIMIT 20";
        }

        // ── Customer Retention ────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['retention', 'pelanggan baru', 'pelanggan kembali', 'repeat order', 'repeat buyer'])) {
            $queries['Customer Retention'] = "
                SELECT TO_CHAR(tr.{$tgl}, 'YYYY-MM') as bulan,
                    COUNT(DISTINCT CASE WHEN fb.bulan_pertama = TO_CHAR(tr.{$tgl}, 'YYYY-MM') THEN tr.id_pembeli END) as pelanggan_baru,
                    COUNT(DISTINCT CASE WHEN fb.bulan_pertama != TO_CHAR(tr.{$tgl}, 'YYYY-MM') THEN tr.id_pembeli END) as pelanggan_kembali
                FROM transaksi tr
                JOIN (
                    SELECT id_pembeli, TO_CHAR(MIN({$tgl}), 'YYYY-MM') as bulan_pertama
                    FROM transaksi GROUP BY id_pembeli
                ) fb ON tr.id_pembeli = fb.id_pembeli
                GROUP BY TO_CHAR(tr.{$tgl}, 'YYYY-MM')
                ORDER BY bulan DESC LIMIT 12";
        }

        // ── Fallback: ringkasan umum ──────────────────────────────────────────
        if (empty($queries)) {
            if ($hasW) {
                $queries["Ringkasan di " . ucwords($wilayahFilter)] = "
                    SELECT COUNT(DISTINCT tr.id_transaksi) as total_transaksi,
                        COALESCE(SUM(tr.{$bayar}), 0) as total_revenue,
                        COUNT(DISTINCT pb.id_pembeli) as total_pelanggan,
                        ROUND(AVG(tr.{$bayar}), 0) as avg_order_value
                    FROM transaksi tr
                    JOIN pembeli pb ON tr.id_pembeli = pb.id_pembeli
                    WHERE 1=1 {$wAnd}";
            } else {
                $queries['Ringkasan Bisnis'] = "
                    SELECT
                        (SELECT COUNT(*) FROM transaksi) as total_transaksi,
                        (SELECT COALESCE(SUM({$bayar}), 0) FROM transaksi) as total_revenue,
                        (SELECT COUNT(*) FROM pembeli) as total_pelanggan,
                        (SELECT COUNT(*) FROM produk) as total_produk,
                        (SELECT ROUND(AVG({$bayar}), 0) FROM transaksi) as avg_order_value";
            }
        }

        return $queries;
    }

    // ── Format data sebagai respons langsung (fallback jika AI gagal) ─────────
    private function formatContextAsResponse(string $ctx): string
    {
        return "### 📊 Hasil Data\n\n" .
               preg_replace('/^=== DATA NYATA.*?\n.*?\n\n/s', '', $ctx) .
               "\n\n> ℹ️ Model AI sedang tidak tersedia. Data di atas langsung dari database.";
    }

    // ── Ekstrak filter wilayah dari pesan ─────────────────────────────────────
    private function extractWilayahFilter(string $lower): string
    {
        $provinces = [
            'aceh','sumatera utara','sumut','sumatera barat','sumbar',
            'riau','kepulauan riau','kepri','jambi','sumatera selatan','sumsel',
            'bangka belitung','babel','bengkulu','lampung',
            'dki jakarta','jakarta','jawa barat','jabar','jawa tengah','jateng',
            'diy','yogyakarta','jogja','jawa timur','jatim',
            'banten','bali','nusa tenggara barat','ntb','nusa tenggara timur','ntt',
            'kalimantan barat','kalbar','kalimantan tengah','kalteng',
            'kalimantan selatan','kalsel','kalimantan timur','kaltim','kalimantan utara','kalut',
            'sulawesi utara','sulut','sulawesi tengah','sulteng',
            'sulawesi selatan','sulsel','sulawesi tenggara','sultra',
            'gorontalo','sulawesi barat','sulbar',
            'maluku','maluku utara','papua','papua barat',
        ];

        foreach ($provinces as $prov) {
            if (str_contains($lower, $prov)) return $prov;
        }

        if (preg_match('/(?:di|dari|untuk|wilayah|daerah|kota|area)\s+([a-z\s]+?)(?:\s|$|,|\?)/u', $lower, $m)) {
            $c = trim($m[1]);
            $stop = ['sini','sana','mana','atas','bawah','dalam','luar','mana','semua'];
            if (strlen($c) >= 3 && !in_array($c, $stop)) return $c;
        }

        return '';
    }

    // ── Deteksi apakah butuh database ────────────────────────────────────────
    private function messageNeedsDatabase(string $message): bool
    {
        $keywords = [
            'produk','terlaris','revenue','transaksi','penjualan','pelanggan',
            'pembeli','kategori','stok','laporan','analisis','analisa','data',
            'tren','trend','statistik','ranking','rank','terbaik','tertinggi',
            'terendah','total','jumlah','bulan','tahun','wilayah','provinsi',
            'kota','diskon','profit','pendapatan','omzet','rfm','abc','retention',
            'cross-sell','cross sell','dead stock','metode bayar','metode pembayaran',
            'aov','lihat','tampilkan','tunjukkan','cari','berapa','siapa','mana',
            'show','display','top','paling','laku','laris','beli','jual','loyal','terloyal',
            'jawa barat','jawa tengah','jawa timur','jakarta','banten','bali',
            'sumatera','kalimantan','sulawesi','papua','aceh','riau','lampung',
            'jogja','yogyakarta','jabar','jateng','jatim','sumut','sumbar',
        ];
        $lower = mb_strtolower($message);
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) return true;
        }
        return false;
    }

    // ── Deteksi apakah butuh dokumentasi ERP ──────────────────────────────────
    private function messageNeedsDocs(string $message): bool
    {
        $keywords = [
            'cara', 'bagaimana', 'tutorial', 'panduan', 'tahap', 'langkah',
            'dokumentasi', 'docs', 'erp', 'finance', 'inventory', 'warehouse',
            'purchasing', 'sales', 'pembayaran', 'dp', 'pembelian', 'stok',
            'laporan', 'report', 'setting', 'konfigurasi', 'modul', 'fitur',
        ];
        $lower = mb_strtolower($message);
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) return true;
        }
        return false;
    }

    // ── Cari dokumentasi relevan ──────────────────────────────────────────────
    private function fetchRelevantDocs(string $message): string
    {
        $words = explode(' ', mb_strtolower($message));
        $words = array_filter($words, fn($w) => strlen($w) > 3 || in_array($w, ['dp', 'po', 'pr']));
        
        $stopWords = ['coba', 'perbaiki', 'bagaimana', 'cara', 'tolong', 'berikan', 'langkah', 'lengkap', 'apa', 'saja', 'lihat', 'tampilkan'];
        $words = array_filter($words, fn($w) => !in_array($w, $stopWords));

        if (empty($words)) return '';

        // Fetch all docs that match AT LEAST ONE word
        $query = DB::table('documentation');
        foreach ($words as $word) {
            $safe = addslashes($word);
            $query->orWhere('title', 'ILIKE', "%{$safe}%")
                  ->orWhere('content', 'ILIKE', "%{$safe}%");
        }

        $allDocs = $query->get();
        if ($allDocs->isEmpty()) return '';

        // Score the documents
        $scoredDocs = [];
        foreach ($allDocs as $doc) {
            $score = 0;
            $titleLower = mb_strtolower($doc->title);
            $contentLower = mb_strtolower($doc->content);

            foreach ($words as $word) {
                // Beri bobot lebih tinggi untuk kata kunci spesifik/jarang muncul
                $isRareWord = in_array($word, ['klaim', 'suplier', 'supplier', 'retur', 'rusak', 'dp', 'po', 'pr']);
                $titleWeight = $isRareWord ? 50 : 10;
                $contentWeight = $isRareWord ? 10 : 1;

                if (str_contains($titleLower, $word)) {
                    $score += $titleWeight;
                }
                if (str_contains($contentLower, $word)) {
                    $score += $contentWeight;
                }
            }
            $scoredDocs[] = ['doc' => $doc, 'score' => $score];
        }

        // Sort by score descending
        usort($scoredDocs, fn($a, $b) => $b['score'] <=> $a['score']);

        // Take top 3
        $topDocs = array_slice($scoredDocs, 0, 3);

        $ctx = "=== DOKUMENTASI ERP (PANDUAN PENGGUNA) ===\n";
        $ctx .= "Gunakan panduan ini untuk menjawab pertanyaan teknis tentang ERP. Selalu sertakan rincian field formulir jika tersedia.\n\n";

        foreach ($topDocs as $item) {
            $doc = $item['doc'];
            $ctx .= "--- Judul: {$doc->title} ---\n";
            $ctx .= "Link: {$doc->url}\n";
            $ctx .= "Konten: " . mb_substr($doc->content, 0, 8000) . "...\n\n";
        }

        return $ctx;
    }

    // ── System prompt ─────────────────────────────────────────────────────────
    private function buildSystemPrompt(string $schemaContext, string $dbContext, string $docContext = ''): string
    {
        $dataSection = $dbContext
            ? "\n\n{$dbContext}\nGUNAKAN DATA DI ATAS. Data ini NYATA dari database.\n"
            : '';

        $docSection = $docContext
            ? "\n\n{$docContext}\nGUNAKAN PANDUAN DI ATAS untuk memberikan instruksi kepada pengguna.\n"
            : '';

        return "Kamu adalah asisten AI yang ramah, cerdas, dan ahli sebagai Senior Data Analyst sekaligus Konsultan ERP.
Nama panggilanmu adalah DataBot.

## KEPRIBADIAN
- Bisa diajak ngobrol santai dan merespons salam dengan hangat.
- Untuk pertanyaan umum, jawab natural.
- Untuk pertanyaan data, jadilah analis data profesional.
- Untuk pertanyaan teknis/operasional ERP, berikan langkah-langkah yang jelas berdasarkan dokumentasi. 
- Jika dokumentasi memiliki bagian 'Daftar Field Formulir', sertakan rincian field yang harus diisi secara lengkap agar pengguna terbantu.

## BAHASA
- Bahasa Indonesia → jawab Bahasa Indonesia.
- English → answer in English.

## SKEMA DATABASE
{$schemaContext}
{$dataSection}
{$docSection}
## FORMAT JAWABAN DATA
### 📊 Hasil Data
| Kolom1 | Kolom2 |
|--------|--------|
| nilai  | nilai  |

### 🔍 Analisis Mendalam
- **Temuan Utama**: insight dari data
- **Pola & Tren**: pola yang terlihat
- **Potensi Masalah**: risiko atau anomali

### 💡 Rekomendasi Bisnis
1. **[Aksi]**: penjelasan konkret
2. **[Aksi]**: penjelasan konkret

## FORMAT JAWABAN PANDUAN/DOCS
- Berikan langkah demi langkah (1, 2, 3...) jika itu sebuah prosedur.
- Berikan link sumber dokumentasi jika tersedia.

Gunakan data dan panduan nyata di atas. Jangan mengarang.
Untuk percakapan santai, jawab natural tanpa format laporan.";
    }

    // ── Ekstrak history untuk frontend ───────────────────────────────────────
    private function extractHistoryForClient(array $messages): array
    {
        $history = [];
        foreach ($messages as $msg) {
            if (in_array($msg['role'] ?? '', ['user', 'assistant']) && !empty($msg['content'])) {
                $history[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
        return array_slice($history, -($this->maxHistoryTurns * 2));
    }

    // ── Schema context ────────────────────────────────────────────────────────
    private function getSchemaContext(): string
    {
        try {
            return cache()->remember('db_schema_context_v12', 300, function () {
                $tables  = DB::select("
                    SELECT table_name FROM information_schema.tables
                    WHERE table_schema = 'public'
                    AND table_name NOT IN ('migrations','cache','cache_locks','sessions','jobs','failed_jobs','personal_access_tokens')
                    ORDER BY table_name
                ");
                $context = "TABEL:\n";
                foreach ($tables as $table) {
                    $tn   = $table->table_name;
                    $cols = DB::select("
                        SELECT column_name, data_type FROM information_schema.columns
                        WHERE table_name = ? AND table_schema = 'public'
                        ORDER BY ordinal_position
                    ", [$tn]);
                    $colStr = implode(", ", array_map(fn($c) => "{$c->column_name} ({$c->data_type})", $cols));
                    $context .= "- {$tn}: {$colStr}\n";
                }
                try {
                    $sp = DB::select("SELECT DISTINCT provinsi FROM pembeli WHERE provinsi IS NOT NULL LIMIT 10");
                    if ($sp) {
                        $context .= "\nContoh nilai provinsi: " . implode(', ', array_column($sp, 'provinsi')) . "\n";
                    }
                } catch (\Exception $e) {}
                return $context ?: "No tables found.";
            });
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    // ── Helper keyword check ──────────────────────────────────────────────────
    private function hasKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) return true;
        }
        return false;
    }

    public function rerank(Request $request)
    {
        $query    = $request->input('query');
        $passages = $request->input('passages');
        $apiKey   = env('NVIDIA_API_KEY');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post('https://ai.api.nvidia.com/v1/retrieval/nvidia/llama-nemotron-rerank-1b-v2/reranking', [
            'model'    => 'nvidia/llama-nemotron-rerank-1b-v2',
            'query'    => ['text' => $query],
            'passages' => array_map(fn($p) => ['text' => $p], $passages),
        ]);

        return $response->json();
    }
}
