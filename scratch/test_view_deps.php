<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;

$conn = DatabaseConnection::where('code', 'mbi_prod')->first();
$config = $conn->getConnectionConfig();
config(['database.connections.temp_rbac' => $config]);

$viewName = 'view_data_penjualan_rinci_mbi';
$results = DB::connection('temp_rbac')->select("
    SELECT table_schema, table_name 
    FROM information_schema.view_table_usage 
    WHERE view_name = ?
", [$viewName]);

echo "Underlying tables for $viewName:\n";
foreach ($results as $row) {
    echo "- {$row->table_schema}.{$row->table_name}\n";
}
