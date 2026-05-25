<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$lastMessage = DB::table('chat_messages')
    ->orderBy('id', 'desc')
    ->first();

if ($lastMessage) {
    echo "=== Message Details ===\n";
    echo "ID: " . $lastMessage->id . "\n";
    echo "Role: " . $lastMessage->role . "\n";
    echo "Content:\n" . $lastMessage->content . "\n";
    echo "Tool Results:\n" . substr($lastMessage->tool_results, 0, 1000) . "...\n";
    
    // Print previous user message to see what they typed
    $userMsg = DB::table('chat_messages')
        ->where('chat_session_id', $lastMessage->chat_session_id)
        ->where('role', 'user')
        ->orderBy('id', 'desc')
        ->first();
    if ($userMsg) {
        echo "=== User Message ===\n";
        echo "Content: " . $userMsg->content . "\n";
    }
} else {
    echo "No messages found.\n";
}
