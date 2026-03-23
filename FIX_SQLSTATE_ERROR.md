# Fix: Chatbot SQLSTATE Error

## Masalah Utama

Chatbot selalu menghasilkan **SQLSTATE error** meskipun sudah mengecek schema tabel yang ada di database `data_mbi`. 

### Root Cause

Setelah investigasi, ditemukan bahwa masalah utamanya adalah:

1. **LLM Mengarang Nama Tabel yang Salah**
   - LLM menghasilkan SQL dengan nama tabel yang TIDAK ADA di database
   - Contoh tabel yang SALAH: `cabang`, `produk`, `pembeli`, `master_cabang`, `target`, `regions`
   - Tabel yang BENAR: `view_master_cabang_mbi`, `view_data_penjualan_rinci_mbi`, `view_master_pelanggan_mbi`, dll

2. **System Prompt Tidak Cukup Jelas**
   - System prompt di `planSQLQueries()` tidak memberikan daftar tabel yang eksplisit
   - LLM tidak diberi tahu dengan tegas untuk HANYA menggunakan nama tabel yang ada di schema

3. **Validasi SQL Kurang Ketat**
   - Validasi SQL tidak cukup detail dalam memeriksa apakah tabel yang digunakan benar-benar ada

## Solusi yang Diterapkan

### 1. Perbaikan `planSQLQueries()` - `app/Http/Controllers/ChatbotController.php`

**Sebelum:**
```php
$systemPrompt = "You are a SQL Planner. SCHEMA:
{$schemaContext}

RULES:
- Respond ONLY: [LABEL]User Language Label[/LABEL] [SQL]SELECT ...[/SQL]
- Use 'sch_mbi.' prefix.
...
```

**Sesudah:**
```php
$systemPrompt = "You are a SQL Planner for PostgreSQL database. SCHEMA INFORMATION:
{$schemaContext}

⚠️ CRITICAL RULES - READ CAREFULLY:
1. ONLY use table names that are explicitly listed in the SCHEMA above
2. NEVER invent table names like 'cabang', 'produk', 'pembeli', 'target', 'regions', 'master_cabang' - these DO NOT EXIST!
3. ALWAYS use the full table name with 'sch_mbi.' prefix
4. If user asks for data but you're unsure which table to use, respond with a clarification request
5. ONLY use column names that are listed for each table in the schema above

RESPONSE FORMAT:
- Respond ONLY: [LABEL]User Language Label[/LABEL] [SQL]SELECT ...[/SQL]
- Limit 50 rows maximum
- No explanation, no semicolon at the end

EXAMPLE CORRECT QUERIES:
✓ SELECT * FROM sch_mbi.view_master_cabang_mbi LIMIT 50
✓ SELECT nama_cabang, alamat_cabang FROM sch_mbi.view_master_cabang_mbi WHERE nama_propinsi_cabang ILIKE '%riau%'
✗ SELECT * FROM sch_mbi.cabang (WRONG - table 'cabang' does not exist!)
✗ SELECT * FROM sch_mbi.master_cabang (WRONG - use 'view_master_cabang_mbi' instead!)"
```

### 2. Perbaikan `getSchemaContext()` - `app/Http/Controllers/ChatbotController.php`

Menambahkan header yang jelas yang menampilkan SEMUA tabel yang tersedia:

```php
// IMPORTANT: Start with a clear header listing ALL available tables
$context = "AVAILABLE TABLES (USE EXACT NAMES AS SHOWN):\n";
$context .= "----------------------------------------\n";
$context .= "Table names you MUST use (choose from this list only):\n";
foreach (array_keys($tableGroups) as $tn) {
    $context .= "  - {$tn}\n";
}
$context .= "----------------------------------------\n\n";
$context .= "TABLE DETAILS (name(columns)):\n";
```

### 3. Perbaikan `validateSQL()` - `app/Http/Controllers/ChatbotController.php`

Menambahkan validasi yang lebih ketat:

```php
// 2. Pastikan semua tabel yang digunakan ada di daftar allowedTables
// Regex untuk mencari nama tabel setelah FROM, JOIN, INTO, UPDATE, dll
if (preg_match_all('/(?:from|join|into|update|table)\s+([a-zA-Z0-9_\.]+)/i', $sql, $matches)) {
    foreach ($matches[1] as $fullTableName) {
        // Skip subqueries and parentheses
        if (in_array(strtolower($fullTableName), ['select', '('])) continue;
        
        $parts = explode('.', $fullTableName);
        $tableName = end($parts);
        
        // Clean table name from any aliases or conditions
        $tableName = preg_replace('/\s+.*$/', '', $tableName);

        if (!in_array($tableName, $allowedTables)) {
            Log::warning("SQL Validation failed: Table '{$tableName}' (from '{$fullTableName}') is not in allowed tables.");
            return false;
        }
    }
}
```

### 4. Perbaikan Error Handling di `fetchRelevantData()` - `app/Http/Controllers/ChatbotController.php`

Menambahkan penanganan error SQLSTATE yang lebih baik:

```php
catch (\Illuminate\Database\QueryException $qe) {
    $errorCode = $qe->getCode();
    $errorMsg = $qe->getMessage();
    
    // Log detailed error information
    Log::error("Query '{$label}' failed with SQLSTATE error: {$errorMsg}", [
        'sql' => $sql,
        'code' => $errorCode,
        'label' => $label
    ]);
    
    // Provide user-friendly error message based on error code
    $userError = 'Query gagal dijalankan.';
    
    // PostgreSQL error codes (SQLSTATE)
    if (str_contains($errorMsg, '42P01') || str_contains($errorMsg, 'relation does not exist')) {
        $userError = 'Tabel yang diminta tidak ditemukan. Kemungkinan nama tabel salah.';
        Log::error("Table not found error - check if table name exists in schema");
    } elseif (str_contains($errorMsg, '42703') || str_contains($errorMsg, 'column does not exist')) {
        $userError = 'Kolom yang diminta tidak ditemukan dalam tabel.';
        Log::error("Column not found error - check column names");
    } elseif (str_contains($errorMsg, '42601')) {
        $userError = 'Sintaks SQL tidak valid.';
        Log::error("SQL syntax error");
    } elseif ($errorCode >= 1000) {
        // Connection or server errors
        $userError = 'Koneksi ke database gagal. Silakan coba lagi.';
    }
    
    $results[$label] = ['error' => $userError];
}
```

## Hasil Testing

Semua test case lolos:

```
✓ PASS: Valid query with correct table name
✓ PASS: Invalid query with wrong table name (cabang)
✓ PASS: Invalid query with wrong table name (master_cabang)
✓ PASS: Valid query with JOIN
✓ PASS: Invalid query with one wrong table in JOIN
✓ PASS: Valid query without schema prefix
✓ PASS: Invalid query with wrong table (produk)

Passed: 7/7
Failed: 0/7
```

## Database Connection Status

Koneksi ke database `data_mbi` dengan schema `sch_mbi` berfungsi dengan baik:

```
✓ Connected successfully!
Database: data_mbi
Host: 74.48.112.31
Port: 8832
Username: aicore
Schema: sch_mbi
Search Path: sch_mbi, public
```

## Tabel yang Tersedia di `sch_mbi`

Berikut adalah 18 tabel yang tersedia dan dapat digunakan oleh chatbot:

1. `view_data_intransit_pembelian_mbi`
2. `view_data_kartu_stock_barang_mbi`
3. `view_data_kartu_stock_mbi`
4. `view_data_penjualan_rinci_mbi`
5. `view_data_ssr_mbi`
6. `view_data_target_real_product_service_unit_mbi`
7. `view_data_target_realisasi_mbi`
8. `view_data_trm_mbi`
9. `view_master_barang_golongan_mbi`
10. `view_master_barang_kategori_mbi`
11. `view_master_barang_mbi`
12. `view_master_cabang_mbi`
13. `view_master_kelurahan_mbi`
14. `view_master_kecamatan_mbi`
15. `view_master_kabupaten_mbi`
16. `view_master_pelanggan_mbi`
17. `view_master_provinsi_mbi`
18. `view_target_unit_mbi`

## Rekomendasi Tambahan

1. **Update Cache Schema**: Setelah deploy, clear cache schema dengan:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

2. **Monitor Log**: Pantau `storage/logs/laravel.log` untuk error SQLSTATE yang masih muncul

3. **Testing Manual**: Test chatbot dengan query berikut:
   - "Tampilkan daftar cabang di Riau"
   - "Tampilkan 10 produk terlaris"
   - "Lihat 5 pelanggan terbaik di Medan"

## Files Modified

- `app/Http/Controllers/ChatbotController.php`
  - Method `planSQLQueries()` - Line ~196
  - Method `validateSQL()` - Line ~258
  - Method `getSchemaContext()` - Line ~920
  - Method `fetchRelevantData()` - Line ~420

## Tanggal Fix

23 Maret 2026
