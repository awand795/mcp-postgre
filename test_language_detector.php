<?php

/**
 * Language Detector Test Script
 * 
 * Run this script to test the language detection functionality:
 * php test_language_detector.php
 */

require __DIR__ . '/vendor/autoload.php';

use App\Helpers\LanguageDetector;

$detector = new LanguageDetector();

$testCases = [
    // Indonesian tests
    [
        'text' => 'Halo, apa kabar?',
        'expected' => 'id',
        'description' => 'Indonesian greeting'
    ],
    [
        'text' => 'Tampilkan total penjualan bulan ini',
        'expected' => 'id',
        'description' => 'Indonesian business query'
    ],
    [
        'text' => 'Berapa revenue dari wilayah Jawa Barat?',
        'expected' => 'id',
        'description' => 'Indonesian revenue question'
    ],
    [
        'text' => 'Saya ingin melihat produk terlaris',
        'expected' => 'id',
        'description' => 'Indonesian product query'
    ],
    [
        'text' => 'Bagaimana cara membuat laporan keuangan?',
        'expected' => 'id',
        'description' => 'Indonesian how-to question'
    ],
    
    // English tests
    [
        'text' => 'Hello, how are you?',
        'expected' => 'en',
        'description' => 'English greeting'
    ],
    [
        'text' => 'Show me total sales for this month',
        'expected' => 'en',
        'description' => 'English business query'
    ],
    [
        'text' => 'What is the revenue from West Java region?',
        'expected' => 'en',
        'description' => 'English revenue question'
    ],
    [
        'text' => 'I want to see the bestseller products',
        'expected' => 'en',
        'description' => 'English product query'
    ],
    [
        'text' => 'How to create a financial report?',
        'expected' => 'en',
        'description' => 'English how-to question'
    ],
    
    // Mixed/ambiguous tests
    [
        'text' => 'Show me data penjualan',
        'expected' => 'id',  // Mixed language will be detected based on patterns
        'description' => 'Mixed English-Indonesian (detected based on patterns)'
    ],
    [
        'text' => 'Tampilkan revenue',
        'expected' => 'id',
        'description' => 'Mixed Indonesian-English (should detect based on pattern)'
    ],
];

echo "===========================================\n";
echo "LANGUAGE DETECTOR TEST\n";
echo "===========================================\n\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $index => $test) {
    $detected = $detector->detect($test['text']);
    $info = $detector->detectWithInfo($test['text']);
    
    $status = $detected === $test['expected'] ? '✓ PASS' : '✗ FAIL';
    
    if ($detected === $test['expected']) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo sprintf(
        "Test %d: %s\n",
        $index + 1,
        $status
    );
    echo sprintf(
        "  Description: %s\n",
        $test['description']
    );
    echo sprintf(
        "  Text: \"%s\"\n",
        $test['text']
    );
    echo sprintf(
        "  Expected: %s | Detected: %s (%s)\n",
        $test['expected'],
        $detected,
        $info['name']
    );
    echo sprintf(
        "  Confidence: %d%%\n",
        $info['confidence']
    );
    echo "\n";
}

echo "===========================================\n";
echo sprintf("RESULTS: %d passed, %d failed out of %d tests\n", $passed, $failed, count($testCases));
echo "===========================================\n";

exit($failed > 0 ? 1 : 0);
