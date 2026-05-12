<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * BaseService
 *
 * Shared utilities for all service classes.
 */
abstract class BaseService
{
    /**
     * Safe JSON encode with error logging.
     */
    protected function safeJsonEncode(mixed $data): string
    {
        $json = json_encode($data);
        if ($json === false) {
            Log::error("[BaseService] JSON encode failed: " . json_last_error_msg());
            return json_encode(['error' => 'Failed to encode response.']);
        }
        return $json;
    }

    /**
     * Log tool execution with standardized format.
     */
    protected function logToolCall(string $toolName, array $arguments): void
    {
        Log::info("[ToolCallExecutor] Tool called: {$toolName}", $arguments);
    }

    /**
     * Log tool failure with standardized format.
     */
    protected function logToolFailure(string $toolName, \Throwable $e): void
    {
        Log::error("[ToolCallExecutor] Tool {$toolName} failed: " . $e->getMessage());
    }

    /**
     * Return error response as JSON string.
     */
    protected function errorResponse(string $message): string
    {
        return $this->safeJsonEncode(['error' => $message]);
    }

    /**
     * Check if array key exists and is not empty.
     */
    protected function getArrayValue(array $array, string $key, mixed $default = null): mixed
    {
        return $array[$key] ?? $default;
    }

    /**
     * Safely cast value to float.
     */
    protected function toFloat(mixed $value): float
    {
        return (float) ($value ?? 0);
    }

    /**
     * Safely cast value to int.
     */
    protected function toInt(mixed $value): int
    {
        return (int) ($value ?? 0);
    }

    /**
     * Decode JSON string safely.
     */
    protected function decodeJson(string $json, bool $associative = true): mixed
    {
        return json_decode($json, $associative);
    }

    /**
     * Check if a specific table in a database/schema is allowed by RBAC.
     * $allowedDbs format: [ 'db_code' => [ 'schema_name' => [ 'table1', 'table2', '*' ] ] ]
     */
    protected function isTableAllowed(string $dbCode, ?string $schema, string $table, array $allowedDbs): bool
    {
        if (!isset($allowedDbs[$dbCode])) {
            return false;
        }

        $dbPermissions = $allowedDbs[$dbCode];
        $table = strtolower($table);
        $schema = $schema ? strtolower($schema) : null;

        // 1. Check wildcard schema permission (Schema='*')
        if (isset($dbPermissions['*'])) {
            if ($this->tableMatchesEntries($table, $dbPermissions['*'])) {
                return true;
            }
        }

        // 2. Check specific schema permission
        if ($schema && isset($dbPermissions[$schema])) {
            if ($this->tableMatchesEntries($table, $dbPermissions[$schema])) {
                return true;
            }
        } elseif (!$schema) {
            // If no schema prefix, we allow it if it's permitted in ANY specific schema.
            // This is necessary since users/AI often omit schema names in their SQL.
            foreach ($dbPermissions as $s => $entries) {
                if ($s === '*') continue;
                if ($this->tableMatchesEntries($table, $entries)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate global security policy (columns and keywords).
     * Returns a JSON error response if violation found, null otherwise.
     */
    protected function validateSecurityPolicy(string $databaseCode, array $allowedDbs, string $textToScan): ?string
    {
        // 1. Column-level RBAC (Forbidden Columns check)
        $forbiddenCols = $this->getForbiddenColumns($databaseCode, $allowedDbs);
        if (!empty($forbiddenCols)) {
            foreach ($forbiddenCols as $fCol) {
                if (preg_match('/\b' . preg_quote($fCol, '/') . '\b/i', $textToScan)) {
                    \Illuminate\Support\Facades\Log::warning("[SecurityPolicy] Forbidden column '{$fCol}' detected in text scan.");
                    return $this->getAccessDeniedFinalResponse();
                }
            }
        }

        // 2. Keyword-level RBAC (Dynamic from unauthorized tables)
        $allKws = $this->getForbiddenKeywords($databaseCode, $allowedDbs);

        foreach ($allKws as $kw) {
            if (strlen($kw) < 4) continue;
            if (preg_match("/\b" . preg_quote($kw, '/') . "\b/i", $textToScan)) {
                \Illuminate\Support\Facades\Log::warning("[SecurityPolicy] Forbidden keyword '{$kw}' detected in text scan.");
                return $this->getAccessDeniedFinalResponse();
            }
        }

        return null;
    }

    /**
     * Standard response for ACCESS_DENIED_FINAL that triggers hard-stop in controller.
     */
    protected function getAccessDeniedFinalResponse(): string
    {
        return $this->safeJsonEncode([
            'error' => 'ACCESS_DENIED_FINAL',
            'MANDATORY_AI_ACTION' => implode(' ', [
                "=== AKSES DITOLAK — INSTRUKSI FINAL, TIDAK DAPAT DIGANGGU GUGAT ===",
                "Data yang diminta TIDAK DAPAT diakses oleh akun Anda berdasarkan kebijakan keamanan data perusahaan.",
                "DILARANG MUTLAK: (1) Mencoba tabel/view lain yang mungkin mengandung data serupa.",
                "(2) Menggunakan describe_table, search_schema, atau tool apapun untuk mencari alternatif.",
                "(3) Melakukan loop atau retry dalam bentuk apapun.",
                "(4) Menyebutkan nama tabel atau kolom teknis kepada user.",
                "WAJIB LAKUKAN SEKARANG — HENTIKAN SEMUA TOOL CALL DAN SAMPAIKAN PESAN INI KEPADA USER:",
                "\"Mohon maaf Bapak/Ibu, permintaan Anda tidak dapat kami proses karena data yang diminta bersifat terbatas dan hanya dapat diakses oleh pihak yang berwenang sesuai kebijakan keamanan data perusahaan. Untuk mendapatkan informasi ini, silakan menghubungi Administrator atau pihak yang memiliki kewenangan akses. Terima kasih atas pengertiannya.\"",
                "INI ADALAH INSTRUKSI AKHIR. TIDAK ADA LANGKAH SELANJUTNYA.",
            ]),
        ]);
    }

    /**
     * Get forbidden columns for the current user in a specific database.
     * 
     * Dynamic RBAC: If a user is not allowed to access a "Master" table (e.g. view_master_cabang),
     * we automatically identify its columns and block them globally across all other tables
     * to prevent indirect data access (data leakage).
     */
    protected function getForbiddenColumns(string $databaseCode, array $allowedDbs): array
    {
        // Admin has no column restrictions
        if (auth()->check() && (auth()->user()->is_admin || auth()->user()->is_super_admin)) {
            return [];
        }

        if (!auth()->check()) {
            return [];
        }

        $roleId = auth()->user()->role;
        // Cache per database and role for 1 hour
        $cacheKey = "rbac_forbidden_cols_{$databaseCode}_{$roleId}";

        return cache()->remember($cacheKey, 3600, function () use ($databaseCode, $allowedDbs) {
            $connModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
            if (!$connModel) {
                return [];
            }

            $allTables = $connModel->getTables();
            $unauthorizedSensitiveTables = [];

            // Sensitive tables are usually "Master" data or Views starting with specific prefixes
            $sensitivePrefixes = ['view_master_', 'master_', 'mst_', 'm_'];
            
            foreach ($allTables as $tableInfo) {
                $tableName = $tableInfo['table_name'];
                $schema = $tableInfo['schema_name'];
                
                $isSensitive = false;
                $lowTable = strtolower($tableName);
                foreach ($sensitivePrefixes as $prefix) {
                    if (str_starts_with($lowTable, $prefix)) {
                        $isSensitive = true;
                        break;
                    }
                }
                
                // If it's a sensitive table and NOT allowed, we must block its columns globally
                if ($isSensitive && !$this->isTableAllowed($databaseCode, $schema, $tableName, $allowedDbs)) {
                    $unauthorizedSensitiveTables[] = $tableInfo;
                }
            }

            if (empty($unauthorizedSensitiveTables)) {
                return [];
            }

            $forbiddenCols = [];
            // We don't want to block common columns that might exist in many tables
            $commonCols = [
                'id', 'created_at', 'updated_at', 'deleted_at', 
                'row_id', 'created_by', 'updated_by', 'is_active',
                'version', 'timestamp'
            ];

            foreach ($unauthorizedSensitiveTables as $tableInfo) {
                $cols = $this->getTableColumns($connModel, $tableInfo['schema_name'], $tableInfo['table_name']);
                foreach ($cols as $colName) {
                    $lowCol = strtolower($colName);
                    if (!in_array($lowCol, $commonCols)) {
                        $forbiddenCols[] = $lowCol;
                    }
                }
            }

            $result = array_unique($forbiddenCols);
            Log::info("[BaseService] Dynamic RBAC: Found " . count($result) . " forbidden columns for DB {$databaseCode} due to unauthorized tables: " . implode(', ', array_column($unauthorizedSensitiveTables, 'table_name')));
            
            return $result;
        });
    }

    /**
     * Get forbidden keywords based on unauthorized sensitive tables.
     * Useful for blocking SQL value searches (e.g. LIKE '%keyword%').
     */
    public function getForbiddenKeywords(string $databaseCode, array $allowedDbs): array
    {
        if (auth()->check() && (auth()->user()->is_admin || auth()->user()->is_super_admin)) return [];

        $connModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
        if (!$connModel) return [];

        $allTables = $connModel->getTables();
        $keywords = [];

        foreach ($allTables as $tableInfo) {
            $tableName = $tableInfo['table_name'];
            $schema = $tableInfo['schema_name'];
            
            // Check if it's a sensitive table prefix
            $isSensitive = false;
            $lowTable = strtolower($tableName);
            foreach (['view_master_', 'master_', 'mst_', 'm_'] as $prefix) {
                if (str_starts_with($lowTable, $prefix)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive && !$this->isTableAllowed($databaseCode, $schema, $tableName, $allowedDbs)) {
                // Extract keywords from table name (e.g. 'view_master_cabang_mbi' -> ['cabang'])
                $clean = str_replace(['view_master_', 'master_', 'view_', 'mst_', 'm_', '_mbi'], '', $lowTable);
                $parts = explode('_', $clean);
                foreach ($parts as $p) {
                    if (strlen($p) >= 4) $keywords[] = $p;
                }
            }
        }

        return array_unique($keywords);
    }

    /**
     * Helper to get column names for a specific table.
     */
    private function getTableColumns($connModel, $schema, $table): array
    {
        $tempConn = 'temp_rbac_cols_' . uniqid();
        try {
            $config = $connModel->getConnectionConfig();
            $adapter = $connModel->getAdapter();

            config(["database.connections.{$tempConn}" => $config]);

            $query = $adapter->describeTableQuery();
            $params = $adapter->usesSchema()
                ? [$table, $schema ?: $connModel->schema ?: 'public']
                : [$table, $connModel->database];

            $results = \Illuminate\Support\Facades\DB::connection($tempConn)->select($query, $params);
            \Illuminate\Support\Facades\DB::purge($tempConn);

            return array_map(fn($r) => $r->column_name, $results);
        } catch (\Exception $e) {
            \Log::error("[BaseService] Failed to fetch columns for RBAC check on {$schema}.{$table}: " . $e->getMessage());
            \Illuminate\Support\Facades\DB::purge($tempConn);
            return [];
        }
    }

    /**
     * Helper to check if a table name matches a list of allowed entries (strings or objects).
     */
    private function tableMatchesEntries(string $tableName, array $entries): bool
    {
        foreach ($entries as $entry) {
            $name = is_array($entry) ? ($entry['name'] ?? '') : (string) $entry;
            if ($name === '*' || strtolower($name) === strtolower($tableName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get underlying physical tables for a VIEW (Deep RBAC).
     */
    protected function getUnderlyingTables(string $databaseCode, ?string $schema, string $table): array
    {
        // Don't check for admin (they have access anyway)
        if (auth()->check() && (auth()->user()->is_admin || auth()->user()->is_super_admin)) {
            return [];
        }

        $cacheKey = "view_deps_v3_{$databaseCode}_" . ($schema ?: 'any') . "_{$table}";
        
        return cache()->remember($cacheKey, 3600, function () use ($databaseCode, $schema, $table) {
            try {
                $connModel = \App\Models\DatabaseConnection::where('database', $databaseCode)->active()->first();
                if (!$connModel) return [];

                // Cek apakah driver PostgreSQL
                if ($connModel->driver !== 'pgsql') {
                    return []; // deep check saat ini hanya untuk pgsql
                }

                $tempConn = 'temp_rbac_check_' . $databaseCode . '_' . uniqid();
                config(["database.connections.{$tempConn}" => $connModel->getConnectionConfig()]);

                \Illuminate\Support\Facades\Log::info("[BaseService] Checking dependencies for {$schema}.{$table} on {$databaseCode}");

                // PostgreSQL raw catalog dependency query (Robust version)
                // We look for dependencies linked to the view's rewrite rules.
                $results = \Illuminate\Support\Facades\DB::connection($tempConn)->select("
                    SELECT DISTINCT
                        n.nspname AS table_schema,
                        c.relname AS table_name
                    FROM pg_class c
                    JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE c.oid IN (
                        SELECT refobjid 
                        FROM pg_depend 
                        WHERE objid IN (
                            SELECT oid FROM pg_rewrite WHERE ev_class = (
                                SELECT oid FROM pg_class WHERE relname = ? 
                                AND relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = ?)
                            )
                        )
                    )
                    AND c.relkind IN ('r', 'v', 'm')
                    AND c.relname != ?
                ", [$table, $schema ?: $connModel->schema ?: 'public', $table]);

                \Illuminate\Support\Facades\DB::purge($tempConn);

                $mapped = array_map(fn($r) => [
                    'schema' => strtolower($r->table_schema),
                    'table' => strtolower($r->table_name)
                ], $results);

                \Illuminate\Support\Facades\Log::info("[BaseService] Dependencies found for {$table} (v2): " . json_encode($mapped));

                return $mapped;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("[BaseService] Failed to fetch view dependencies for {$schema}.{$table} on {$databaseCode}: " . $e->getMessage());
                return [];
            }
        });
    }
}
