# Fitur Export Excel untuk Tabel dan Grafik AI

## Overview
Fitur ini memungkinkan pengguna untuk mengekspor tabel dan grafik yang dihasilkan oleh AI chatbot ke format Excel (.xlsx).

## Cara Menggunakan

### Export Tabel
1. Setelah AI menampilkan tabel data (smart table), akan ada toolbar di bagian atas tabel
2. Klik tombol **"Export Excel"** (berwarna hijau dengan ikon download)
3. File Excel akan otomatis diunduh dengan nama `table-export-{timestamp}.xlsx`
4. File berisi semua data tabel dengan format:
   - Header berwarna merah (tema Darko)
   - Zebra striping untuk baris data
   - Auto-size columns
   - Format angka dan currency otomatis

### Export Grafik
1. Setelah AI menampilkan grafik, akan ada toolbar di bagian atas grafik
2. Klik tombol **"Export Excel"** (berwarna hijau dengan ikon download)
3. File Excel akan otomatis diunduh dengan nama `chart-{title}-{timestamp}.xlsx`
4. File berisi:
   - **Data grafik yang PERSIS sama** dengan yang ditampilkan
   - Kolom: No, Label, dan nilai setiap dataset
   - **Summary statistics**: Sum (Σ), Average, Min, Max untuk setiap dataset
   - **Chart Information**: Type, Title, Dataset count, Data points, Export timestamp

## Fitur Export

### Tabel
- ✅ Semua baris data (tidak terbatas pada halaman yang ditampilkan)
- ✅ Format currency otomatis untuk kolom: total, amount, harga, price, profit, dll
- ✅ Header dengan warna tema (#F53003 - Darko red)
- ✅ Zebra striping (selang-seling warna)
- ✅ Auto-size columns
- ✅ Freeze header row
- ✅ Border dan styling profesional

### Grafik (DIPERBAIKI)
- ✅ **Data EKSAK sesuai yang ditampilkan di grafik**
- ✅ Nilai numerik murni (bisa langsung di-sum/average di Excel)
- ✅ Summary statistics otomatis:
  - **Σ (Sum)**: Total keseluruhan
  - **Avg**: Rata-rata
  - **Min**: Nilai minimum
  - **Max**: Nilai maksimum
- ✅ Chart metadata:
  - Type (bar, line, pie, dll)
  - Title
  - Jumlah datasets
  - Jumlah data points
  - Timestamp export
- ✅ Format currency untuk nilai besar (Ribuan/Jutaan)
- ✅ Siap untuk analisis lebih lanjut di Excel

## Contoh Data Export Chart

### Data di Grafik:
```
Label       | Dataset 1
------------|----------
Januari     | 1000000
Februari    | 1500000
Maret       | 2000000
```

### Data di Excel:
| No | Label    | Dataset 1 |
|----|----------|-----------|
| 1  | Januari  | 1000000   |
| 2  | Februari | 1500000   |
| 3  | Maret    | 2000000   |
|    |          |           |
| Summary | | Σ:4,500,000 | Avg:1,500,000 | Min:1,000,000 | Max:2,000,000 |

### Chart Information (di bagian bawah):
```
Chart Information:
Type: bar
Title: Penjualan Q1 2026
Datasets: 1
Data Points: 3
Exported: 2026-03-26 15:30:45
```

## Struktur File

### 1. ChatTableExport Class
**File:** `app/Exports/ChatTableExport.php`

Class ini menangani pembuatan file Excel dengan fitur:
- Dynamic headers dan rows
- Auto-detect currency columns
- Professional styling
- Column auto-sizing

```php
// Usage example
$export = new ChatTableExport(
    headers: ['ID', 'Nama', 'Total'],
    rows: [
        [1, 'Produk A', 1000000],
        [2, 'Produk B', 2000000],
    ],
    title: 'Data Export'
);
```

### 2. Frontend Functions
**File:** `resources/views/chatbot.blade.php`

#### Export Table Function
```javascript
exportTableToExcel(tableId, headers, rows)
```
- Mengambil data dari smart table
- Membersihkan data dari HTML tags
- Mengirim ke backend untuk diproses
- Download file Excel

#### Export Chart Function
```javascript
exportChartToExcel(chartId, chartConfig)
```
- Mengekstrak data dari Chart.js config
- Mengkonversi ke format tabel
- Mengirim ke backend untuk diproses
- Download file Excel

### 3. Backend Controller Method
**File:** `app/Http/Controllers/AgenticChatbotController.php`

```php
public function exportExcel(Request $request)
```
- Menerima headers, rows, dan filename dari frontend
- Validasi input
- Membuat ChatTableExport instance
- Return file Excel sebagai download

### 4. Route
**File:** `routes/web.php`

```php
Route::post('/chatbot/export/excel', [AgenticChatbotController::class, 'exportExcel'])
    ->name('chatbot.export.excel');
```

## Technical Details

### Data Flow
1. User klik "Export Excel" button
2. JavaScript mengumpulkan data dari tabel/grafik
3. Data dikirim via POST request ke backend
4. Backend memproses dengan PhpSpreadsheet (via Laravel Excel)
5. File Excel di-return sebagai binary blob
6. Browser mendownload file

### Security
- CSRF token protection
- Input validation
- Filename sanitization (max 255 chars)
- Auth middleware (hanya user login bisa akses)

### Dependencies
- `maatwebsite/excel` - Laravel Excel package
- `phpoffice/phpspreadsheet` - Excel processing library
- Chart.js - Untuk grafik (sudah terinstall)

## Contoh Penggunaan

### Skenario 1: Export Data Penjualan
```
User: "Tampilkan penjualan bulan ini per produk"
AI: [Menampilkan smart table dengan data penjualan]
User: [Klik Export Excel]
→ File: table-export-2026-03-26-10-30-45.xlsx
```

### Skenario 2: Export Data Grafik
```
User: "Buat grafik tren penjualan 6 bulan terakhir"
AI: [Menampilkan line chart dengan data bulanan]
User: [Klik Export Excel]
→ File: chart-export-2026-03-26-10-35-22.xlsx
```

## Troubleshooting

### "Gagal export tabel"
- Pastikan user sudah login
- Cek koneksi internet
- Pastikan data tabel sudah fully loaded

### File Excel kosong/rusak
- Cek browser console untuk error JavaScript
- Pastikan data tabel valid (headers dan rows tidak kosong)

### Export button tidak muncul
- Pastikan tabel menggunakan format `smart_table` code block
- Refresh halaman dan coba lagi

## Future Enhancements
- [ ] Export multiple sheets (data + summary)
- [ ] Include chart image in Excel
- [ ] Custom column formatting options
- [ ] Export to CSV format
- [ ] Export selected rows only
- [ ] Include metadata (export date, user, query)

## Credits
- Laravel Excel: https://docs.laravel-excel.com/
- PhpSpreadsheet: https://phpspreadsheet.readthedocs.io/
- Chart.js: https://www.chartjs.org/
