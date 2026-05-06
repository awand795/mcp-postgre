<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;

$conn = DatabaseConnection::where('database', 'data_mbi')->first();
config(['database.connections.temp' => $conn->getConnectionConfig()]);

$results = DB::connection('temp')->select("
    SELECT n.nspname, c.relname, c.relkind 
    FROM pg_class c 
    JOIN pg_namespace n ON n.oid = c.relnamespace 
    WHERE c.relname = 'view_data_penjualan_rinci_mbi'
");

print_r($results);
