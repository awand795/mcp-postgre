<?php
/**
 * test_nvidia_nim.php
 * Jalankan dari root project: php test_nvidia_nim.php
 *
 * Script ini mencoba setiap model NVIDIA NIM yang mendukung tool calling
 * untuk menemukan mana yang bisa diakses dengan API key kamu.
 */

$apiKey = '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';
$apiUrl = 'https://integrate.api.nvidia.com/v1/chat/completions';

// Daftar model NVIDIA NIM yang mendukung tool calling
$models = [
    'meta/llama-3.3-70b-instruct',
    'meta/llama-3.1-8b-instruct',
    'meta/llama-3.1-70b-instruct',
    'mistralai/mistral-nemo-12b-instruct',
    'mistralai/mistral-large-2-instruct',
    'mistralai/mixtral-8x22b-instruct-v0.1',
    'nvidia/llama-3.1-nemotron-70b-instruct',
    'nvidia/llama-3.1-nemotron-nano-8b-instruct',
    'google/gemma-2-9b-it',
    'meta/llama-3.2-3b-instruct',
    'meta/llama-3.2-1b-instruct',
];

// Payload ringan dengan tool calling
$toolPayload = [
    'messages' => [
        ['role' => 'user', 'content' => 'What is 2+2? Use the calculator tool.'],
    ],
    'tools' => [
        [
            'type' => 'function',
            'function' => [
                'name' => 'calculator',
                'description' => 'Perform math operations',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'expression' => ['type' => 'string', 'description' => 'Math expression'],
                    ],
                    'required' => ['expression'],
                ],
            ],
        ],
    ],
    'tool_choice' => 'auto',
    'max_tokens'  => 200,
    'temperature' => 0.1,
];

echo "=== NVIDIA NIM Tool Calling Test ===\n";
echo "API Key: " . substr($apiKey, 0, 15) . "...\n\n";

$workingModels = [];

foreach ($models as $model) {
    $payload = array_merge($toolPayload, ['model' => $model]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode($body, true);

    if ($curlErr) {
        echo "❌ [{$model}] cURL error: {$curlErr}\n";
        continue;
    }

    if ($httpCode === 200 && isset($decoded['choices'])) {
        $finishReason = $decoded['choices'][0]['finish_reason'] ?? 'unknown';
        $hasToolCall  = !empty($decoded['choices'][0]['message']['tool_calls']);
        echo "✅ [{$model}] OK! finish_reason={$finishReason}, tool_call=" . ($hasToolCall ? 'YES' : 'no') . "\n";
        $workingModels[] = $model;
    } elseif ($httpCode === 402) {
        echo "💳 [{$model}] Credits required (402)\n";
    } elseif ($httpCode === 403) {
        $detail = $decoded['detail'] ?? ($decoded['title'] ?? 'Forbidden');
        echo "🔒 [{$model}] Access denied (403): {$detail}\n";
    } elseif ($httpCode === 404) {
        echo "🔍 [{$model}] Model not found (404)\n";
    } elseif ($httpCode === 422) {
        $errMsg = $decoded['detail'][0]['msg'] ?? ($decoded['detail'] ?? 'Validation error');
        if (is_array($errMsg)) $errMsg = json_encode($errMsg);
        echo "⚠️  [{$model}] Validation error (422): {$errMsg}\n";
    } else {
        $errMsg = $decoded['error']['message'] ?? ($decoded['detail'] ?? substr($body, 0, 100));
        echo "❌ [{$model}] HTTP {$httpCode}: {$errMsg}\n";
    }

    usleep(300000); // 300ms delay antar request
}

echo "\n=== HASIL ===\n";
if (empty($workingModels)) {
    echo "❌ Tidak ada model yang berhasil!\n";
    echo "\nKemungkinan penyebab:\n";
    echo "  1. API key tidak valid atau expired\n";
    echo "  2. API key memerlukan upgrade di https://build.nvidia.com\n";
    echo "  3. Semua model butuh approval khusus\n";
    echo "\nSolusi: Buat API key baru di https://build.nvidia.com -> Get API Key\n";
} else {
    echo "✅ Model yang bekerja:\n";
    foreach ($workingModels as $m) {
        echo "   - {$m}\n";
    }
    echo "\nGunakan model pertama di atas sebagai primary!\n";
}
