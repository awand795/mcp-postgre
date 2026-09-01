<?php

namespace App\Models;

use App\Services\Database\DriverFactory;
use App\Services\Database\SshTunnelManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseConnection extends Model
{
    use HasFactory;

    protected $table = 'database_connections';

    protected $fillable = [
        'name',
        'code',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'schema',
        'table_filters',
        'ssl_mode',
        'connection_timeout',
        'options',
        'description',
        'is_active',
        'is_default',
        'last_tested_at',
        'test_status',
        'added_by',
        'use_ssh',
        'ssh_host',
        'ssh_port',
        'ssh_username',
        'ssh_auth_type',
        'ssh_password',
        'ssh_private_key',
        'ssh_pid',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'port' => 'integer',
        'connection_timeout' => 'integer',
        'last_tested_at' => 'datetime',
        'options' => 'array',
        'use_ssh' => 'boolean',
        'ssh_port' => 'integer',
        'ssh_pid' => 'integer',
        // password encryption handled manually in mutator/accessor
    ];

    protected $hidden = [
        'password',
        'ssh_password',
    ];

    protected static function booted()
    {
        static::saved(function ($db) {
            if ($db->is_active) {
                $db->syncRolePermissions();
            }
        });

        static::deleting(function ($db) {
            // Hapus semua hak akses tabel (role_permissions) yang terkait database ini
            $dbCodes = array_filter(array_unique([$db->database, $db->code, $db->name]));
            if (!empty($dbCodes)) {
                RolePermission::whereIn('database_code', $dbCodes)->delete();
            }

            // Hapus semua user table filter yang terkait database ini
            UserTableFilter::where('database_connection_id', $db->id)->delete();
        });
    }

    /**
     * Sinkronisasi izin tabel database dengan role:
     * 1. Hapus dari SEMUA role untuk tabel-tabel yang sekarang terfilter (tidak lagi valid).
     * 2. Auto-izinkan ke role Super Admin untuk semua tabel yang valid (baru / ditambahkan ulang).
     * 3. Role selain Super Admin TIDAK ditambah otomatis (menunggu dicentang manual oleh admin).
     */
    public function syncRolePermissions(): void
    {
        if (!$this->is_active) {
            return;
        }

        try {
            $validTables = $this->getTables();
            $dbCode = $this->database;

            $validKeys = [];
            foreach ($validTables as $t) {
                $validKeys[] = "{$dbCode}|{$t['schema_name']}|{$t['table_name']}";
            }

            // 1. Hapus dari SEMUA role untuk tabel yang terfilter atau tidak lagi ada
            $existingPerms = RolePermission::where('database_code', $dbCode)->get();
            foreach ($existingPerms as $perm) {
                $key = "{$perm->database_code}|{$perm->schema_name}|{$perm->table_name}";
                if (!in_array($key, $validKeys)) {
                    $perm->delete();
                }
            }

            // 2. Cari semua role Super Admin (berdasarkan ID 3, nama 'Super Admin', atau superadmin)
            $superAdminRoles = Role::where(function ($q) {
                $q->whereRaw('LOWER(name) = ?', ['super admin'])
                  ->orWhereRaw('LOWER(name) = ?', ['superadmin'])
                  ->orWhere('id', 3);
            })->get();

            // 3. Tambahkan tabel valid HANYA ke role Super Admin (auto-grant)
            foreach ($superAdminRoles as $role) {
                $existingRoleKeys = RolePermission::where('role_id', $role->id)
                    ->where('database_code', $dbCode)
                    ->get()
                    ->map(fn($p) => "{$p->database_code}|{$p->schema_name}|{$p->table_name}")
                    ->toArray();

                foreach ($validTables as $t) {
                    $k = "{$dbCode}|{$t['schema_name']}|{$t['table_name']}";
                    if (!in_array($k, $existingRoleKeys)) {
                        RolePermission::create([
                            'role_id'       => $role->id,
                            'database_code' => $dbCode,
                            'schema_name'   => $t['schema_name'],
                            'table_name'    => $t['table_name'],
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("syncRolePermissions failed for DB {$this->name}: " . $e->getMessage());
        }
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Get the driver adapter for this connection
     */
    public function getAdapter(): \App\Services\Database\DriverAdapter
    {
        return DriverFactory::make($this->driver);
    }

    /**
     * Mutator: Encrypt password before saving
     */
    public function setPasswordAttribute($value): void
    {
        if (!empty($value) && !$this->isEncrypted($value)) {
            $this->attributes['password'] = encrypt($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    /**
     * Accessor: Decrypt password when accessing
     */
    public function getPasswordAttribute(): ?string
    {
        $value = $this->attributes['password'] ?? null;
        if ($value && $this->isEncrypted($value)) {
            return decrypt($value);
        }
        return $value;
    }

    /**
     * Mutator: Encrypt SSH password before saving
     */
    public function setSshPasswordAttribute($value): void
    {
        if (!empty($value) && !$this->isEncrypted($value)) {
            $this->attributes['ssh_password'] = encrypt($value);
        } else {
            $this->attributes['ssh_password'] = $value;
        }
    }

    /**
     * Accessor: Decrypt SSH password when accessing
     */
    public function getSshPasswordAttribute(): ?string
    {
        $value = $this->attributes['ssh_password'] ?? null;
        if ($value && $this->isEncrypted($value)) {
            return decrypt($value);
        }
        return $value;
    }

    /**
     * Mutator: Encrypt SSH private key before saving
     */
    public function setSshPrivateKeyAttribute($value): void
    {
        if (!empty($value) && !$this->isEncrypted($value)) {
            $this->attributes['ssh_private_key'] = encrypt($value);
        } else {
            $this->attributes['ssh_private_key'] = $value;
        }
    }

    /**
     * Accessor: Decrypt SSH private key when accessing
     */
    public function getSshPrivateKeyAttribute(): ?string
    {
        $value = $this->attributes['ssh_private_key'] ?? null;
        if ($value && $this->isEncrypted($value)) {
            return decrypt($value);
        }
        return $value;
    }

    /**
     * Check if a string looks like an encrypted value
     */
    private function isEncrypted($value): bool
    {
        try {
            decrypt($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get decrypted password for connection config
     */
    public function getDecryptedPasswordAttribute(): string
    {
        return $this->password ?? '';
    }

    /**
     * Get connection configuration array
     */
    public function getConnectionConfig(): array
    {
        $adapter = $this->getAdapter();

        $host = $this->host;
        $port = $this->port;

        if ($this->use_ssh) {
            try {
                $localPort = SshTunnelManager::start($this);
                $host = '127.0.0.1';
                $port = $localPort;
            } catch (\Exception $e) {
                Log::error("SSH Tunnel failed for {$this->name}: " . $e->getMessage());
                throw new \Exception("Gagal melakukan SSH Tunnel: " . $e->getMessage());
            }
        }

        $config = [
            'driver' => $this->driver,
            'host' => $host,
            'port' => $port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->getDecryptedPasswordAttribute(),
            'charset' => $this->driver === 'mysql' || $this->driver === 'mariadb' ? 'utf8mb4' : 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => $this->schema,
            'ssl_mode' => $this->ssl_mode,
            'connection_timeout' => $this->connection_timeout,
            // PENTING: 'options' HARUS selalu berupa array (PDO::ATTR_* keys).
            // Laravel connector memanggil array_diff_key() pada nilai ini —
            // jika berupa string atau null akan crash dengan TypeError.
            'options' => (function () {
                try {
                    $raw = $this->getAttribute('options');
                    if (is_array($raw)) return $raw;
                    if (is_string($raw) && !empty($raw)) {
                        $decoded = json_decode($raw, true);
                        return is_array($decoded) ? $decoded : [];
                    }
                } catch (\Exception $e) {}
                return []; // null, false, 0, dsb → array kosong
            })(),
        ];

        // Let the adapter add driver-specific options
        return $adapter->getConnectionOptions($config);
    }

    /**
     * Test the database connection
     */
    public function testConnection(): array
    {
        try {
            $config = $this->getConnectionConfig();
            $adapter = $this->getAdapter();

            // Create temporary connection
            DB::purge('test_connection');
            config(["database.connections.test_connection" => $config]);

            if ($this->driver !== 'clickhouse') {
                $pdo = DB::connection('test_connection')->getPdo();
            }
            $versionQuery = DriverFactory::getVersionQuery($this->driver);
            $result = DB::connection('test_connection')->select($versionQuery);

            DB::purge('test_connection');

            $this->update([
                'test_status' => 'success',
                'last_tested_at' => now(),
            ]);

            return [
                'success' => true,
                'version' => $adapter->formatVersion($result),
            ];
        } catch (\Exception $e) {
            DB::purge('test_connection');

            $this->update([
                'test_status' => 'failed',
                'last_tested_at' => now(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get tables and views from this database connection
     */
    public function getTables(): array
    {
        $tempConn = 'temp_conn_' . $this->code;

        try {
            $config = $this->getConnectionConfig();
            $adapter = $this->getAdapter();

            DB::purge($tempConn);
            config(["database.connections.{$tempConn}" => $config]);

            $query = $adapter->listTablesQuery();

            if ($this->driver === 'clickhouse') {
                // ClickHouse HTTP driver (bavix/laravel-clickhouse) IGNORES the $bindings parameter.
                // The select() method signature is: select($query, $bindings = [], $tables = [])
                // and it passes $tables (3rd param) to readOne(), not $bindings (2nd param).
                // So we must inline the database name directly into the SQL query.
                // Escape single quotes dengan cara SQL standar (double single-quote)
                $escapedDb = str_replace("'", "''", $this->database);
                $query = str_replace('?', "'{$escapedDb}'", $query);
                $tables = DB::connection($tempConn)->select($query);
            } elseif ($this->driver === 'mysql' || $this->driver === 'mariadb') {
                // MySQL/MariaDB: bind database name karena query pakai placeholder '?'
                // (lebih reliable daripada DATABASE() pada dynamic connections)
                $tables = DB::connection($tempConn)->select($query, [$this->database]);
            } else {
                // SQLite uses PRAGMA which can't be parameterized
                $tables = DB::connection($tempConn)->select($query);
            }

            \Log::info("DatabaseConnection: Loaded tables for {$this->name} ({$this->driver})", [
                'count' => count($tables),
                'database' => $this->database
            ]);

            DB::purge($tempConn);

            if (empty($tables)) {
                \Log::warning("No tables or views found in database: {$this->name}");
            }

            $mappedTables = array_map(function($table) {
                // Handle both stdClass objects (PDO drivers) and arrays (ClickHouse HTTP driver)
                if (is_array($table)) {
                    $table = (object) $table;
                }
                $isView = isset($table->table_type) && stripos($table->table_type, 'view') !== false;
                return [
                    'table_name' => $table->table_name,
                    'schema_name' => $table->table_schema ?? $this->schema ?? '',
                    'description' => $table->description ?? '',
                    'table_type' => $isView ? $table->table_type : 'table',
                ];
            }, $tables);

            if (!empty($this->table_filters)) {
                $filters = array_filter(array_map('trim', explode("\n", $this->table_filters)));
                if (!empty($filters)) {
                    $mappedTables = array_filter($mappedTables, function($item) use ($filters) {
                        foreach ($filters as $filter) {
                            $pattern = '/' . str_replace('/', '\/', $filter) . '/i';
                            if (@preg_match($pattern, $item['table_name'])) {
                                return false; // exclude
                            }
                        }
                        return true;
                    });
                    $mappedTables = array_values($mappedTables);
                }
            }

            return $mappedTables;
        } catch (\Exception $e) {
            \Log::error("Failed to get tables from database: {$this->name}", [
                'driver'   => $this->driver,
                'host'     => $this->host,
                'port'     => $this->port,
                'database' => $this->database,
                'error'    => $e->getMessage(),
            ]);
            DB::purge($tempConn);
            return [];
        }
    }

    /**
     * Get all schemas in this database
     */
    public function getSchemas(): array
    {
        $tempConn = 'temp_conn_' . $this->code;

        try {
            $config = $this->getConnectionConfig();
            $adapter = $this->getAdapter();

            // If driver doesn't use schema concept, return empty or database name
            if (!$adapter->usesSchema()) {
                return [$this->database];
            }

            DB::purge($tempConn);
            config(["database.connections.{$tempConn}" => $config]);

            $query = $adapter->listSchemasQuery();
            $schemas = DB::connection($tempConn)->select($query);

            DB::purge($tempConn);

            return array_column($schemas, 'schema_name');
        } catch (\Exception $e) {
            DB::purge($tempConn);
            return [];
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
