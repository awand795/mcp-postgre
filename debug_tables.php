<?php

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = DatabaseConnection::first();
echo "Testing connection to: " . $db->name . "\n";

try {
    $config = $db->getConnectionConfig();
    config(["database.connections.debug_conn" => $config]);
    $conn = DB::connection('debug_conn');
    
    echo "Using query: " . $db->getAdapter()->listTablesQuery() . "\n";
    $tables = $conn->select($db->getAdapter()->listTablesQuery());
    
    echo "Found " . count($tables) . " tables/views.\n";
    foreach ($tables as $t) {
        echo "- {$t->table_schema}.{$t->table_name} ({$t->table_type})\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
