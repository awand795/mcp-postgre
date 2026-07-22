<?php

namespace App\Services\Database;

/**
 * ClickhouseAdapter
 *
 * Metadata queries for ClickHouse databases.
 */
class ClickhouseAdapter extends DriverAdapter
{
    public function driver(): string
    {
        return 'clickhouse';
    }

    public function listTablesQuery(): string
    {
        return "
            SELECT 
                name AS table_name,
                database AS table_schema,
                comment AS description,
                multiIf(
                    engine = 'View', 'view',
                    engine IN ('MaterializedView', 'LiveView', 'WindowView'), 'materialized_view',
                    'table'
                ) AS table_type
            FROM system.tables
            WHERE database = ?
            ORDER BY name
        ";
    }

    public function listSchemasQuery(): string
    {
        return "
            SELECT name as schema_name
            FROM system.databases
            ORDER BY name
        ";
    }

    public function describeTableQuery(): string
    {
        return "
            SELECT
                name AS column_name,
                type AS data_type,
                if(type LIKE 'Nullable%', 'YES', 'NO') AS is_nullable,
                default_expression AS column_default
            FROM system.columns
            WHERE table = ? AND database = ?
            ORDER BY position
        ";
    }

    public function describeTableWithKeysQuery(): string
    {
        return "
            SELECT
                name AS column_name,
                type AS data_type,
                if(type LIKE 'Nullable%', 'YES', 'NO') AS is_nullable,
                default_expression AS column_default,
                NULL AS foreign_key_table,
                NULL AS foreign_key_column,
                comment AS description
            FROM system.columns
            WHERE table = ? AND database = ?
            ORDER BY position
        ";
    }

    public function getViewDefinitionQuery(): string
    {
        return "
            SELECT create_table_query AS view_definition
            FROM system.tables
            WHERE table = ? AND database = ? AND engine = 'View'
        ";
    }

    public function getTableIndexesQuery(): string
    {
        return "
            SELECT
                name AS index_name,
                type AS column_name,
                0 AS is_primary,
                0 AS is_unique
            FROM system.data_skipping_indices
            WHERE table = ? AND database = ?
        ";
    }

    public function searchSchemaQuery(): string
    {
        return "
            SELECT
                database AS table_schema,
                table AS table_name,
                name AS column_name,
                comment AS description
            FROM system.columns
            WHERE (table LIKE ? OR name LIKE ? OR comment LIKE ?)
            AND database NOT IN ('system', 'information_schema', 'INFORMATION_SCHEMA')
            ORDER BY database, table
            LIMIT 100
        ";
    }

    public function usesSchema(): bool
    {
        return false;
    }

    public function defaultSchema(): string
    {
        return '';
    }

    public function formatVersion(mixed $result): string
    {
        // ClickHouse HTTP client returns results as arrays of arrays, not stdClass
        if (is_array($result) && isset($result[0])) {
            $row = $result[0];
            if (is_array($row)) {
                $version = $row['version'] ?? $row['version()'] ?? null;
                if ($version) return 'ClickHouse ' . $version;
            }
            if (is_object($row)) {
                $version = $row->version ?? null;
                if ($version) return 'ClickHouse ' . $version;
            }
        }
        $fallback = parent::formatVersion($result);
        return $fallback !== 'Unknown' ? 'ClickHouse ' . $fallback : $fallback;
    }

    public function getConnectionOptions(array $connection): array
    {
        // bavix/laravel-clickhouse driver expects these fields at the top level
        $connection['timeout_connect'] = $connection['connection_timeout'] ?? 5;
        $connection['timeout_query'] = $connection['connection_timeout'] ?? 5;
        $connection['https'] = (int)($connection['port'] ?? 8123) === 8443;
        $connection['retries'] = 0;

        // Nested options for the underlying HTTP client
        $connection['options'] = [
            'database' => $connection['database'] ?? 'default',
            'connect_timeout' => $connection['connection_timeout'] ?? 5,
            'enable_http_compression' => 1,
        ];

        // Clean up keys not used by ClickHouse driver
        unset($connection['connection_timeout'], $connection['ssl_mode'], $connection['schema']);

        return $connection;
    }

    public function getDistinctValuesQuery(string $schemaName, string $tableName, string $columnName, int $limit = 20): string
    {
        return "SELECT DISTINCT `{$columnName}` FROM `{$tableName}` WHERE `{$columnName}` IS NOT NULL LIMIT {$limit}";
    }

    public function getTablePreviewQuery(string $schemaName, string $tableName, int $limit = 5): string
    {
        return "SELECT * FROM `{$tableName}` LIMIT {$limit}";
    }
}
