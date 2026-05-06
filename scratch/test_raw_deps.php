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
// Use DB_MBI_HOST from .env if we are in local to bypass 127.0.0.1 issue
if ($config['host'] === '127.0.0.1') {
    $config['host'] = env('DB_MBI_HOST', '74.48.112.31');
}
config(['database.connections.temp_inspect' => $config]);

$schemaName = 'sch_mbi';
$viewName = 'view_data_penjualan_rinci_mbi';

$query = "
    SELECT DISTINCT
        referenced_schema.nspname AS table_schema,
        referenced_table.relname AS table_name
    FROM pg_rewrite
    JOIN pg_class view_table ON pg_rewrite.ev_class = view_table.oid
    JOIN pg_namespace view_schema ON view_table.relnamespace = view_schema.oid
    JOIN pg_depend ON pg_rewrite.oid = pg_depend.objid
    JOIN pg_class referenced_table ON pg_depend.refobjid = referenced_table.oid
    JOIN pg_namespace referenced_schema ON referenced_table.relnamespace = referenced_schema.oid
    WHERE view_schema.nspname = ? AND view_table.relname = ?
      AND referenced_table.relkind IN ('r', 'v', 'm')
      AND referenced_table.relname != ?
";

$results = DB::connection('temp_inspect')->select($query, [$schemaName, $viewName, $viewName]);

echo "Deep dependencies for $viewName:\n";
foreach ($results as $row) {
    echo "- {$row->table_schema}.{$row->table_name}\n";
}
