<?php
/**
 * Simple test streaming - langsung ke OpenRouter
 * Akses: http://localhost:8000/test_stream.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head><title>Stream Test</title></head>
<body>
<h1>Streaming Test</h1>
<div id="output" style="font-family:monospace;white-space:pre-wrap;background:#f0f0f0;padding:10px;"></div>
<script>
const output = document.getElementById('output');
function log(msg) { output.textContent += msg + '\n'; }

(async () => {
    try {
        log('Starting test...');
        const response = await fetch('chatbot/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                message: 'Say hello in one word',
                history: [],
                stream: true
            })
        });

        log('Response status: ' + response.status);

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) { log('Stream complete'); break; }

            const chunk = decoder.decode(value);
            buffer += chunk;
            log('Received: ' + chunk.substring(0, 100));
        }
    } catch (e) {
        log('ERROR: ' + e.message);
    }
})();
</script>
</body>
</html>
