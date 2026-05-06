<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;

$connModel = DatabaseConnection::where('database', 'data_mbi')->first();

// Use the Service to get connection
$service = app(\App\Services\Core\QueryService::class);
$tempConn = 'temp_rbac_check_debug';
config(["database.connections.{$tempConn}" => $connModel->getConnectionConfig()]);

$view = 'view_data_penjualan_rinci_mbi';
$schema = 'sch_mbi';

$results = DB::connection($tempConn)->select("
    SELECT 
        ev_class::regclass as view_name,
        refobjid::regclass as referenced_name
    FROM pg_rewrite
    JOIN pg_depend ON pg_rewrite.oid = pg_depend.objid
    WHERE ev_class = ?::regclass
", ["{$schema}.{$view}"]);

echo "Dependencies for {$schema}.{$view}:\n";
foreach ($results as $row) {
    echo "- {$row->referenced_name}\n";
}
