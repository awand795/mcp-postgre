<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Core\QueryService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Mock user 7
$user = User::find(7);
Auth::login($user);

$queryService = app(QueryService::class);

$db = 'data_mbi';
$schema = 'sch_mbi';
$view = 'view_data_penjualan_rinci_mbi';

// We need to access protected method for testing
$reflection = new \ReflectionClass($queryService);
$method = $reflection->getMethod('getUnderlyingTables');
$method->setAccessible(true);

echo "Checking dependencies for $view...\n";
$tables = $method->invoke($queryService, $db, $schema, $view);

print_r($tables);

// Also check if isTableAllowed works for 'cabang'
$allowedDbs = $queryService->getAllowedTables();
echo "\nIs 'cabang' allowed for user 7?\n";
$isAllowed = $reflection->getMethod('isTableAllowed')->invoke($queryService, $db, $schema, 'cabang', $allowedDbs);
echo $isAllowed ? "YES (Still leaked!)\n" : "NO (Blocked correctly)\n";

echo "\nIs '$view' allowed deep-check?\n";
$blocked = false;
foreach ($tables as $u) {
    if (!$reflection->getMethod('isTableAllowed')->invoke($queryService, $db, $u['schema'], $u['table'], $allowedDbs)) {
        echo "- Table '{$u['table']}' is NOT allowed. Blocked!\n";
        $blocked = true;
    } else {
        echo "- Table '{$u['table']}' is allowed.\n";
    }
}

if ($blocked) {
    echo "RESULT: View $view SHOULD BE BLOCKED.\n";
} else {
    echo "RESULT: View $view IS ALLOWED (Check permissions or dependency detection).\n";
}
