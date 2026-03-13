<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$po = DB::table('documentation')->where('title', 'ILIKE', '%Order Pembelian%')->first();
if ($po) {
    $detailPO = "\n\n### Daftar Field Formulir Lengkap (Hasil Analisis Gambar):\n\n" .
        "#### 1. Bagian Header (Informasi Utama):\n" .
        "- **No. Transaksi**: Otomatis dihasilkan sistem.\n" .
        "- **Tgl. Transaksi**: Tanggal pencatatan.\n" .
        "- **Tgl. PO**: Tanggal Order Pembelian.\n" .
        "- **T.O.P Hari**: Jangka waktu pembayaran.\n" .
        "- **Cabang**: Cabang pembuat pesanan.\n" .
        "- **Gudang Tujuan**: Gudang penerima barang.\n" .
        "- **Cabang Tujuan**: Cabang penerima barang.\n" .
        "- **Supplier**: Nama pemasok.\n" .
        "- **Agen**: Nama agen.\n" .
        "- **Mata Uang**: IDR atau mata uang asing lainnya.\n" .
        "- **Nilai Kurs Rp**: Kurs mata uang.\n" .
        "- **Jenis PPN**: Include, Exclude, atau Non PPN.\n" .
        "- **% PPN**: Persentase pajak.\n" .
        "- **No. Transaksi PR**: Referensi Permintaan Pembelian.\n" .
        "- **Keterangan**: Catatan tambahan.\n\n" .
        "#### 2. Detail Barang (Grid/Modal):\n" .
        "- **Kode Barang**: ID unik produk.\n" .
        "- **Nama Barang**: Deskripsi produk.\n" .
        "- **Qty Order**: Jumlah pesanan.\n" .
        "- **Satuan**: Satuan barang (Pcs, Box, dll).\n" .
        "- **Harga [Kurs] / [Rp]**: Harga satuan.\n" .
        "- **Disc Item % (1-5)**: Diskon bertingkat.\n" .
        "- **Disc Item Nom. Rp**: Diskon per item.\n" .
        "- **Netto Rp**: Harga bersih per item.\n\n" .
        "#### 3. Ringkasan (Grand Total):\n" .
        "- **DPP, PPN, dan Netto (Total)**: Rekapitulasi nilai transaksi.\n" .
        "- **Tombol 'Ambil Data PR'**: Gunakan jika data berasal dari PR.";
    
    DB::table('documentation')->where('id', $po->id)->update(['content' => $po->content . $detailPO]);
    echo "PO enriched.\n";
}

$pr = DB::table('documentation')->where('title', 'ILIKE', '%Permintaan Pembelian%')->first();
if ($pr) {
    $detailPR = "\n\n### Daftar Field Formulir Lengkap (Hasil Analisis Gambar):\n\n" .
        "#### 1. Bagian Header:\n" .
        "- **No. Transaksi**: Otomatis.\n" .
        "- **Tgl. Transaksi**: Tanggal PR.\n" .
        "- **Cabang**: Lokasi cabang.\n" .
        "- **Mata Uang & Kurs**: Pengaturan kurs.\n" .
        "- **Jenis PPN & %**: Pengaturan pajak.\n" .
        "- **Jangka Waktu Stock (Bulan)**: Estimasi stok.\n\n" .
        "#### 2. Tabel Detail Barang:\n" .
        "- **Kode & Nama Barang**: Identitas barang.\n" .
        "- **Qty Stock saat PR**: Informasi stok saat ini.\n" .
        "- **Qty PO DS (1-4)**: Kuantitas PO berjalan.\n" .
        "- **Qty Jual (Avg)**: Rata-rata penjualan.\n" .
        "- **Stock Level (OH & SM)**: On Hand & Safety Margin.\n" .
        "- **Qty**: Jumlah yang diminta (diisi manual).\n\n" .
        "#### 3. Tombol Aksi Penting:\n" .
        "- **Tambah Supplier**: Untuk menentukan pemasok.\n" .
        "- **Tambah Detail**: Untuk memilih daftar barang.";
    
    DB::table('documentation')->where('id', $pr->id)->update(['content' => $pr->content . $detailPR]);
    echo "PR enriched.\n";
}
