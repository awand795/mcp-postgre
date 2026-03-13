<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$id = 41;
$doc = DB::table('documentation')->where('id', $id)->first();

if (!$doc) {
    die("Error: Document ID $id not found.\n");
}

$enrichment = "

### Daftar Field Formulir Lengkap (Hasil Analisis Gambar):

#### 1. Bagian Header (Informasi Utama):
- **No. Transaksi**: Otomatis dihasilkan sistem ERP.
- **Tgl. Transaksi**: Tanggal pengajuan klaim.
- **No. Referensi**: Nomor referensi eksternal atau internal.
- **Tgl. Referensi**: Tanggal referensi tersebut.
- **Jenis Klaim**: Dropdown (contoh: Barang Rusak, Barang Kurang, dll).
- **Cabang**: Cabang yang melakukan klaim.
- **Supplier**: Supplier tujuan klaim.
- **Keterangan**: Catatan umum untuk satu transaksi klaim.

#### 2. Detail Barang / Tambah Detail (Modal):
**A. Data TTB & Asal Barang:**
- **No. Transaksi TTB**: Referensi nomor TTB yang akan diklaim.
- **Tgl. Transaksi TTB**: Tanggal pencatatan TTB.
- **No. TTB / Tgl. TTB**: Nomor dan tanggal dokumen fisik TTB.
- **Kode / Nama Langganan**: Data pelanggan (jika ada keterkaitan).
- **Kode / Nama Barang**: Identitas barang yang diklaim.
- **No. / Tgl. Faktur Jual**: Referensi jika barang sudah sempat terjual.
- **Qty. TTB**: Total qty yang diterima di TTB asal.
- **Qty. Sisa**: Sisa qty yang masih bisa diklaim.
- **Qty. Klaim**: Jumlah qty yang secara administrasi diklaim.
- **Qty. Kirim**: Jumlah qty barang yang secara fisik dikirim balik.

**B. Data Faktur Pembelian (Referensi Harga):**
- **No. Transaksi Beli**: Referensi faktur pembelian dari supplier.
- **Tgl. Faktur Beli**: Tanggal faktur pembelian.
- **Qty. Faktur**: Jumlah barang di faktur asli.
- **Harga Faktur**: Harga satuan beli.
- **Disc. Item % [1-5]**: Diskon bertingkat per item.
- **Disc. Item Nominal**: Potongan harga nominal per item.
- **Jenis PPN**: Pilihan (Include, Exclude, atau Non PPN).
- **% PPN**: Tarif pajak yang berlaku.

**C. Nilai Klaim (Ringkasan Biaya):**
- **Total Harga**: Perkalian qty dan harga.
- **Total Discount**: Total potongan harga.
- **Total DPP**: Dasar Pengenaan Pajak.
- **Total PPN**: Nilai pajak.
- **Pembulatan**: Penyesuaian nilai akhir.
- **Total Netto**: Nilai bersih klaim yang diajukan.

**D. Keterangan Detail**: Catatan spesifik untuk baris barang tersebut.
";

// Append enrichment to content if not already present
if (!str_contains($doc->content, "Daftar Field Formulir Lengkap")) {
    $newContent = $doc->content . "\n\n" . $enrichment;
    DB::table('documentation')->where('id', $id)->update(['content' => $newContent]);
    echo "Document ID $id enriched successfully.\n";
} else {
    echo "Document ID $id is already enriched.\n";
}
