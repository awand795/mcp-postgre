<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\MCPServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/', function () {
    return view('welcome');
});

Mcp::web('/mcp', MCPServer::class);

Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot');
Route::post('/chatbot/send', [ChatbotController::class, 'send'])->name('chatbot.send');
