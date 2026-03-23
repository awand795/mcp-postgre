<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $res = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'sch_ai'");
    if (empty($res)) {
        echo "Skema 'sch_ai' belum ada di database data_setting_ai!\n";
    }
    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    echo "Tabel yang ada di dalam public: " . implode(', ', array_column($tables, 'table_name')) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
