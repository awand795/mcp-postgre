<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:enrich-documentation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich existing documentation with form field details';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Documentation Enrichment...');

        $enrichments = [
            'Order Pembelian' => "

### Daftar Field Formulir Lengkap (Hasil Analisis Gambar):

#### 1. Bagian Header (Informasi Utama):
- **No. Transaksi**: Otomatis dihasilkan sistem.
- **Tgl. Transaksi**: Tanggal pencatatan.
- **Tgl. PO**: Tanggal Order Pembelian.
- **T.O.P Hari**: Jangka waktu pembayaran.
- **Cabang**: Cabang pembuat pesanan.
- **Gudang Tujuan**: Gudang penerima barang.
- **Cabang Tujuan**: Cabang penerima barang.
- **Supplier**: Nama pemasok.
- **Agen**: Nama agen.
- **Mata Uang**: IDR atau mata uang asing lainnya.
- **Nilai Kurs Rp**: Kurs mata uang.
- **Jenis PPN**: Include, Exclude, atau Non PPN.
- **% PPN**: Persentase pajak.
- **No. Transaksi PR**: Referensi Permintaan Pembelian.
- **Keterangan**: Catatan tambahan.

#### 2. Detail Barang (Grid/Modal):
- **Kode Barang**: ID unik produk.
- **Nama Barang**: Deskripsi produk.
- **Qty Order**: Jumlah pesanan.
- **Satuan**: Satuan barang (Pcs, Box, dll).
- **Harga [Kurs] / [Rp]**: Harga satuan.
- **Disc Item % (1-5)**: Diskon bertingkat.
- **Disc Item Nom. Rp**: Diskon per item.
- **Netto Rp**: Harga bersih per item.

#### 3. Ringkasan (Grand Total):
- **DPP, PPN, dan Netto (Total)**: Rekapitulasi nilai transaksi.
- **Tombol \'Ambil Data PR\'**: Gunakan jika data berasal dari PR.",

            'Permintaan Pembelian' => "

### Daftar Field Formulir Lengkap (Hasil Analisis Gambar):

#### 1. Bagian Header:
- **No. Transaksi**: Otomatis.
- **Tgl. Transaksi**: Tanggal PR.
- **Cabang**: Lokasi cabang.
- **Mata Uang & Kurs**: Pengaturan kurs.
- **Jenis PPN & %**: Pengaturan pajak.
- **Jangka Waktu Stock (Bulan)**: Estimasi stok.

#### 2. Tabel Detail Barang:
- **Kode & Nama Barang**: Identitas barang.
- **Qty Stock saat PR**: Informasi stok saat ini.
- **Qty PO DS (1-4)**: Kuantitas PO berjalan.
- **Qty Jual (Avg)**: Rata-rata penjualan.
- **Stock Level (OH & SM)**: On Hand & Safety Margin.
- **Qty**: Jumlah yang diminta (diisi manual).

#### 3. Tombol Aksi Penting:
- **Tambah Supplier**: Untuk menentukan pemasok.
- **Tambah Detail**: Untuk memilih daftar barang.",

            'Klaim Barang' => "

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

**D. Keterangan Detail**: Catatan spesifik untuk baris barang tersebut."
        ];

        foreach ($enrichments as $title => $enrichment) {
            $doc = DB::table('documentation')->where('title', 'ILIKE', "%{$title}%")->first();
            if ($doc) {
                if (!str_contains($doc->content, "Daftar Field Formulir Lengkap")) {
                    DB::table('documentation')->where('id', $doc->id)->update([
                        'content' => $doc->content . $enrichment
                    ]);
                    $this->info("Enriched: {$doc->title}");
                } else {
                    $this->warn("Already enriched: {$doc->title}");
                }
            } else {
                $this->error("Not found: {$title}");
            }
        }

        $this->info('Enrichment completed.');
    }
}
