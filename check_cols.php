<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$columns = DB::connection('pgsql_mbi')->select("
    SELECT column_name 
    FROM information_schema.columns 
    WHERE table_name = 'view_master_cabang_mbi' 
    AND table_schema = 'sch_mbi'
    ORDER BY ordinal_position
");

$out = "";
foreach ($columns as $col) {
    $out .= $col->column_name . "\n";
}
file_put_contents(__DIR__ . '/cols_output.txt', $out);
echo "Done\n";
