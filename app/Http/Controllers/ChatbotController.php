<?php

namespace App\Http\Controllers;

use App\Helpers\LanguageDetector;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    // Language detector instance
    private LanguageDetector $languageDetector;

    /**
     * Constructor - initialize language detector
     */
    public function __construct()
    {
        $this->languageDetector = new LanguageDetector();
    }

    /**
     * Mendapatkan daftar tabel yang boleh diakses berdasarkan role user
     */
    private function getAllowedTables(): array
    {
        if (!Auth::check()) {
            // Fallback: allow common tables for unauthenticated users (for testing)
            Log::warning('No authenticated user, using default allowed tables');
            return ['produk', 'kategori', 'transaksi', 'detail_transaksi', 'pembeli', 'karyawan'];
        }

        $roleId = Auth::user()->role;

        return cache()->remember("allowed_tables_role_{$roleId}", 600, function () use ($roleId) {
            $tables = RolePermission::where('role_id', $roleId)->pluck('table_name')->toArray();
            Log::info("Allowed tables for role {$roleId}: " . implode(', ', $tables));
            return $tables;
        });
    }

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
                $cols = DB::connection('pgsql_mbi')->select("
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
        return 'total_harga';
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

        // Detect language from user message
        $detectedLanguage = $this->languageDetector->detect($message);
        $languageInfo = $this->languageDetector->detectWithInfo($message);
        Log::info("Detected language: ", $languageInfo);

        $needsData = $this->messageNeedsDatabase($message);
        $dbContext = '';
        $docContext = '';

        Log::info("Needs database: " . ($needsData ? 'YES' : 'NO'));

        $needsDocs = $this->messageNeedsDocs($message);

        if ($needsDocs) {
            $docContext = $this->fetchRelevantDocs($message);
            Log::info("Needs docs: YES, length: " . strlen($docContext));
            if (!empty($docContext)) {
                $needsData = false;
            }
        }

        if ($needsData) {
            $dbContext = $this->fetchRelevantData($message);
            Log::info("Needs docs: NO, fetching DB data, length: " . strlen($dbContext));
        }

        $schemaContext = $this->getSchemaContext();
        $systemPrompt  = $this->buildSystemPrompt($schemaContext, $dbContext, $docContext, $detectedLanguage);

        Log::info("System prompt length: " . strlen($systemPrompt));
        Log::info("DB Context empty: " . (empty($dbContext) ? 'YES' : 'NO'));

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        $trimmedHistory = array_slice($history, -($this->maxHistoryTurns * 2));
        foreach ($trimmedHistory as $turn) {
            if (isset($turn['role'], $turn['content'])) {
                $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        // Ensure session is written and closed before streaming to avoid blocking other requests
        session_write_close();

        return response()->stream(function () use ($messages, $apiKey, $dbContext) {
            $this->streamAIResponse($messages, $apiKey, $dbContext);
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    // ── Panggil AI dengan auto-fallback (Streaming SSE) ───────────────────────
    private function streamAIResponse(array $messages, string $apiKey, string $dbContext): void
    {
        $success = false;
        $fullContent = '';

        foreach ($this->models as $model) {
            try {
                Log::info("Trying model (stream): {$model}");
                $ch = curl_init($this->apiUrl);
                
                $payload = json_encode([
                    'model'       => $model,
                    'messages'    => $messages,
                    'max_tokens'  => 4096,
                    'temperature' => 0.3,
                    'top_p'       => 0.90,
                    'stream'      => true,
                ]);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]);

                $httpCode = 0;
                curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$httpCode) {
                    if (preg_match('/^HTTP\/1\.[01] (\d+)/', $header, $matches)) {
                        $httpCode = (int)$matches[1];
                    }
                    return strlen($header);
                });

                $streamBuffer = '';
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$fullContent, &$streamBuffer) {
                    $streamBuffer .= $data;
                    $lines = explode("\n", $streamBuffer);
                    $streamBuffer = array_pop($lines); // Simpan potongan line terakhir yang belum selesai ke buffer

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (str_starts_with($line, 'data: ')) {
                            $jsonStr = trim(substr($line, 6));
                            if ($jsonStr === '[DONE]') continue;
                            
                            $json = json_decode($jsonStr, true);
                            if (isset($json['choices'][0]['delta']['content'])) {
                                $content = $json['choices'][0]['delta']['content'];
                                $fullContent .= $content;
                                echo "data: " . json_encode(['chunk' => $content]) . "\n\n";
                                ob_flush();
                                flush();
                            }
                        }
                    }
                    return strlen($data);
                });

                curl_exec($ch);
                $err = curl_error($ch);
                curl_close($ch);

                if ((!$err && $httpCode >= 200 && $httpCode < 300) || !empty($fullContent)) {
                    $success = true;
                    Log::info("Model {$model} stream succeeded (or partially succeeded).");
                    break;
                }
                
                Log::warning("Model {$model} stream failed: HTTP {$httpCode}. Error: {$err}");
            } catch (\Exception $e) {
                Log::warning("Model {$model} stream exception: " . $e->getMessage());
            }
        }

        if (!$success) {
            if ($dbContext) {
                $fallback = $this->formatContextAsResponse($dbContext);
                echo "data: " . json_encode(['fallback' => true, 'response' => $fallback]) . "\n\n";
            } else {
                echo "data: " . json_encode(['error' => true, 'response' => "Maaf, semua model AI sedang tidak tersedia. Coba beberapa saat lagi."]) . "\n\n";
            }
            ob_flush(); flush();
        } else {
            $messages[] = ['role' => 'assistant', 'content' => $fullContent];
            $history = $this->extractHistoryForClient($messages);
            echo "data: " . json_encode(['history' => $history]) . "\n\n";
            ob_flush(); flush();
        }

        echo "data: [DONE]\n\n";
        ob_flush(); flush();
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

                    $rows = DB::connection('pgsql_mbi')->select($sql);
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
                $vals = array_map(function($v, $key) {
                    if ($v === null || $v === '-') return '-';
                    // Format monetary values (columns containing: total, harga, bayar, revenue, profit, amount, dll)
                    if ($this->isMonetaryColumn($key) && is_numeric($v)) {
                        return $this->formatRupiah($v);
                    }
                    return $v;
                }, array_values((array)$row), array_keys((array)$row));
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
        $tgl     = 'tgl_fak_jl';
        $bayar   = 'total_harga';
        $hasW    = !empty($wilayahFilter);
        $safe    = $hasW ? addslashes($wilayahFilter) : '';

        $allowedTables = $this->getAllowedTables();
        $isAllowed = function($table) use ($allowedTables) {
            return in_array($table, $allowedTables);
        };

        $vSales = 'sch_mbi.view_data_penjualan_rinci_mbi';
        $allowSales = $isAllowed('view_data_penjualan_rinci_mbi');

        $wAnd = $hasW ? "AND (LOWER(nama_propinsi_cabang) LIKE '%{$safe}%' OR LOWER(nama_kabupaten_cabang) LIKE '%{$safe}%' OR LOWER(alamat_pelanggan) LIKE '%{$safe}%')" : '';
        $wWhere = $hasW ? "WHERE (LOWER(nama_propinsi_cabang) LIKE '%{$safe}%' OR LOWER(nama_kabupaten_cabang) LIKE '%{$safe}%' OR LOWER(alamat_pelanggan) LIKE '%{$safe}%')" : '';

        // ── Produk terlaris ──────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['produk', 'terlaris', 'best seller', 'bestseller', 'paling laku', 'banyak terjual', 'laris', 'product', 'top selling', 'most sold'])
            && $allowSales) {
            $label = $hasW ? "Produk Terlaris di " . ucwords($wilayahFilter) : "Produk Terlaris";
            $queries[$label] = "
                SELECT nama_barang, nama_kategori_barang,
                    SUM(qty_jual) as total_terjual,
                    SUM(total_harga) as total_pendapatan,
                    ROUND(SUM(total_harga) * 100.0 / NULLIF(SUM(SUM(total_harga)) OVER (), 0), 2) as persen_revenue
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY nama_barang, nama_kategori_barang
                ORDER BY total_terjual DESC LIMIT 10";
        }

        // ── Pelanggan terbaik / terloyal ─────────────────────────────────────
        if ($this->hasKeyword($lower, ['pelanggan', 'pembeli', 'customer', 'loyal', 'setia', 'terbaik', 'terloyal', 'buyer', 'client', 'best customer'])
            && $allowSales) {
            $label = $hasW ? "Pelanggan Terbaik di " . ucwords($wilayahFilter) : "Pelanggan Terbaik";
            $queries[$label] = "
                SELECT nama_pelanggan, nama_kabupaten_cabang as kota, nama_propinsi_cabang as provinsi,
                    COUNT(DISTINCT no_fak_jl) as total_transaksi,
                    SUM(total_harga) as total_belanja,
                    ROUND(AVG(total_harga), 0) as rata_rata_belanja,
                    MAX({$tgl}) as transaksi_terakhir
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY nama_pelanggan, nama_kabupaten_cabang, nama_propinsi_cabang
                ORDER BY total_belanja DESC LIMIT 10";
        }

        // ── Revenue per wilayah ──────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['wilayah', 'provinsi', 'kota', 'daerah', 'region', 'area', 'province', 'city'])
            && $allowSales) {
            $queries['Revenue per Wilayah'] = "
                SELECT nama_propinsi_cabang as provinsi,
                    COUNT(DISTINCT kode_pelanggan) as jumlah_pelanggan,
                    COUNT(DISTINCT no_fak_jl) as jumlah_transaksi,
                    SUM(total_harga) as total_revenue,
                    ROUND(AVG(total_harga), 0) as aov
                FROM {$vSales}
                GROUP BY nama_propinsi_cabang
                ORDER BY total_revenue DESC";
        }

        // ── Revenue trend / bulanan ──────────────────────────────────────────
        if ($this->hasKeyword($lower, ['tren', 'trend', 'revenue', 'pendapatan', 'omzet', 'per bulan', 'bulanan', 'penjualan bulan', 'monthly', 'sales trend', 'income'])
            && $allowSales) {
            $label = $hasW ? "Revenue Bulanan di " . ucwords($wilayahFilter) : "Revenue per Bulan";
            $queries[$label] = "
                SELECT periode_tahun || '-' || periode_bulan as bulan,
                    COUNT(DISTINCT no_fak_jl) as jumlah_transaksi,
                    SUM(total_harga) as total_revenue,
                    ROUND(AVG(total_harga), 0) as avg_order_value,
                    COUNT(DISTINCT kode_pelanggan) as unique_pelanggan
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY periode_tahun, periode_bulan
                ORDER BY periode_tahun DESC, periode_bulan DESC LIMIT 12";
        }

        // ── Kategori ─────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['kategori', 'category', 'jenis produk'])
            && $allowSales) {
            $label = $hasW ? "Kategori Terlaris di " . ucwords($wilayahFilter) : "Penjualan per Kategori";
            $queries[$label] = "
                SELECT nama_kategori_barang as nama_kategori,
                    COUNT(DISTINCT kode_barang) as jumlah_produk,
                    SUM(qty_jual) as total_terjual,
                    SUM(total_harga) as total_pendapatan
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY nama_kategori_barang
                ORDER BY total_pendapatan DESC";
        }

        // ── RFM ──────────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['rfm', 'recency', 'frequency', 'monetary', 'segmen pelanggan', 'segmentasi'])
            && $allowSales) {
            $label = $hasW ? "RFM di " . ucwords($wilayahFilter) : "Analisis RFM";
            $queries[$label] = "
                SELECT nama_pelanggan,
                    MAX({$tgl}) as last_purchase,
                    CURRENT_DATE - MAX({$tgl}) as recency_days,
                    COUNT(DISTINCT no_fak_jl) as frequency,
                    SUM(total_harga) as monetary,
                    CASE
                        WHEN CURRENT_DATE - MAX({$tgl}) <= 30 AND COUNT(DISTINCT no_fak_jl) >= 3 THEN 'Champions'
                        WHEN CURRENT_DATE - MAX({$tgl}) <= 60 AND COUNT(DISTINCT no_fak_jl) >= 2 THEN 'Loyal'
                        WHEN CURRENT_DATE - MAX({$tgl}) <= 90 THEN 'At Risk'
                        ELSE 'Lost'
                    END as rfm_segment
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY nama_pelanggan
                ORDER BY monetary DESC LIMIT 20";
        }

        // ── Metode pembayaran ─────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['metode bayar', 'pembayaran', 'payment', 'cara bayar', 'transfer', 'tunai', 'kredit'])
            && $allowSales) {
            $label = $hasW ? "Metode Pembayaran di " . ucwords($wilayahFilter) : "Metode Pembayaran";
            $queries[$label] = "
                SELECT CASE WHEN hari_jth_tempo > 0 THEN 'Kredit' ELSE 'Tunai' END as metode_bayar,
                    COUNT(*) as jumlah_transaksi,
                    SUM(total_harga) as total_revenue,
                    ROUND(AVG(total_harga), 0) as avg_transaksi,
                    ROUND(COUNT(*) * 100.0 / NULLIF(SUM(COUNT(*)) OVER (), 0), 2) as persen_penggunaan
                FROM {$vSales}
                " . ($hasW ? $wWhere : "WHERE 1=1") . "
                GROUP BY CASE WHEN hari_jth_tempo > 0 THEN 'Kredit' ELSE 'Tunai' END
                ORDER BY jumlah_transaksi DESC";
        }

        // ── Diskon ───────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['diskon', 'discount', 'promo', 'potongan'])
            && $allowSales) {
            $queries['Efektivitas Diskon'] = "
                SELECT CASE WHEN total_disc > 0 THEN 'Ada Diskon' ELSE 'Tanpa Diskon' END as status_diskon,
                    COUNT(*) as jumlah_transaksi,
                    ROUND(AVG(total_harga), 0) as rata_nilai,
                    SUM(total_harga) as total_revenue,
                    ROUND(SUM(total_disc), 2) as total_diskon_nominal
                FROM {$vSales}
                GROUP BY CASE WHEN total_disc > 0 THEN 'Ada Diskon' ELSE 'Tanpa Diskon' END";
        }

        // ── Dead stock ────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['dead stock', 'tidak laku', 'stok mati', 'tidak terjual', 'slow moving'])
            && $isAllowed('view_master_barang_mbi') && $isAllowed('view_data_kartu_stock_mbi')) {
            $queries['Dead Stock'] = "
                SELECT b.nama_barang, b.nama_kategori_barang,
                    SUM(s.qty_saldo_akhir) as stok_akhir,
                    SUM(s.qty_jual) as terjual
                FROM sch_mbi.view_master_barang_mbi b
                LEFT JOIN sch_mbi.view_data_kartu_stock_mbi s ON b.kode_barang = s.kode_kategori_barang
                GROUP BY b.nama_barang, b.nama_kategori_barang
                HAVING SUM(s.qty_saldo_akhir) > 0 AND (SUM(s.qty_jual) IS NULL OR SUM(s.qty_jual) = 0)
                LIMIT 50";
        }

        // ── Cross-sell ────────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['cross sell', 'cross-sell', 'kombinasi', 'sering dibeli bersama', 'bundle'])
            && $allowSales) {
            $queries['Cross-Sell'] = "
                SELECT dt1.nama_barang as produk_a, dt2.nama_barang as produk_b,
                    COUNT(*) as frekuensi_bersamaan
                FROM {$vSales} dt1
                JOIN {$vSales} dt2 ON dt1.no_fak_jl = dt2.no_fak_jl AND dt1.kode_barang < dt2.kode_barang
                GROUP BY dt1.nama_barang, dt2.nama_barang
                ORDER BY frekuensi_bersamaan DESC LIMIT 10";
        }

        // ── ABC Analysis ──────────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['abc', 'pareto', '80/20'])
            && $allowSales) {
            $queries['ABC Analysis'] = "
                SELECT nama_barang, total_pendapatan,
                    ROUND(total_pendapatan * 100.0 / NULLIF(SUM(total_pendapatan) OVER (), 0), 2) as persen,
                    ROUND(SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / NULLIF(SUM(total_pendapatan) OVER (), 0), 2) as kumulatif,
                    CASE
                        WHEN SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / NULLIF(SUM(total_pendapatan) OVER (), 0) <= 80 THEN 'A - Prioritas'
                        WHEN SUM(total_pendapatan) OVER (ORDER BY total_pendapatan DESC) * 100.0 / NULLIF(SUM(total_pendapatan) OVER (), 0) <= 95 THEN 'B - Menengah'
                        ELSE 'C - Rendah'
                    END as kategori_abc
                FROM (
                    SELECT nama_barang, SUM(total_harga) as total_pendapatan
                    FROM {$vSales}
                    GROUP BY nama_barang
                ) sub ORDER BY total_pendapatan DESC LIMIT 20";
        }

        // ── Customer Retention ────────────────────────────────────────────────
        if ($this->hasKeyword($lower, ['retention', 'pelanggan baru', 'pelanggan kembali', 'repeat order', 'repeat buyer'])
            && $allowSales) {
            $queries['Customer Retention'] = "
                SELECT periode_tahun || '-' || periode_bulan as bulan,
                    COUNT(DISTINCT CASE WHEN fb.bulan_pertama = (periode_tahun || '-' || periode_bulan) THEN tr.kode_pelanggan END) as pelanggan_baru,
                    COUNT(DISTINCT CASE WHEN fb.bulan_pertama != (periode_tahun || '-' || periode_bulan) THEN tr.kode_pelanggan END) as pelanggan_kembali
                FROM {$vSales} tr
                JOIN (
                    SELECT kode_pelanggan, MIN(periode_tahun || '-' || periode_bulan) as bulan_pertama
                    FROM {$vSales} GROUP BY kode_pelanggan
                ) fb ON tr.kode_pelanggan = fb.kode_pelanggan
                GROUP BY periode_tahun, periode_bulan
                ORDER BY periode_tahun DESC, periode_bulan DESC LIMIT 12";
        }

        // ── Fallback: ringkasan umum ──────────────────────────────────────────
        if (empty($queries)) {
            if ($allowSales) {
                $queries[$hasW ? "Ringkasan di " . ucwords($wilayahFilter) : 'Ringkasan Bisnis'] = "
                    SELECT COUNT(DISTINCT no_fak_jl) as total_transaksi,
                        COALESCE(SUM(total_harga), 0) as total_revenue,
                        COUNT(DISTINCT kode_pelanggan) as total_pelanggan,
                        ROUND(AVG(total_harga), 0) as avg_order_value
                    FROM {$vSales}
                    " . ($hasW ? $wWhere : "WHERE 1=1");
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
            // Indonesian keywords
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
            // English keywords
            'product', 'bestseller', 'best seller', 'transaction', 'sales', 'customer',
            'buyer', 'category', 'stock', 'report', 'analysis', 'data',
            'trend', 'statistics', 'ranking', 'best', 'highest',
            'lowest', 'total', 'amount', 'sum', 'count', 'month', 'year', 'region', 'province',
            'city', 'discount', 'profit', 'income', 'revenue', 'retention',
            'cross sell', 'dead stock', 'payment method', 'payment',
            'aov', 'see', 'show', 'display', 'find', 'search', 'how many', 'how much', 'what', 'who', 'which',
            'top', 'most', 'buy', 'sell', 'loyal', 'loyalty',
            'west java', 'central java', 'east java', 'jakarta', 'banten', 'bali',
            'sumatra', 'kalimantan', 'sulawesi', 'papua', 'aceh', 'riau', 'lampung',
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
    private function buildSystemPrompt(string $schemaContext, string $dbContext, string $docContext = '', string $userLanguage = 'id'): string
    {
        // Build data section with language-specific instructions
        $dataSection = '';
        $dataWarning = '';
        if (!empty($dbContext)) {
            if ($userLanguage === 'en') {
                $dataSection = "\n\n{$dbContext}\n";
                $dataWarning = "⚠️ CRITICAL: REAL DATABASE DATA IS PROVIDED ABOVE. YOU MUST USE THIS EXACT DATA IN YOUR RESPONSE. DO NOT MAKE UP NUMBERS OR FABRICATE ANY INFORMATION.\n";
            } else {
                $dataSection = "\n\n{$dbContext}\n";
                $dataWarning = "⚠️ PENTING: DATA NYATA DARI DATABASE SUDAH DISEDIAKAN DI ATAS. ANDA WAJIB MENGGUNAKAN DATA INI DALAM JAWABAN ANDA. JANGAN MENGARANG ANGKA ATAU INFORMASI.\n";
            }
        }

        // Build doc section with language-specific instructions
        $docSection = '';
        if (!empty($docContext)) {
            if ($userLanguage === 'en') {
                $docSection = "\n\n{$docContext}\nUSE THE GUIDE ABOVE to provide instructions to the user.\n";
            } else {
                $docSection = "\n\n{$docContext}\nGUNAKAN PANDUAN DI ATAS untuk memberikan instruksi kepada pengguna.\n";
            }
        }

        // Build language-specific instructions
        $languageInstruction = $userLanguage === 'en'
            ? "## LANGUAGE\n- ALWAYS respond in English since the user is using English.\n- Match the user's tone and formality level.\n"
            : "## BAHASA\n- SELALU jawab dalam Bahasa Indonesia karena user menggunakan Bahasa Indonesia.\n- Sesuaikan nada dan tingkat formalitas dengan user.\n";

        // Build personality section based on language
        $personalitySection = $userLanguage === 'en'
            ? "## PERSONALITY\n- Friendly and warm in greetings.\n- Answer general questions naturally.\n- Be a professional data analyst for data questions.\n- Provide clear step-by-step instructions for technical/operational ERP questions."
            : "## KEPRIBADIAN\n- Bisa diajak ngobrol santai dan merespons salam dengan hangat.\n- Untuk pertanyaan umum, jawab natural.\n- Untuk pertanyaan data, jadilah analis data profesional.\n- Untuk pertanyaan teknis/operasional ERP, berikan langkah-langkah yang jelas berdasarkan dokumentasi.";

        // Build data format section based on language
        $dataFormatSection = $userLanguage === 'en'
            ? "## DATA RESPONSE FORMAT (Only if access is granted)\n### 📊 Data Results\n| Column1 | Column2 |\n|---------|---------|\n| value   | value   |\n\n### 🔍 In-depth Analysis\n- **Key Findings**: insights from data\n- **Patterns & Trends**: visible patterns\n\n### 💡 Business Recommendations\n1. **[Action]**: concrete explanation"
            : "## FORMAT JAWABAN DATA (Hanya jika ada akses)\n### 📊 Hasil Data\n| Kolom1 | Kolom2 |\n|--------|--------|\n| nilai  | nilai  |\n\n### 🔍 Analisis Mendalam\n- **Temuan Utama**: insight dari data\n- **Pola & Tren**: pola yang terlihat\n\n### 💡 Rekomendasi Bisnis\n1. **[Aksi]**: penjelasan konkret";

        // Build docs format section based on language
        $docsFormatSection = $userLanguage === 'en'
            ? "## GUIDE/DOCS RESPONSE FORMAT\n- Provide step-by-step instructions (1, 2, 3...) for procedures.\n- Include documentation source links if available."
            : "## FORMAT JAWABAN PANDUAN/DOCS\n- Berikan langkah demi langkah (1, 2, 3...) jika itu sebuah prosedur.\n- Berikan link sumber dokumentasi jika tersedia.";

        // Build closing instruction based on language
        $closingInstruction = $userLanguage === 'en'
            ? "Use real data and guidance above. Do not fabricate information.\nFor casual conversation, respond naturally without report format."
            : "Gunakan data dan panduan nyata di atas. Jangan mengarang.\nUntuk percakapan santai, jawab natural tanpa format laporan.";

        // Build access denial message based on language
        $accessDenialMessage = $userLanguage === 'en'
            ? "1. IF USER ASKS ABOUT DATA FROM TABLES NOT LISTED IN 'TABLES YOU CAN ACCESS' BELOW, YOU MUST ANSWER WITH ONLY THIS SENTENCE: 'I'm sorry, I don't have access rights to display [information name] data for your account.'\n2. STRICTLY FORBIDDEN to provide reasons, alternative solutions, or example queries when access is denied. JUST ONE SENTENCE ONLY.\n3. NEVER FABRICATE DATA (HALLUCINATION)."
            : "1. JIKA USER BERTANYA TENTANG DATA DARI TABEL YANG TIDAK ADA DI 'TABEL YANG DAPAT ANDA AKSES' DI BAWAH, KAMU WAJIB MENJAWAB HANYA DENGAN SATU KALIMAT INI: 'Mohon maaf, saya tidak memiliki hak akses untuk menampilkan data [nama informasi] untuk akun Anda.'\n2. DILARANG KERAS MEMBERIKAN ALASAN, DILARANG MEMBERIKAN SOLUSI ALTERNATIF, DAN DILARANG MEMBERIKAN CONTOH QUERY JIKA AKSES DITOLAK. CUKUP SATU KALIMAT SAJA.\n3. JANGAN PERNAH MENGARANG DATA (HALLUCINATION).";

        // Put data context FIRST so it's most prominent
        return "### MAIN RULES (MANDATORY TO FOLLOW)
{$accessDenialMessage}

{$dataWarning}

You are a friendly, intelligent, and expert AI assistant serving as a Senior Data Analyst and ERP Consultant. Your nickname is DataBot.

{$personalitySection}

{$languageInstruction}

## DATA & DOCUMENTATION CONTEXT
{$schemaContext}
{$dataSection}
{$docSection}

{$dataFormatSection}

{$docsFormatSection}

{$closingInstruction}";
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
            $allowedTables = $this->getAllowedTables();
            $cacheKey = 'db_schema_context_role_' . (Auth::user() ? Auth::user()->role : 'guest');

            return cache()->remember($cacheKey, 300, function () use ($allowedTables) {
                $tables  = DB::connection('pgsql_mbi')->select("
                    SELECT table_name FROM information_schema.tables
                    WHERE table_schema = 'sch_mbi'
                    AND table_name NOT IN ('migrations','cache','cache_locks','sessions','jobs','failed_jobs','personal_access_tokens','users','password_reset_tokens')
                    ORDER BY table_name
                ");
                $context = "TABEL YANG DAPAT ANDA AKSES:\n";
                $count = 0;
                foreach ($tables as $table) {
                    $tn = $table->table_name;
                    if (!in_array($tn, $allowedTables)) continue;
                    
                    $count++;
                    $cols = DB::connection('pgsql_mbi')->select("
                        SELECT column_name, data_type FROM information_schema.columns
                        WHERE table_name = ? AND table_schema = 'sch_mbi'
                        ORDER BY ordinal_position
                    ", [$tn]);
                    $colStr = implode(", ", array_map(fn($c) => "{$c->column_name} ({$c->data_type})", $cols));
                    $context .= "- {$tn}: {$colStr}\n";
                }
                
                if ($count === 0) return "Anda tidak memiliki akses ke tabel data manapun.";

                try {
                    if (in_array('view_master_pelanggan_mbi', $allowedTables)) {
                        $sp = DB::connection('pgsql_mbi')->select("SELECT DISTINCT nama_propinsi_pelanggan as provinsi FROM sch_mbi.view_master_pelanggan_mbi WHERE nama_propinsi_pelanggan IS NOT NULL LIMIT 10");
                        if ($sp) {
                            $context .= "\nContoh nilai provinsi: " . implode(', ', array_column($sp, 'provinsi')) . "\n";
                        }
                    }
                } catch (\Exception $e) {}
                return $context;
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

    // ── Deteksi apakah kolom adalah nilai keuangan ────────────────────────────
    private function isMonetaryColumn(string $columnName): bool
    {
        $monetaryKeywords = [
            'bayar', 'revenue', 'profit', 'pendapatan', 'omzet', 'keuntungan',
            'biaya', 'cost', 'price', 'belanja', 'monetary', 'avg', 'rata_rata', 'rata-rata',
            'amount', 'sales'
        ];
        $quantityKeywords = [
            'qty', 'jumlah', 'total_terjual', 'total_transaksi', 'count', 'banyak',
            'kuantitas', 'unit', 'pcs', 'total_pelanggan', 'total_produk'
        ];
        $percentageKeywords = [
            'persen', 'persentase', 'percent', 'percentage', 'proporsi', 'rasio'
        ];
        
        $lower = mb_strtolower($columnName);
        
        // Percentage columns should NOT be formatted as Rupiah
        foreach ($percentageKeywords as $keyword) {
            if (str_contains($lower, $keyword)) return false;
        }
        
        // Quantity columns should NOT be formatted as Rupiah
        foreach ($quantityKeywords as $keyword) {
            if (str_contains($lower, $keyword)) return false;
        }
        
        // Then check if it's a monetary column
        foreach ($monetaryKeywords as $keyword) {
            if (str_contains($lower, $keyword)) return true;
        }
        
        // Special case: 'harga' alone is monetary, but check for false positives
        if ($lower === 'harga' || str_contains($lower, 'harga_')) return true;
        
        // Special case: 'total' at the beginning usually means money, unless it's quantity
        if (str_starts_with($lower, 'total_') && !str_contains($lower, 'terjual') && !str_contains($lower, 'transaksi')) {
            return true;
        }
        
        return false;
    }

    // ── Format nilai sebagai Rupiah ───────────────────────────────────────────
    private function formatRupiah(float|int $value): string
    {
        $value = (float) $value;
        // Format: Rp 1.000.000 (dengan titik sebagai pemisah ribuan)
        return 'Rp ' . number_format($value, 0, ',', '.');
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
