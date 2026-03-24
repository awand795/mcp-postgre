<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\AgenticChatbotController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MCPServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Routes
Route::middleware('auth')->group(function () {

    Route::get('/', function () {
        return redirect()->route('chatbot');
    });

    // ── CHATBOT ROUTES ──────────────────────────────────────────────────────

    // Mode lama (manual SQL planning) - tetap ada sebagai fallback
    Route::get('/chatbot', [AgenticChatbotController::class, 'index'])->name('chatbot');
    Route::post('/chatbot/send', [AgenticChatbotController::class, 'send'])->name('chatbot.send');

    // Mode agentic baru - AI query mandiri via tool calling
    // Endpoint terpisah agar bisa ditest paralel
    Route::get('/chatbot/agentic', [AgenticChatbotController::class, 'index'])->name('chatbot.agentic');
    Route::post('/chatbot/agentic/send', [AgenticChatbotController::class, 'send'])->name('chatbot.agentic.send');

    // ── ADMIN ROUTES ─────────────────────────────────────────────────────────
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');

        // User Management
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'userStore'])->name('users.store');
        Route::put('/users/{user}', [AdminController::class, 'userUpdate'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'userDelete'])->name('users.delete');

        // User Import/Export
        Route::get('/users/export', [AdminController::class, 'usersExport'])->name('users.export');
        Route::post('/users/import', [AdminController::class, 'usersImport'])->name('users.import');
        Route::get('/users/template', [AdminController::class, 'userTemplate'])->name('users.template');

        // Role Management
        Route::get('/roles', [AdminController::class, 'roles'])->name('roles');
        Route::post('/roles', [AdminController::class, 'roleStore'])->name('roles.store');
        Route::put('/roles/{role}', [AdminController::class, 'roleUpdate'])->name('roles.update');
        Route::delete('/roles/{role}', [AdminController::class, 'roleDelete'])->name('roles.delete');
        Route::post('/roles/{role}/permissions', [AdminController::class, 'updatePermissions'])->name('roles.permissions');
    });
});

// MCP Server endpoint (untuk koneksi klien eksternal seperti Claude Desktop)
Mcp::web('/mcp', MCPServer::class);
