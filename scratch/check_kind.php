<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;

$connModel = DatabaseConnection::where('database', 'data_mbi')->first();
$tempConn = 'temp_inspect_kind';
config(["database.connections.{$tempConn}" => $connModel->getConnectionConfig()]);

$view = 'view_master_pelanggan_mbi';
$schema = 'sch_mbi';

$results = DB::connection($tempConn)->select("
    SELECT n.nspname, c.relname, c.relkind, 
           CASE c.relkind 
             WHEN 'r' THEN 'table' 
             WHEN 'v' THEN 'view' 
             WHEN 'm' THEN 'materialized view' 
             WHEN 'f' THEN 'foreign table' 
             ELSE c.relkind::text 
           END as type
    FROM pg_class c 
    JOIN pg_namespace n ON n.oid = c.relnamespace 
    WHERE c.relname = ?
", [$view]);

echo "Identity check for $view:\n";
print_r($results);
