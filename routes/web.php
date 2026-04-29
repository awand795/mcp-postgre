<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\AgenticChatbotController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

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
    Route::post('/chatbot/export/pdf', [AgenticChatbotController::class, 'exportPdf'])->name('chatbot.export.pdf');

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
        Route::post('/users/{user}/ai-config', [AdminController::class, 'updateAiConfig'])->name('users.ai_config.update');
        Route::post('/users/{user}/ai-models/{model}/toggle', [AdminController::class, 'toggleUserAiModel'])->name('users.ai_models.toggle');
        Route::post('/users/{user}/ai-keys/{key}/toggle', [AdminController::class, 'toggleUserAiKey'])->name('users.ai_keys.toggle');
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

        // Database Management
        Route::get('/databases', [AdminController::class, 'databases'])->name('databases');
        Route::post('/databases', [AdminController::class, 'databaseStore'])->name('databases.store');
        Route::put('/databases/{database}', [AdminController::class, 'databaseUpdate'])->name('databases.update');
        Route::delete('/databases/{database}', [AdminController::class, 'databaseDelete'])->name('databases.delete');
        Route::post('/databases/{database}/test', [AdminController::class, 'databaseTest'])->name('databases.test');
        Route::get('/databases/{database}/schemas', [AdminController::class, 'databaseSchemas'])->name('databases.schemas');
        Route::post('/databases/load-schemas', [AdminController::class, 'loadSchemasFromParams'])->name('databases.load-schemas');
        Route::get('/databases/test-all', [AdminController::class, 'testAllConnections'])->name('databases.test-all');
        Route::post('/cache/clear', [AdminController::class, 'clearCache'])->name('cache.clear');

        // AI Management
        Route::get('/ai-management', [App\Http\Controllers\Admin\AiController::class, 'index'])->name('ai_management');
        Route::post('/ai-management/keys', [App\Http\Controllers\Admin\AiController::class, 'storeKey'])->name('ai_management.store_key');
        Route::put('/ai-management/keys/{key}', [App\Http\Controllers\Admin\AiController::class, 'updateKey'])->name('ai_management.update_key');
        Route::delete('/ai-management/keys/{key}', [App\Http\Controllers\Admin\AiController::class, 'deleteKey'])->name('ai_management.delete_key');
        Route::post('/ai-management/keys/{key}/reset-limit', [App\Http\Controllers\Admin\AiController::class, 'resetLimit'])->name('ai_management.reset_limit');
        Route::post('/ai-management/keys/{key}/health-check', [App\Http\Controllers\Admin\AiController::class, 'healthCheck'])->name('ai_management.health_check');
        Route::post('/ai-management/models', [App\Http\Controllers\Admin\AiController::class, 'storeModel'])->name('ai_management.store_model');
        Route::delete('/ai-management/models/{model}', [App\Http\Controllers\Admin\AiController::class, 'deleteModel'])->name('ai_management.delete_model');
        Route::post('/ai-management/models/{model}/toggle', [App\Http\Controllers\Admin\AiController::class, 'toggleModel'])->name('ai_management.toggle_model');
        Route::post('/ai-management/providers/{provider}/toggle', [App\Http\Controllers\Admin\AiController::class, 'toggleProvider'])->name('ai_management.toggle_provider');
        Route::post('/ai-management/providers', [App\Http\Controllers\Admin\AiController::class, 'storeProvider'])->name('ai_management.store_provider');
        Route::delete('/ai-management/providers/{provider}', [App\Http\Controllers\Admin\AiController::class, 'deleteProvider'])->name('ai_management.delete_provider');
        Route::get('/ai-management/keys/status-poll', [App\Http\Controllers\Admin\AiController::class, 'pollKeyStatus'])->name('ai_management.poll_status');
    });
});

// MCP route sudah otomatis didaftarkan oleh McpServiceProvider dari php-mcp/laravel
// di prefix /mcp (default). Tidak perlu Mcp::web() lagi.
