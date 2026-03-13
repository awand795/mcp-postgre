<?php
// Script sementara untuk dump schema database
// Jalankan: php check_schema.php
// Lalu hapus file ini setelah selesai

$host   = '127.0.0.1';
$port   = '5432';
$dbname = 'db_penjualan';
$user   = 'postgres';
$pass   = 'adnawaa';

try {
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$dbname}", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil semua tabel
    $tables = $pdo->query("
        SELECT table_name FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "\n=== TABEL: {$table} ===\n";
        $cols = $pdo->prepare("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = 'public'
            ORDER BY ordinal_position
        ");
        $cols->execute([$table]);
        foreach ($cols->fetchAll() as $col) {
            $nullable = $col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
            echo "  - {$col['column_name']} ({$col['data_type']}) {$nullable}\n";
        }

        // Sample 2 baris
        try {
            $sample = $pdo->query("SELECT * FROM \"{$table}\" LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
            if ($sample) {
                echo "  SAMPLE:\n";
                foreach ($sample as $row) {
                    echo "    " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "  (sample error: {$e->getMessage()})\n";
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
