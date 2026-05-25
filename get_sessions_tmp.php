<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sessionIds = [171, 718];

foreach ($sessionIds as $sid) {
    echo "=== Session {$sid} ===\n";
    $messages = DB::table('chat_messages')
        ->where('chat_session_id', $sid)
        ->orderBy('id', 'asc')
        ->get();
        
    foreach ($messages as $m) {
        echo "Role: {$m->role}\n";
        echo "Content: " . substr($m->content, 0, 500) . "\n";
        if ($m->tool_results) {
            $tr = json_decode($m->tool_results, true);
            foreach ($tr as $res) {
                echo "  Tool: {$res['tool_name']}\n";
                if ($res['tool_name'] === 'execute_query') {
                    echo "    SQL: " . ($res['data']['sql'] ?? $res['data']['query'] ?? json_encode($res['data'])) . "\n";
                }
            }
        }
        echo "----------------------------------------\n";
    }
}
