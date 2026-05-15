<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\DatabaseConnection;
use App\Imports\UserImport;
use App\Exports\UsersExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        $stats = [
            'users_count' => User::count(),
            'roles_count' => Role::count(),
            'databases_count' => DatabaseConnection::active()->count(),
            'tables_count' => count($this->getAllTables()),
        ];
        return view('admin.dashboard', compact('stats'));
    }

    public function users(Request $request)
    {
        $query = User::with(['roleModel', 'aiModels', 'aiKeys', 'addedBy', 'tableFilters']);
        
        // Visibility filter: Admins see only users they added, Super Admins see all
        if (!auth()->user()->is_super_admin) {
            $query->where('added_by', auth()->id());
        }
        
        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filter by role
        if ($request->filled('role_filter')) {
            $query->where('role', $request->role_filter);
        }
        
        $users = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        
        // For copy filter modal, we need all users the admin can see (unpaginated)
        $allUsersQuery = User::orderBy('name');
        if (!auth()->user()->is_super_admin) {
            $allUsersQuery->where('added_by', auth()->id());
        }
        $allUsers = $allUsersQuery->get();

        $roles = Role::with('addedBy')->get();
        $aiModels = \App\Models\AiModel::with('provider')->where('is_active', true)->get();
        $aiKeysQuery = \App\Models\AiApiKey::with(['provider', 'addedBy'])->where('is_active', true);
        if (!auth()->user()->is_super_admin) {
            $aiKeysQuery->where('added_by', auth()->id());
        }
        $aiKeys = $aiKeysQuery->get();
        
        return view('admin.users', compact('users', 'roles', 'aiModels', 'aiKeys', 'allUsers'));
    }

    public function userStore(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required',
        ]);

        if ($request->has('is_admin') && $request->has('is_super_admin')) {
            return back()->withErrors(['role' => 'Tidak bisa memilih Admin dan Super Admin sekaligus.'])->withInput();
        }

        $isAdmin = $request->has('is_admin');
        $isSuperAdmin = $request->has('is_super_admin');

        // Only Super Admin can grant Admin or Super Admin
        if (!auth()->user()->is_super_admin && ($isAdmin || $isSuperAdmin)) {
            $isAdmin = false;
            $isSuperAdmin = false;
        }

        // Validate AI Config if provided
        if ($request->has('ai_models') || $request->has('ai_keys')) {
            $this->validateAiConfig(
                $request->input('ai_models', []),
                $request->input('ai_keys', [])
            );
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_admin' => $isAdmin,
            'is_super_admin' => $isSuperAdmin,
            'added_by' => auth()->id(),
            'max_tokens' => $request->input('max_tokens', 32768),
            'analysis_scope_limited' => $request->has('analysis_scope_limited'),
        ]);

        if ($request->has('ai_models')) {
            $user->aiModels()->sync($request->ai_models);
        }
        if ($request->has('ai_keys')) {
            $user->aiKeys()->sync($request->ai_keys);
        }

        $redirect = $request->filled('redirect_url') ? redirect($request->redirect_url) : back();
        return $redirect->with('success', 'User berhasil ditambahkan.');
    }

    public function userUpdate(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required',
        ]);

        if ($request->has('is_admin') && $request->has('is_super_admin')) {
            return back()->withErrors(['role' => 'Tidak bisa memilih Admin dan Super Admin sekaligus.'])->withInput();
        }

        $isAdmin = $request->has('is_admin');
        $isSuperAdmin = $request->has('is_super_admin');

        // Only Super Admin can change Admin or Super Admin status
        if (!auth()->user()->is_super_admin) {
            $isAdmin = $user->is_admin;
            $isSuperAdmin = $user->is_super_admin;
        }

        // Validate AI Config if provided
        if ($request->has('ai_models') || $request->has('ai_keys')) {
            $this->validateAiConfig(
                $request->input('ai_models', []),
                $request->input('ai_keys', [])
            );
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'is_admin' => $isAdmin,
            'is_super_admin' => $isSuperAdmin,
            'max_tokens' => $request->input('max_tokens', 32768),
            'analysis_scope_limited' => $request->has('analysis_scope_limited'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Jika role user berubah, clear cache allowed tables user lama & baru
        if ($user->role != $request->role) {
            // FIX: clear semua varian cache key (v1 dan v2) untuk role lama & baru
            cache()->forget("agentic_allowed_dbs_role_v2_{$user->role}");
            cache()->forget("agentic_allowed_dbs_role_v2_{$request->role}");
            cache()->forget("agentic_allowed_dbs_role_{$user->role}");
            cache()->forget("agentic_allowed_dbs_role_{$request->role}");
            // FIX: invalidasi juga cache schema_info user ini (SchemaService men-cache per user)
            cache()->forget('schema_info_' . md5("{$user->id}_"));
            cache()->forget('schema_info_' . md5("{$user->id}_1"));
        }

        $user->update($data);

        if ($request->has('ai_models')) {
            $user->aiModels()->sync($request->ai_models);
        }
        if ($request->has('ai_keys')) {
            $user->aiKeys()->sync($request->ai_keys);
        }

        $redirect = $request->filled('redirect_url') ? redirect($request->redirect_url) : back();
        return $redirect->with('success', 'User berhasil diperbarui.');
    }

    public function toggleUserAiModel(User $user, $modelId)
    {
        $pivot = DB::table('user_ai_models')
            ->where('user_id', $user->id)
            ->where('model_id', $modelId)
            ->first();
        
        if ($pivot) {
            DB::table('user_ai_models')
                ->where('user_id', $user->id)
                ->where('model_id', $modelId)
                ->update(['is_enabled' => !$pivot->is_enabled]);
        }
        
        return response()->json(['success' => true]);
    }

    public function toggleUserAiKey(User $user, $keyId)
    {
        $pivot = DB::table('user_ai_keys')
            ->where('user_id', $user->id)
            ->where('api_key_id', $keyId)
            ->first();
        
        if ($pivot) {
            DB::table('user_ai_keys')
                ->where('user_id', $user->id)
                ->where('api_key_id', $keyId)
                ->update(['is_enabled' => !$pivot->is_enabled]);
        }
        
        return response()->json(['success' => true]);
    }

    public function updateAiConfig(Request $request, User $user)
    {
        $selectedModelIds = $request->input('ai_models', []);
        $selectedKeyIds = $request->input('ai_keys', []);

        try {
            $this->validateAiConfig($selectedModelIds, $selectedKeyIds);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first()
            ], 422);
        }

        // Sync models with is_enabled = true
        $models = [];
        foreach ($selectedModelIds as $id) {
            $models[$id] = ['is_enabled' => true];
        }
        $user->aiModels()->sync($models);

        // Sync keys with is_enabled = true
        $keys = [];
        foreach ($selectedKeyIds as $id) {
            $keys[$id] = ['is_enabled' => true];
        }
        $user->aiKeys()->sync($keys);

        return response()->json(['success' => true]);
    }

    /**
     * Validate that for every selected model, there is a corresponding API key from the same provider.
     */
    private function validateAiConfig(array $modelIds, array $keyIds)
    {
        // Admin (non-super) only allowed to assign keys they added
        if (!auth()->user()->is_super_admin && !empty($keyIds)) {
            $ownedCount = \App\Models\AiApiKey::whereIn('id', $keyIds)
                ->where('added_by', auth()->id())
                ->count();
            
            if ($ownedCount < count($keyIds)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ai_config' => ["Anda hanya dapat memberikan akses API Key yang Anda tambahkan sendiri."]
                ]);
            }
        }

        if (empty($modelIds)) return;

        $models = \App\Models\AiModel::with('provider')->whereIn('id', $modelIds)->get();
        $keys = \App\Models\AiApiKey::whereIn('id', $keyIds)->get();

        $keyProviderIds = $keys->pluck('provider_id')->unique();
        
        foreach ($models as $model) {
            if (!$keyProviderIds->contains($model->provider_id)) {
                $providerName = $model->provider->name ?? 'Unknown';
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ai_config' => ["Anda memilih model '{$model->display_name}' ({$providerName}), tetapi belum memilih API Key untuk provider tersebut."]
                ]);
            }
        }
    }

    public function userDelete(User $user)
    {
        $user->delete();
        return back()->with('success', 'User berhasil dihapus.');
    }

    public function usersExport()
    {
        return Excel::download(new UsersExport, 'users-' . date('Y-m-d') . '.xlsx');
    }

    public function usersImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            Excel::import(new UserImport, $request->file('file'));
            return back()->with('success', 'User berhasil diimport.');
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $row = $failure->row();
                foreach ($failure->errors() as $error) {
                    $errors[] = "Baris {$row}: {$error}";
                }
            }
            return back()->withErrors(['file' => $errors])->withInput();
        } catch (\Exception $e) {
            return back()->withErrors(['file' => 'Error: ' . $e->getMessage()])->withInput();
        }
    }

    public function userTemplate()
    {
        return Excel::download(new UsersExport(null, true), 'user-template.xlsx');
    }

    public function roles()
    {
        // Paksa bersihkan cache agar tabel database yang baru ditambah langsung muncul
        $this->clearTableCache();
        
        $user = auth()->user();
        
        $rolesQuery = Role::with(['permissions', 'addedBy']);
        $databasesQuery = DatabaseConnection::active();
        
        if (!$user->is_super_admin) {
            $rolesQuery->where('added_by', $user->id);
            $databasesQuery->where('added_by', $user->id);
        }
        
        $roles = $rolesQuery->get();
        $databases = $databasesQuery->get();
        
        $allowedDbCodes = $databases->pluck('database')->toArray();
        $allTables = $this->getAllTables();
        
        // Filter tables to only those from allowed databases
        if (!$user->is_super_admin) {
            $allTables = array_values(array_filter($allTables, function($table) use ($allowedDbCodes) {
                return in_array($table['database_code'], $allowedDbCodes);
            }));
        }

        // Temporary debug
        // Detailed debug logging
        $dbCounts = [];
        foreach ($databases as $db) {
            $dbTables = array_filter($allTables, fn($t) => $t['database_code'] === $db->database);
            $dbCounts[$db->database] = count($dbTables);
        }

        \Log::info('Role Management Debug', [
            'total_tables_count' => count($allTables),
            'counts_by_db' => $dbCounts,
            'sample_tables' => array_slice($allTables, 0, 5),
            'databases_count' => $databases->count(),
        ]);

        return view('admin.roles', compact('roles', 'allTables', 'databases'));
    }

    public function roleStore(Request $request)
    {
        $request->validate(['name' => 'required|unique:roles']);
        $data = $request->only('name', 'description');
        $data['added_by'] = auth()->id();
        Role::create($data);
        return back()->with('success', 'Role berhasil ditambahkan.');
    }

    public function roleUpdate(Request $request, Role $role)
    {
        $request->validate(['name' => 'required|unique:roles,name,' . $role->id]);
        $role->update($request->only('name', 'description'));
        return back()->with('success', 'Role berhasil diperbarui.');
    }

    public function roleDelete(Role $role)
    {
        $role->delete();
        return response()->json(['success' => true, 'message' => 'Role berhasil dihapus.']);
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $tables = $request->input('tables', []);

        // New format: each table is "database_code|schema_name|table_name"
        RolePermission::where('role_id', $role->id)->delete();

        foreach ($tables as $tableStr) {
            $parts = explode('|', $tableStr);
            
            if (count($parts) === 3) {
                // New multi-database format
                RolePermission::create([
                    'role_id' => $role->id,
                    'database_code' => $parts[0],
                    'schema_name' => $parts[1],
                    'table_name' => $parts[2],
                ]);
            } else {
                // Legacy format - use defaults from first active database
                $defaultDb = DatabaseConnection::active()->first();
                if ($defaultDb) {
                    $defaultDbCode = $defaultDb->database; // use 'database' field, consistent with role_permissions.database_code
                    $defaultSchema = $defaultDb->schema ?? match ($defaultDb->driver) {
                        'pgsql' => 'public',
                        'sqlsrv' => 'dbo',
                        'mysql', 'mariadb' => $defaultDb->database,
                        'sqlite' => 'main',
                        default => 'public',
                    };
                } else {
                    $defaultDbCode = config('database.default', 'pgsql');
                    $defaultSchema = 'public';
                }

                RolePermission::create([
                    'role_id' => $role->id,
                    'database_code' => $defaultDbCode,
                    'schema_name' => $defaultSchema,
                    'table_name' => $tableStr,
                ]);
            }
        }

        // FIX: Gunakan clearTableCache() agar semua key (termasuk v2) dihapus secara konsisten
        $this->clearTableCache();

        // FIX: Invalidasi cache schema_info per user untuk semua user dengan role ini
        // (SchemaService::getSchemaInfo() men-cache daftar tabel per user selama 10 menit)
        User::where('role', $role->id)->each(function ($u) {
            cache()->forget('schema_info_' . md5("{$u->id}_"));
            cache()->forget('schema_info_' . md5("{$u->id}_1"));
        });

        return response()->json(['success' => true]);
    }

    // ── Database Management ──────────────────────────────────────────────────────

    public function databases()
    {
        $query = DatabaseConnection::with('addedBy')->orderBy('is_default', 'desc')->orderBy('name');
        
        if (!auth()->user()->is_super_admin) {
            $query->where('added_by', auth()->id());
        }
        
        $databases = $query->get();
        return view('admin.databases', compact('databases'));
    }

    public function databaseStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|unique:database_connections',
            'code' => 'required|unique:database_connections|alpha_dash',
            'driver' => 'required|in:pgsql,mysql,mariadb,sqlsrv,sqlite',
            'host' => 'required_if:driver,pgsql,mysql,mariadb,sqlsrv|nullable',
            'port' => 'required|integer',
            'database' => 'required',
            'username' => 'nullable',
            'password' => 'required_unless:driver,sqlite',
            'schema' => 'nullable',
            'ssl_mode' => 'nullable|in:,prefer,require,verify-ca,verify-full',
            'connection_timeout' => 'nullable|integer|min:5|max:300',
            'description' => 'nullable',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['is_default'] = $request->has('is_default');

        // Set defaults based on driver
        $driver = $validated['driver'];
        if (empty($validated['schema'])) {
            $validated['schema'] = match ($driver) {
                'pgsql' => 'public',
                'sqlsrv' => 'dbo',
                'mysql', 'mariadb' => $validated['database'], // MySQL uses DB name as schema
                'sqlite' => 'main',
                default => 'public',
            };
        }

        if (empty($validated['connection_timeout'])) {
            $validated['connection_timeout'] = 30;
        }

        // If setting as default, unset others
        if ($validated['is_default']) {
            DatabaseConnection::where('is_default', true)->update(['is_default' => false]);
        }

        $validated['added_by'] = auth()->id();

        $db = DatabaseConnection::create($validated);

        // Auto test connection
        $testResult = $db->testConnection();

        // Clear table cache so new database tables appear in role management
        $this->clearTableCache();

        return back()->with($testResult['success'] ? 'success' : 'warning',
            $testResult['success']
                ? 'Database berhasil ditambahkan dan terhubung.'
                : 'Database berhasil ditambahkan, tetapi koneksi gagal: ' . ($testResult['error'] ?? 'Unknown error')
        );
    }

    public function databaseUpdate(Request $request, DatabaseConnection $database)
    {
        $validated = $request->validate([
            'name' => 'required|unique:database_connections,name,' . $database->id,
            'driver' => 'required|in:pgsql,mysql,mariadb,sqlsrv,sqlite',
            'host' => 'required_if:driver,pgsql,mysql,mariadb,sqlsrv|nullable',
            'port' => 'required|integer',
            'database' => 'required',
            'username' => 'nullable',
            'password' => 'nullable',
            'schema' => 'nullable',
            'ssl_mode' => 'nullable|in:,prefer,require,verify-ca,verify-full',
            'connection_timeout' => 'nullable|integer|min:5|max:300',
            'description' => 'nullable',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['is_default'] = $request->has('is_default');

        // Set defaults based on driver if schema is empty
        if (empty($validated['schema'])) {
            $driver = $validated['driver'] ?? $database->driver;
            $validated['schema'] = match ($driver) {
                'pgsql' => 'public',
                'sqlsrv' => 'dbo',
                'mysql', 'mariadb' => $validated['database'] ?? $database->database,
                'sqlite' => 'main',
                default => 'public',
            };
        }

        // If setting as default, unset others
        if ($validated['is_default']) {
            DatabaseConnection::where('is_default', true)->update(['is_default' => false]);
        }

        // Only update password if provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $database->update($validated);

        // Clear table cache in case connection params changed
        $this->clearTableCache();

        return back()->with('success', 'Database berhasil diperbarui.');
    }

    public function databaseDelete(DatabaseConnection $database)
    {
        // Prevent deleting default database
        if ($database->is_default) {
            return back()->withErrors(['error' => 'Tidak bisa menghapus database default.']);
        }

        $database->delete();

        // Clear table cache
        $this->clearTableCache();

        return back()->with('success', 'Database berhasil dihapus.');
    }

    public function databaseTest(DatabaseConnection $database)
    {
        $result = $database->testConnection();

        return response()->json($result);
    }

    /**
     * Test all database connections and return health status
     */
    public function testAllConnections()
    {
        $databases = DatabaseConnection::all();
        $results = [];

        foreach ($databases as $db) {
            $startTime = microtime(true);
            $testResult = $db->testConnection();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $results[] = [
                'id' => $db->id,
                'name' => $db->name,
                'code' => $db->code,
                'driver' => $db->driver,
                'host' => $db->host,
                'database' => $db->database,
                'success' => $testResult['success'],
                'version' => $testResult['version'] ?? null,
                'error' => $testResult['error'] ?? null,
                'response_time_ms' => $responseTime,
                'last_tested_at' => $db->last_tested_at?->toISOString(),
                'test_status' => $db->test_status,
            ];
        }

        return response()->json([
            'total' => count($results),
            'healthy' => count(array_filter($results, fn($r) => $r['success'])),
            'unhealthy' => count(array_filter($results, fn($r) => !$r['success'])),
            'databases' => $results,
        ]);
    }

    public function databaseSchemas(DatabaseConnection $database)
    {
        $schemas = $database->getSchemas();
        return response()->json(['schemas' => $schemas]);
    }

    /**
     * Load schemas from connection params (without saving database first)
     */
    public function loadSchemasFromParams(Request $request)
    {
        try {
            $validated = $request->validate([
                'driver' => 'required|in:pgsql,mysql,mariadb,sqlsrv,sqlite',
                'host' => 'required_if:driver,pgsql,mysql,mariadb,sqlsrv|nullable',
                'port' => 'required|integer',
                'database' => 'required',
                'username' => 'required_if:driver,pgsql,mysql,mariadb,sqlsrv|nullable',
                'password' => 'required_if:driver,pgsql,mysql,mariadb,sqlsrv|nullable',
                'schema' => 'nullable',
                'ssl_mode' => 'nullable|in:,prefer,require,verify-ca,verify-full',
                'connection_timeout' => 'nullable|integer|min:5|max:300',
                'test_only' => 'nullable', // allow boolean or string
            ]);

            // Create temporary model instance
            $tempDb = new DatabaseConnection([
                'driver' => $validated['driver'],
                'host' => $validated['host'],
                'port' => $validated['port'],
                'database' => $validated['database'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'schema' => $validated['schema'] ?? 'public',
                'ssl_mode' => $validated['ssl_mode'] ?? '',
                'connection_timeout' => $validated['connection_timeout'] ?? 30,
            ]);

            if ($request->boolean('test_only')) {
                $result = $tempDb->testConnection();
                return response()->json($result);
            }

            $schemas = $tempDb->getSchemas();
            return response()->json(['schemas' => $schemas]);
        } catch (\Exception $e) {
            \Log::error("Error in loadSchemasFromParams: " . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->except(['password'])
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Internal Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ── Helper Methods ──────────────────────────────────────────────────────────

    /**
     * Get all tables from all active database connections
     * Returns array of: { database_code, schema_name, table_name, description }
     */
    private function getAllTables()
    {
        return cache()->remember('all_db_tables_admin', 600, function() {
            $activeDatabases = DatabaseConnection::active()->get();
            $allTables = [];

            foreach ($activeDatabases as $db) {
                try {
                    $tables = $db->getTables();
                    
                    foreach ($tables as $table) {
                        $allTables[] = [
                            // Gunakan nama database asli sebagai identifier (konsisten dengan role_permissions.database_code)
                            'database_code' => $db->database,
                            'database_name' => $db->database,
                            'schema_name'   => $table['schema_name'],
                            'table_name'    => $table['table_name'],
                            'description'   => $table['description'] ?? '',
                            'table_type'    => $table['table_type'] ?? 'table',
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to load tables for DB {$db->name}: " . $e->getMessage());
                }
            }

            // Sort by database_name, schema_name, table_name
            usort($allTables, function($a, $b) {
                $cmp = strcmp($a['database_code'], $b['database_code']);
                if ($cmp !== 0) return $cmp;
                $cmp = strcmp($a['schema_name'], $b['schema_name']);
                if ($cmp !== 0) return $cmp;
                return strcmp($a['table_name'], $b['table_name']);
            });

            return $allTables;
        });
    }

    /**
     * Clear all database related caches
     */
    public function clearCache()
    {
        $this->clearTableCache();
        return back()->with('success', 'Cache berhasil dibersihkan.');
    }

    // ── MCP TOKEN MANAGEMENT ─────────────────────────────────────────────────

    /**
     * Generate MCP API Token untuk user tertentu.
     * Token plaintext hanya ditampilkan SEKALI — simpan baik-baik.
     * Di database hanya disimpan hash SHA-256-nya.
     */
    public function generateMcpToken(User $user)
    {
        $plainToken = bin2hex(random_bytes(32)); // 64 karakter hex
        $hashed     = hash('sha256', $plainToken);

        $user->update(['mcp_api_token' => $hashed]);

        // Invalidate cache token lama jika ada
        \App\Mcp\Auth\McpTokenGuard::invalidateToken($plainToken);

        return response()->json([
            'success'         => true,
            'message'         => 'Token MCP berhasil dibuat. Salin sekarang — tidak akan ditampilkan lagi.',
            'mcp_api_token'   => $plainToken,
            'user_id'         => $user->id,
            'user_email'      => $user->email,
            'mcp_server_url'  => url('/mcp'),
            'usage_example'   => 'Authorization: Bearer ' . $plainToken,
        ]);
    }

    /**
     * Revoke MCP API Token untuk user tertentu.
     */
    public function revokeMcpToken(User $user)
    {
        $user->update(['mcp_api_token' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Token MCP user ' . $user->email . ' berhasil di-revoke.',
        ]);
    }

    /**
     * Get allowed tables and current filters for a user.
     */
    public function getTableFilters(User $user)
    {
        if (!auth()->user()->is_super_admin && $user->added_by !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $role = $user->roleModel;
        if (!$role) return response()->json(['tables' => [], 'filter_count' => 0]);

        $permissions = \App\Models\RolePermission::with('databaseConnection')
            ->where('role_id', $role->id)
            ->get();

        $allowedTables = [];
        $filterCount = 0;
        foreach ($permissions as $p) {
            $conn = $p->databaseConnection;
            if (!$conn) continue;

            if (!auth()->user()->is_super_admin && $conn->added_by !== auth()->id()) {
                continue;
            }

            $existingFilter = \App\Models\UserTableFilter::where('user_id', $user->id)
                ->where('database_connection_id', $conn->id)
                ->where('table_name', $p->table_name)
                ->first();

            $rules = [];
            if ($existingFilter && $existingFilter->filter_condition) {
                $decoded = json_decode($existingFilter->filter_condition, true);
                $rules = is_array($decoded) ? $decoded : [];
            }

            if (!empty($rules)) $filterCount++;

            $allowedTables[] = [
                'db_id' => $conn->id,
                'db_name' => $conn->name,
                'db_code' => $conn->database,
                'table_name' => $p->table_name,
                'schema' => $p->schema_name,
                'rules' => $rules
            ];
        }

        return response()->json(['tables' => $allowedTables, 'filter_count' => $filterCount]);
    }

    /**
     * Get columns for a specific table (for dropdown in rule builder).
     */
    public function getTableColumns(Request $request)
    {
        $dbId = $request->input('db_id');
        $tableName = $request->input('table_name');
        $schema = $request->input('schema', 'public');

        $conn = DatabaseConnection::find($dbId);
        if (!$conn) return response()->json(['columns' => []]);

        if (!auth()->user()->is_super_admin && $conn->added_by !== auth()->id()) {
            return response()->json(['columns' => []], 403);
        }

        $tempConn = 'temp_cols_' . $conn->code;
        try {
            DB::purge($tempConn);
            config(["database.connections.{$tempConn}" => $conn->getConnectionConfig()]);

            $driver = $conn->driver;
            if ($driver === 'pgsql') {
                $columns = DB::connection($tempConn)->select(
                    "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position",
                    [$schema, $tableName]
                );
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                $columns = DB::connection($tempConn)->select(
                    "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position",
                    [$conn->database, $tableName]
                );
            } else {
                $columns = DB::connection($tempConn)->select(
                    "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position",
                    [$tableName]
                );
            }

            DB::purge($tempConn);

            return response()->json([
                'columns' => array_map(fn($c) => [
                    'name' => $c->column_name,
                    'type' => $c->data_type
                ], $columns)
            ]);
        } catch (\Exception $e) {
            DB::purge($tempConn);
            return response()->json(['columns' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Preview data with applied filter rules.
     */
    public function previewTableFilter(Request $request)
    {
        $dbId = $request->input('db_id');
        $tableName = $request->input('table_name');
        $schema = $request->input('schema', 'public');
        $rules = $request->input('rules', []);

        $conn = DatabaseConnection::find($dbId);
        if (!$conn) return response()->json(['error' => 'Database not found'], 404);

        if (!auth()->user()->is_super_admin && $conn->added_by !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $whereClause = $this->buildWhereFromRules($rules);
        $driver = $conn->driver;

        // Build table reference per driver
        if ($driver === 'pgsql') {
            $fullTable = "\"{$schema}\".\"{$tableName}\"";
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $fullTable = "`{$tableName}`";
        } elseif ($driver === 'sqlsrv') {
            $fullTable = "[{$schema}].[{$tableName}]";
        } else {
            $fullTable = "\"{$tableName}\"";
        }

        // Build SELECT with driver-specific LIMIT
        if ($driver === 'sqlsrv') {
            $sql = "SELECT TOP 5 * FROM {$fullTable}";
        } else {
            $sql = "SELECT * FROM {$fullTable}";
        }
        if ($whereClause) {
            $sql .= " WHERE {$whereClause}";
        }
        if ($driver !== 'sqlsrv') {
            $sql .= " LIMIT 5";
        }

        $tempConn = 'temp_preview_' . $conn->code;
        try {
            DB::purge($tempConn);
            config(["database.connections.{$tempConn}" => $conn->getConnectionConfig()]);
            $rows = DB::connection($tempConn)->select($sql);
            DB::purge($tempConn);

            $totalSql = "SELECT COUNT(*) as total FROM {$fullTable}";
            DB::purge($tempConn);
            config(["database.connections.{$tempConn}" => $conn->getConnectionConfig()]);
            if ($whereClause) {
                $totalSql .= " WHERE {$whereClause}";
            }
            $totalResult = DB::connection($tempConn)->select($totalSql);
            $total = $totalResult[0]->total ?? 0;
            DB::purge($tempConn);

            return response()->json([
                'success' => true,
                'total' => $total,
                'rows' => array_map(fn($r) => (array) $r, array_slice($rows, 0, 5)),
                'sql_preview' => "WHERE {$whereClause}"
            ]);
        } catch (\Exception $e) {
            DB::purge($tempConn);
            // Sanitize error: remove connection details for security
            $errMsg = $e->getMessage();
            $errMsg = preg_replace('/\(Connection:.*$/', '', $errMsg);
            return response()->json(['success' => false, 'error' => trim($errMsg)]);
        }
    }

    /**
     * Copy filters from one user to another.
     */
    public function copyUserFilters(Request $request, User $targetUser)
    {
        if (!auth()->user()->is_super_admin && $targetUser->added_by !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $sourceUserId = $request->input('source_user_id');
        $sourceUser = User::find($sourceUserId);
        if (!$sourceUser) return response()->json(['error' => 'Source user not found'], 404);

        if (!auth()->user()->is_super_admin && $sourceUser->added_by !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $sourceFilters = \App\Models\UserTableFilter::where('user_id', $sourceUserId)->get();

        foreach ($sourceFilters as $sf) {
            \App\Models\UserTableFilter::updateOrCreate(
                [
                    'user_id' => $targetUser->id,
                    'database_connection_id' => $sf->database_connection_id,
                    'table_name' => $sf->table_name,
                ],
                ['filter_condition' => $sf->filter_condition]
            );
        }

        return response()->json(['success' => true, 'copied' => $sourceFilters->count()]);
    }

    /**
     * Update table filters for a user (with structured rules + sanitization).
     */
    public function updateTableFilters(Request $request, User $user)
    {
        if (!auth()->user()->is_super_admin && $user->added_by !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $filters = $request->input('filters', []);
        $dangerousKeywords = ['drop', 'delete', 'update', 'insert', 'alter', 'truncate', 'create', 'grant', 'revoke', 'exec', 'execute'];

        foreach ($filters as $f) {
            $dbId = $f['db_id'];
            $tableName = $f['table_name'];
            $rules = $f['rules'] ?? [];

            if (!auth()->user()->is_super_admin) {
                $conn = DatabaseConnection::find($dbId);
                if (!$conn || $conn->added_by !== auth()->id()) continue;
            }

            // Sanitize each rule
            $cleanRules = [];
            foreach ($rules as $rule) {
                $col = trim($rule['column'] ?? '');
                $op = trim($rule['operator'] ?? '=');
                $val = trim($rule['value'] ?? '');

                if (empty($col) || empty($val)) continue;

                // Sanitize: reject dangerous keywords in value
                $valLower = strtolower($val);
                $dangerous = false;
                foreach ($dangerousKeywords as $kw) {
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $valLower)) {
                        $dangerous = true;
                        break;
                    }
                }
                if ($dangerous) continue;

                // Whitelist operators
                $allowedOps = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'ILIKE', 'IN', 'NOT IN'];
                if (!in_array(strtoupper($op), $allowedOps)) $op = '=';

                $logic = strtoupper(trim($rule['logic'] ?? 'AND'));
                if (!in_array($logic, ['AND', 'OR'])) $logic = 'AND';

                $cleanRules[] = [
                    'column' => preg_replace('/[^a-zA-Z0-9_]/', '', $col),
                    'operator' => strtoupper($op),
                    'value' => $val,
                    'logic' => $logic
                ];
            }

            if (empty($cleanRules)) {
                \App\Models\UserTableFilter::where('user_id', $user->id)
                    ->where('database_connection_id', $dbId)
                    ->where('table_name', $tableName)
                    ->delete();
            } else {
                \App\Models\UserTableFilter::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'database_connection_id' => $dbId,
                        'table_name' => $tableName
                    ],
                    ['filter_condition' => json_encode($cleanRules)]
                );
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Build WHERE clause from structured rules array.
     */
    private function buildWhereFromRules(array $rules): string
    {
        if (empty($rules)) return '';

        $parts = [];
        $logics = [];
        foreach ($rules as $i => $rule) {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $rule['column'] ?? '');
            $op = strtoupper($rule['operator'] ?? '=');
            $val = $rule['value'] ?? '';
            $logic = strtoupper($rule['logic'] ?? 'AND');
            if (!in_array($logic, ['AND', 'OR'])) $logic = 'AND';

            if (empty($col) || empty($val)) continue;

            $escaped = str_replace("'", "''", $val);

            if ($op === 'IN' || $op === 'NOT IN') {
                $vals = array_map(function($v) {
                    $v = trim($v, " \t\n\r\"");
                    return "'" . str_replace("'", "''", trim($v)) . "'";
                }, explode(',', $val));
                $parts[] = "{$col} {$op} (" . implode(', ', $vals) . ")";
            } elseif ($op === 'LIKE' || $op === 'ILIKE') {
                $parts[] = "{$col} {$op} '{$escaped}'";
            } else {
                $parts[] = "{$col} {$op} '{$escaped}'";
            }

            if (count($parts) > 1) {
                $logics[] = $logic;
            }
        }

        if (empty($parts)) return '';

        // Build with logic operators
        $result = $parts[0];
        for ($i = 1; $i < count($parts); $i++) {
            $logic = $logics[$i - 1] ?? 'AND';
            $result .= " {$logic} {$parts[$i]}";
        }

        return $result;
    }

    /**
     * Clear table cache (called internally)
     */
    private function clearTableCache(): void
    {
        cache()->forget('agentic_all_dbs_admin');
        cache()->forget('agentic_all_dbs_admin_v3'); // key dipakai QueryService
        cache()->forget('all_db_tables_admin');

        Role::all()->each(function($role) {
            cache()->forget("agentic_allowed_dbs_role_{$role->id}");
            cache()->forget("agentic_allowed_dbs_role_v2_{$role->id}");
            cache()->forget("agentic_allowed_tables_role_{$role->id}");
            cache()->forget("allowed_tables_role_{$role->id}");
        });
    }

    /**
     * Display the admin panel guide.
     */
    public function guide()
    {
        return view('admin.guide');
    }
}
