<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\Core\SchemaService;

$user = User::where('is_admin', true)->first();
if (!$user) {
    echo "No admin user found\n";
    exit(1);
}
Auth::login($user);

$schemaService = app(SchemaService::class);
echo $schemaService->getViewDefinition('data_mbi', 'sch_mbi', 'view_data_penjualan_rinci_mbi');
