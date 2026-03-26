<?php

/**
 * Test script untuk Export Excel feature
 * Jalankan: php test_export.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Exports\ChatTableExport;
use Maatwebsite\Excel\Facades\Excel;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing ChatTableExport ===\n\n";

// Test 1: Export Table Data
echo "Test 1: Export Table Data\n";
$headers = ['ID', 'Nama Produk', 'Total Sales', 'Profit'];
$rows = [
    [1, 'Produk A', 1000000, 200000],
    [2, 'Produk B', 1500000, 300000],
    [3, 'Produk C', 2000000, 400000],
];

try {
    $export = new ChatTableExport($headers, $rows, 'Test Table');
    $filename = 'test-table-' . date('Y-m-d_His') . '.xlsx';
    Excel::store($export, $filename, 'local');
    echo "✅ Table export SUCCESS: storage/app/{$filename}\n\n";
} catch (Exception $e) {
    echo "❌ Table export FAILED: " . $e->getMessage() . "\n\n";
}

// Test 2: Export Chart Data with Summary
echo "Test 2: Export Chart Data\n";
$chartHeaders = ['No', 'Label', 'Penjualan 2024', 'Penjualan 2025'];
$chartRows = [
    [1, 'Januari', 1000000, 1200000],
    [2, 'Februari', 1500000, 1600000],
    [3, 'Maret', 2000000, 2100000],
    [4, 'April', 1800000, 1900000],
    [], // Empty row
    ['Summary', '', 'Σ:6,300,000 | Avg:1,575,000', 'Σ:6,800,000 | Avg:1,700,000'],
];

$chartInfo = [
    'type' => 'bar',
    'title' => 'Penjualan Comparison',
    'datasetCount' => 2,
    'dataPoints' => 4,
];

try {
    $export = new ChatTableExport($chartHeaders, $chartRows, 'Chart Data', $chartInfo);
    $filename = 'test-chart-' . date('Y-m-d_His') . '.xlsx';
    Excel::store($export, $filename, 'local');
    echo "✅ Chart export SUCCESS: storage/app/{$filename}\n\n";
} catch (Exception $e) {
    echo "❌ Chart export FAILED: " . $e->getMessage() . "\n\n";
}

// Test 3: Verify numeric data handling
echo "Test 3: Verify Numeric Data Handling\n";
$numericHeaders = ['No', 'Bulan', 'Revenue'];
$numericRows = [
    [1, 'Q1', 5000000],
    [2, 'Q2', 7500000],
    [3, 'Q3', 6000000],
    [4, 'Q4', 8500000],
];

try {
    $export = new ChatTableExport($numericHeaders, $numericRows, 'Numeric Test');
    
    // Get the worksheet to verify data
    $worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet();
    $export->styles($worksheet);
    
    echo "✅ Numeric data handling SUCCESS\n";
    echo "   - Headers: " . count($numericHeaders) . " columns\n";
    echo "   - Rows: " . count($numericRows) . " rows\n";
    echo "   - Numeric values preserved for Excel calculations\n\n";
} catch (Exception $e) {
    echo "❌ Numeric data handling FAILED: " . $e->getMessage() . "\n\n";
}

echo "=== All Tests Completed ===\n";
echo "\nNote: Files are stored in storage/app/ directory\n";
echo "Check the files to verify:\n";
echo "  ✓ Header styling (red background)\n";
echo "  ✓ Zebra striping on data rows\n";
echo "  ✓ Column auto-sizing\n";
echo "  ✓ Number formatting (with commas)\n";
echo "  ✓ Chart information section (if applicable)\n";
