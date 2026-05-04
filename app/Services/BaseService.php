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
        if (auth()->check() && auth()->user()->is_admin) {
            return [];
        }

        $cacheKey = "view_deps_v2_{$databaseCode}_" . ($schema ?: 'any') . "_{$table}";
        
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

                // PostgreSQL raw catalog dependency query
                $results = \Illuminate\Support\Facades\DB::connection($tempConn)->select("
                    SELECT DISTINCT
                        referenced_schema.nspname AS table_schema,
                        referenced_table.relname AS table_name
                    FROM pg_rewrite
                    JOIN pg_class view_table ON pg_rewrite.ev_class = view_table.oid
                    JOIN pg_namespace view_schema ON view_table.relnamespace = view_schema.oid
                    JOIN pg_depend ON pg_rewrite.oid = pg_depend.objid
                    JOIN pg_class referenced_table ON pg_depend.refobjid = referenced_table.oid
                    JOIN pg_namespace referenced_schema ON referenced_table.relnamespace = referenced_schema.oid
                    WHERE view_table.relname = ?
                      AND referenced_table.relkind IN ('r', 'v', 'm')
                      AND referenced_table.relname != ?
                ", [$table, $table]);

                \Illuminate\Support\Facades\DB::purge($tempConn);

                $mapped = array_map(fn($r) => [
                    'schema' => strtolower($r->table_schema),
                    'table' => strtolower($r->table_name)
                ], $results);

                \Illuminate\Support\Facades\Log::info("[BaseService] Dependencies found for {$table}: " . json_encode($mapped));

                return $mapped;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("[BaseService] Failed to fetch view dependencies for {$schema}.{$table} on {$databaseCode}: " . $e->getMessage());
                return [];
            }
        });
    }
}
