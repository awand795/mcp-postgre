<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $tables = DB::connection('pgsql_mbi')->select("SELECT table_schema, table_name FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog', 'information_schema')");
    
    $schema = [];
    foreach ($tables as $t) {
        $cols = DB::connection('pgsql_mbi')->select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ? AND table_schema = ?", [$t->table_name, $t->table_schema]);
        $schema[$t->table_schema . '.' . $t->table_name] = $cols;
    }
    
    file_put_contents('schema_output.json', json_encode(['tables_found' => count($schema), 'schema' => $schema], JSON_PRETTY_PRINT));
    echo "Saved to schema_output.json";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
