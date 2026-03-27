# Excel Export Troubleshooting Guide

## Problem: Export Fails with Large Data

When exporting large datasets (500+ rows) from the Smart Table to Excel, the export may fail due to:

1. **PHP Memory Limit** - PhpSpreadsheet requires significant memory
2. **Execution Timeout** - Large exports take longer than default timeout
3. **POST Size Limits** - Data sent via POST request may exceed limits
4. **Browser Limitations** - Large payloads can cause browser issues

---

## Solutions Applied

### 1. Controller-Level Fixes (`AgenticChatbotController.php`)

```php
// Increased time and memory limits for large exports
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

// Better error handling with user-friendly messages
try {
    // Export logic
} catch (\Exception $e) {
    \Log::error('Excel export failed: ' . $e->getMessage());
    return response()->json([
        'error' => 'Export gagal: ' . $e->getMessage(),
        'message' => 'Data terlalu besar atau terjadi kesalahan saat memproses export.'
    ], 500);
}
```

### 2. Export Class Optimizations (`ChatTableExport.php`)

- Selective column auto-sizing (first 10 columns only)
- Conditional summary row styling (only when needed)
- Memory-efficient row handling

### 3. Frontend Improvements (`chatbot.blade.php`)

- Warning dialog for large datasets (500+ rows)
- Better error messages based on error type
- Validation of downloaded blob
- Success confirmation for large exports

---

## Server Configuration Recommendations

### PHP Configuration (`php.ini`)

Add or update these settings for better large export support:

```ini
; Memory settings
memory_limit = 512M

; Execution time
max_execution_time = 300
max_input_time = 300

; POST limits
post_max_size = 64M
upload_max_filesize = 64M

; Input variables (for large arrays)
max_input_vars = 10000
```

### Nginx Configuration (if using Nginx)

```nginx
http {
    # Increase buffer size for large POST requests
    client_max_body_size 64M;
    client_body_buffer_size 128k;
    
    # Increase timeouts
    proxy_connect_timeout 300;
    proxy_send_timeout 300;
    proxy_read_timeout 300;
}
```

### Apache Configuration (if using Apache)

Add to `.htaccess` or `httpd.conf`:

```apache
# Increase limits
LimitRequestBody 67108864
Timeout 300

# PHP settings (if mod_php is enabled)
php_value memory_limit 512M
php_value max_execution_time 300
php_value post_max_size 64M
php_value upload_max_filesize 64M
php_value max_input_vars 10000
```

---

## Usage Best Practices

### For Users

1. **Use Filters**: Filter data before exporting to reduce row count
2. **Be Patient**: Large exports (1000+ rows) may take 30-60 seconds
3. **Check Browser Console**: If export fails, check console for detailed errors
4. **Try Smaller Batches**: For very large datasets, export in multiple smaller batches

### Recommended Export Limits

| Data Size | Expected Time | Success Rate | Recommendation |
|-----------|---------------|--------------|----------------|
| < 500 rows | < 5 seconds | ✅ Excellent | No issues expected |
| 500-1000 rows | 5-15 seconds | ✅ Good | Warning dialog shown |
| 1000-5000 rows | 15-60 seconds | ⚠️ Fair | Use filters if possible |
| 5000+ rows | 60+ seconds | ❌ Poor | Strongly recommend filtering |

---

## Error Messages and Solutions

### ⏰ Export timeout
```
Export timeout: Data terlalu besar. Silakan coba dengan filter yang lebih spesifik.
```
**Solution**: Apply date range or other filters to reduce data size.

### 💾 Memory limit
```
Memory limit: Data terlalu besar untuk diproses.
```
**Solution**: Filter data or contact administrator to increase server memory limit.

### 📦 Payload too large
```
Data terlalu besar: Payload melebihi batas.
```
**Solution**: Export smaller batches or contact administrator to increase `post_max_size`.

### 🔧 Server error
```
Server error: Terjadi kesalahan di server.
```
**Solution**: Check server logs (`storage/logs/laravel.log`) for details.

---

## Debugging

### Enable Debug Logging

Add to `.env`:
```
LOG_LEVEL=debug
```

Check logs at:
```
storage/logs/laravel.log
```

### Test Export with Sample Data

Create a test script `test_export.php`:
```php
<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Exports\ChatTableExport;
use Maatwebsite\Excel\Facades\Excel;

// Test with 1000 rows
$headers = ['No', 'Name', 'Total', 'Date'];
$rows = [];
for ($i = 1; $i <= 1000; $i++) {
    $rows[] = [$i, "Item {$i}", rand(1000, 10000), date('Y-m-d')];
}

$export = new ChatTableExport($headers, $rows, 'Test Export');
Excel::download($export, 'test-export.xlsx');
```

---

## Alternative Export Methods

### CSV Export (Lighter Alternative)

For very large datasets, consider implementing CSV export as a lighter alternative:

```javascript
function exportToCSV(headers, rows, filename) {
    const csvContent = [
        headers.join(','),
        ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
    ].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    // Download logic...
}
```

---

## Contact Support

If issues persist after applying these fixes:
1. Check server logs for detailed error messages
2. Verify PHP configuration matches recommendations
3. Test with smaller datasets first
4. Contact your system administrator

---

**Last Updated**: March 27, 2026
**Version**: 1.0
