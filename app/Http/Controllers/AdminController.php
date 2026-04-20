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
        $query = User::with(['roleModel', 'aiModels', 'aiKeys']);
        
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
        $roles = Role::all();
        $aiModels = \App\Models\AiModel::with('provider')->where('is_active', true)->get();
        $aiKeys = \App\Models\AiApiKey::with('provider')->where('is_active', true)->get();
        
        return view('admin.users', compact('users', 'roles', 'aiModels', 'aiKeys'));
    }

    public function userStore(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_admin' => $request->has('is_admin'),
            'max_tokens' => $request->input('max_tokens', 32768),
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

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'is_admin' => $request->has('is_admin'),
            'max_tokens' => $request->input('max_tokens', 32768),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Jika role user berubah, clear cache allowed tables user lama & baru
        if ($user->role != $request->role) {
            cache()->forget("agentic_allowed_dbs_role_{$user->role}");    // correct key
            cache()->forget("agentic_allowed_dbs_role_{$request->role}"); // correct key
        }

        $user->update($data);

        $user->aiModels()->sync($request->ai_models ?? []);
        $user->aiKeys()->sync($request->ai_keys ?? []);

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
        $roles = Role::with('permissions')->get();
        $allTables = $this->getAllTables();
        $databases = DatabaseConnection::active()->get();

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
        Role::create($request->only('name', 'description'));
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
                    $defaultDbCode = $defaultDb->code;
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

        // Clear SEMUA cache keys terkait role ini (harus konsisten dengan ToolCallExecutor)
        cache()->forget("agentic_allowed_dbs_role_{$role->id}");      // key yg dipakai QueryService
        cache()->forget("agentic_allowed_tables_role_{$role->id}");   // key lama (backward compat)
        cache()->forget("allowed_tables_role_{$role->id}");           // key lama (backward compat)
        // Clear cache admin juga agar sinkron
        cache()->forget('agentic_all_dbs_admin');
        cache()->forget('all_db_tables_admin');

        return response()->json(['success' => true]);
    }

    // ── Database Management ──────────────────────────────────────────────────────

    public function databases()
    {
        $databases = DatabaseConnection::orderBy('is_default', 'desc')->orderBy('name')->get();
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
        $validated = $request->validate([
            'driver' => 'required|in:pgsql,mysql,mariadb,sqlsrv,sqlite',
            'host' => 'required_if:driver,pgsql,mysql,mariadb,sqlsrv|nullable',
            'port' => 'required|integer',
            'database' => 'required',
            'username' => 'required_if:driver,pgsql,mysql,mariadb,sqlsrv|nullable',
            'password' => 'required_if:driver,pgsql,mysql,mariadb,sqlsrv|nullable',
            'schema' => 'nullable',
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
        ]);

        $schemas = $tempDb->getSchemas();
        return response()->json(['schemas' => $schemas]);
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
                            // Use actual database name as identifier (consistent with role_permissions.database_code)
                            'database_code' => $db->database,
                            'database_name' => $db->database,
                            'schema_name'   => $table['schema_name'],
                            'table_name'    => $table['table_name'],
                            'description'   => $table['description'] ?? '',
                            'table_type'    => $table['table_type'] ?? 'table',
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to get tables from database: {$db->name}", [
                        'error' => $e->getMessage()
                    ]);
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

    /**
     * Clear table cache (called internally)
     */
    private function clearTableCache(): void
    {
        // FIX: Use correct cache keys that match QueryService
        cache()->forget('agentic_all_dbs_admin');
        cache()->forget('all_db_tables_admin');

        // Clear role-specific caches
        Role::all()->each(function($role) {
            cache()->forget("agentic_allowed_dbs_role_{$role->id}");
            cache()->forget("agentic_allowed_tables_role_{$role->id}");
            cache()->forget("allowed_tables_role_{$role->id}");
        });
    }
}
