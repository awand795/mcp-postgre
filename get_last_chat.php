<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$lastMessage = DB::table('chat_messages')
    ->orderBy('id', 'desc')
    ->first();

if ($lastMessage) {
    echo "ID: " . $lastMessage->id . "\n";
    echo "Role: " . $lastMessage->role . "\n";
    echo "Content:\n" . $lastMessage->content . "\n\n";
    echo "Tool Results:\n" . $lastMessage->tool_results . "\n\n";
} else {
    echo "No messages found.\n";
}
