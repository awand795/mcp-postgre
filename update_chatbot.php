<?php

$file = __DIR__ . '/app/Http/Controllers/ChatbotController.php';
$content = file_get_contents($file);

// Replace getColTanggal
$content = preg_replace(
    '/(private function getColTanggal\(\): string\s*\{).*?(^\s*\}  \/\/ default fallback\s*\}\s*return \$this->colTanggal;\s*\})/ms',
    "\\1\n        return 'tgl_fak_jl';\n    }",
    $content
);

// Replace getColTotalBayar
$content = preg_replace(
    '/(private function getColTotalBayar\(\): string\s*\{).*?(return \'total_bayar\';\s*\}\);\s*\})/ms',
    "\\1\n        return 'total_harga';\n    }",
    $content
);

// Replace the schema fetching lines
$content = str_replace(
    "SELECT table_name FROM information_schema.tables\n                    WHERE table_schema = 'public'",
    "SELECT table_name FROM information_schema.tables\n                    WHERE table_schema = 'sch_mbi'",
    $content
);
$content = str_replace(
    "SELECT column_name, data_type FROM information_schema.columns\n                        WHERE table_name = ? AND table_schema = 'public'",
    "SELECT column_name, data_type FROM information_schema.columns\n                        WHERE table_name = ? AND table_schema = 'sch_mbi'",
    $content
);
$content = str_replace(
    "if (in_array('pembeli', \$allowedTables)) {",
    "if (in_array('view_master_pelanggan_mbi', \$allowedTables)) {",
    $content
);
$content = str_replace(
    "\$sp = DB::connection('pgsql_mbi')->select(\"SELECT DISTINCT provinsi FROM pembeli WHERE provinsi IS NOT NULL LIMIT 10\");",
    "\$sp = DB::connection('pgsql_mbi')->select(\"SELECT DISTINCT nama_propinsi_pelanggan as provinsi FROM sch_mbi.view_master_pelanggan_mbi WHERE nama_propinsi_pelanggan IS NOT NULL LIMIT 10\");",
    $content
);

// Extract the selectQueries method to replace it
$newSelectQueries = <<<'EOD'
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
EOD;

$content = preg_replace(
    '/(private function selectQueries\(string \$lower, string \$wilayahFilter = \'\'\): array\s*\{).*?(return \$queries;\s*\})/ms',
    $newSelectQueries,
    $content
);

file_put_contents($file, $content);
echo "Updated ChatbotController.php";
