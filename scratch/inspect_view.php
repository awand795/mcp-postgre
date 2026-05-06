<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;

$dbCode = 'data_mbi';
$conn = DatabaseConnection::where('database', $dbCode)->first();
$config = $conn->getConnectionConfig();
config(['database.connections.temp_inspect' => $config]);

$viewName = 'view_data_penjualan_rinci_mbi';
$res = DB::connection('temp_inspect')->select("SELECT view_definition FROM information_schema.views WHERE view_name = ?", [$viewName]);

if (!empty($res)) {
    echo "Definition for $viewName:\n";
    echo $res[0]->view_definition;
} else {
    echo "View $viewName not found in information_schema.views\n";
    
    // Try pg_views
    $res2 = DB::connection('temp_inspect')->select("SELECT definition FROM pg_views WHERE viewname = ?", [$viewName]);
    if (!empty($res2)) {
         echo "Definition from pg_views:\n";
         echo $res2[0]->definition;
    }
}
