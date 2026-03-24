<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ChatbotController;

$admin = User::where('is_admin', true)->first();
Auth::login($admin);

$controller = new ChatbotController();
$reflector = new ReflectionClass($controller);

$msg = "ada berapa cabang kita";
$getSchema = $reflector->getMethod('getSchemaContext'); $getSchema->setAccessible(true);
$schema = $getSchema->invoke($controller, $msg);

echo "--- Schema Context for: $msg ---\n";
echo $schema . "\n";

$apiKey = env('OPENROUTER_API_KEY') ?: env('NVIDIA_API_KEY');
echo "--- SQL Planning ---\n";
$plan = $reflector->getMethod('planSQLQueries'); $plan->setAccessible(true);
$queries = $plan->invoke($controller, $msg, $schema, $apiKey);

foreach ($queries as $label => $sql) {
    echo "Label: $label\nSQL: $sql\n";
}
