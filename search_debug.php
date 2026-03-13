<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$output = "";
$docs = DB::table('documentation')->whereIn('id', [41, 68])->get();
foreach ($docs as $doc) {
    $output .= "ID: {$doc->id} | Title: {$doc->title}\n";
    $output .= "Link: {$doc->url}\n";
    $output .= "--- CONTENT ---\n";
    $output .= $doc->content . "\n\n====================================\n\n";
}

file_put_contents('debug_output.txt', $output);
echo "Done. Saved to debug_output.txt";
