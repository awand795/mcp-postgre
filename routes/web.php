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

    // Register hanya aktif di local/testing env.
    // Di production, user dibuat melalui Admin Dashboard (/admin/users)
    if (app()->environment('local', 'testing')) {
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    } else {
        // Fallback agar route name 'register' tetap ada (dipakai di login.blade.php)
        Route::get('/register', fn() => redirect()->route('login'))->name('register');
        Route::post('/register', fn() => redirect()->route('login'))->name('register.store');
    }
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Protected Routes
Route::middleware('auth')->group(function () {

    Route::get('/', function () {
        return redirect()->route('chatbot');
    });

    // ── CHATBOT ROUTES (Agentic Tool Calling) ──────────────────────────────
    Route::get('/chatbot', [AgenticChatbotController::class, 'index'])->name('chatbot');
    Route::post('/chatbot/send', [AgenticChatbotController::class, 'send'])->name('chatbot.send');
    Route::post('/chatbot/export/excel', [AgenticChatbotController::class, 'exportExcel'])->name('chatbot.export.excel');

    // Alias agentic (backward compat — mengarah ke controller yang sama)
    Route::get('/chatbot/agentic', [AgenticChatbotController::class, 'index'])->name('chatbot.agentic');
    Route::post('/chatbot/agentic/send', [AgenticChatbotController::class, 'send'])->name('chatbot.agentic.send');

    // Chat History API Endpoints
    Route::get('/chatbot/sessions', [AgenticChatbotController::class, 'getSessions'])->name('chatbot.sessions');
    Route::get('/chatbot/sessions/{id}', [AgenticChatbotController::class, 'getSession'])->name('chatbot.sessions.show');
    Route::delete('/chatbot/sessions/{id}', [AgenticChatbotController::class, 'deleteSession'])->name('chatbot.sessions.destroy');
    Route::put('/chatbot/sessions/{id}', [AgenticChatbotController::class, 'updateSessionTitle'])->name('chatbot.sessions.update');

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
