<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'description',
        'is_active',
        'is_default',
        'last_tested_at',
        'test_status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'port' => 'integer',
        'last_tested_at' => 'datetime',
        // password encryption handled manually in mutator/accessor
    ];

    protected $hidden = [
        'password',
    ];

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
        return [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->getDecryptedPasswordAttribute(),
            'schema' => $this->schema,
            'search_path' => [$this->schema, 'public'],
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ];
    }

    /**
     * Test the database connection
     */
    public function testConnection(): array
    {
        try {
            $config = $this->getConnectionConfig();
            
            // Create temporary connection
            \Illuminate\Support\Facades\DB::purge('test_connection');
            config(["database.connections.test_connection" => $config]);
            
            $pdo = \Illuminate\Support\Facades\DB::connection('test_connection')->getPdo();
            $result = \Illuminate\Support\Facades\DB::connection('test_connection')
                ->select("SELECT version()");
            
            \Illuminate\Support\Facades\DB::purge('test_connection');
            
            $this->update([
                'test_status' => 'success',
                'last_tested_at' => now(),
            ]);

            return [
                'success' => true,
                'version' => $result[0]->version ?? 'Unknown',
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::purge('test_connection');
            
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
        try {
            $config = $this->getConnectionConfig();
            \Illuminate\Support\Facades\DB::purge('temp_conn_' . $this->code);
            config(["database.connections.temp_conn_" . $this->code => $config]);

            // Query ALL tables AND views from all schemas
            $tables = \Illuminate\Support\Facades\DB::connection('temp_conn_' . $this->code)
                ->select("
                    SELECT
                        t.table_name,
                        t.table_schema,
                        COALESCE(
                            (SELECT description FROM pg_description
                             WHERE objoid = (t.table_schema || '.' || t.table_name)::regclass),
                            ''
                        ) as description,
                        t.table_type
                    FROM information_schema.tables t
                    WHERE t.table_schema NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
                    AND t.table_type IN ('BASE TABLE', 'VIEW')
                    ORDER BY t.table_type DESC, t.table_schema, t.table_name
                ");

            \Illuminate\Support\Facades\DB::purge('temp_conn_' . $this->code);

            if (empty($tables)) {
                \Log::warning("No tables or views found in database: {$this->name}");
            }

            return array_map(function($table) {
                $isView = $table->table_type === 'VIEW';
                return [
                    'table_name' => $table->table_name,
                    'schema_name' => $table->table_schema,
                    'description' => $table->description ?? '',
                    'table_type' => $isView ? 'view' : 'table',
                ];
            }, $tables);
        } catch (\Exception $e) {
            \Log::error("Failed to get tables from database: {$this->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            \Illuminate\Support\Facades\DB::purge('temp_conn_' . $this->code);
            return [];
        }
    }

    /**
     * Get all schemas in this database
     */
    public function getSchemas(): array
    {
        try {
            $config = $this->getConnectionConfig();
            \Illuminate\Support\Facades\DB::purge('temp_conn_' . $this->code);
            config(["database.connections.temp_conn_" . $this->code => $config]);
            
            $schemas = \Illuminate\Support\Facades\DB::connection('temp_conn_' . $this->code)
                ->select("
                    SELECT schema_name 
                    FROM information_schema.schemata 
                    WHERE schema_name NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
                    ORDER BY schema_name
                ");
            
            \Illuminate\Support\Facades\DB::purge('temp_conn_' . $this->code);
            
            return array_column($schemas, 'schema_name');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::purge('temp_conn_' . $this->code);
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
