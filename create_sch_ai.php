<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::statement("CREATE SCHEMA IF NOT EXISTS sch_ai");
    echo "Schema sch_ai created successfully in data_setting_ai!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
