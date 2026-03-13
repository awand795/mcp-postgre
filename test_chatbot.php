<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ChatbotController;
use Illuminate\Http\Request;

$controller = new ChatbotController();
$request = new Request(['message' => 'bagaimana cara klaim barang', 'history' => []]);

echo "Sending request: bagaimana cara klaim barang\n";
echo "Waiting for AI response...\n\n";

$response = $controller->send($request);
$data = json_decode($response->getContent(), true);

if (isset($data['response'])) {
    echo "--- AI RESPONSE ---\n";
    echo $data['response'] . "\n";
    file_put_contents('test_response.txt', $data['response']);
} else {
    echo "Error: No response from chatbot.\n";
    print_r($data);
}
