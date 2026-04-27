<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\DB;

$db = DatabaseConnection::where('database', 'data_mbi')->first();
if (!$db) {
    echo "Database data_mbi not found\n";
    exit(1);
}

$config = $db->getConnectionConfig();
$config['host'] = '74.48.112.31'; // Overwrite with direct IP
config(['database.connections.temp_view' => $config]);

try {
    $res = DB::connection('temp_view')->select("SELECT DISTINCT nama_propinsi_cabang FROM sch_mbi.view_data_penjualan_rinci_mbi LIMIT 20");
    if (empty($res)) {
        echo "No provinces found\n";
    } else {
        foreach ($res as $row) {
            echo $row->nama_propinsi_cabang . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
