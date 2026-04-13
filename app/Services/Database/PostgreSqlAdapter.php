<?php

namespace App\Services\Database;

/**
 * PostgreSqlAdapter
 *
 * Metadata queries for PostgreSQL databases.
 */
class PostgreSqlAdapter extends DriverAdapter
{
    public function driver(): string
    {
        return 'pgsql';
    }

    public function listTablesQuery(): string
    {
        return "
            SELECT
                table_name,
                table_schema,
                '' as description,
                table_type
            FROM information_schema.tables
            WHERE table_schema NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
            AND table_type IN ('BASE TABLE', 'VIEW')
            UNION ALL
            SELECT
                matviewname as table_name,
                schemaname as table_schema,
                '' as description,
                'VIEW' as table_type
            FROM pg_matviews
            WHERE schemaname NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
            ORDER BY table_type DESC, table_schema, table_name
        ";
    }

    public function listSchemasQuery(): string
    {
        return "
            SELECT schema_name
            FROM information_schema.schemata
            WHERE schema_name NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
            ORDER BY schema_name
        ";
    }

    public function describeTableQuery(): string
    {
        return "
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = ?
            ORDER BY ordinal_position
        ";
    }

    public function usesSchema(): bool
    {
        return true;
    }

    public function defaultSchema(): string
    {
        return 'public';
    }

    public function getConnectionOptions(array $connection): array
    {
        // Laravel PostgreSQL driver reads 'schema' to set search_path
        $schema = $connection['schema'] ?? 'public';
        $connection['schema'] = $schema;

        // Add SSL mode if specified
        if (!empty($connection['ssl_mode'])) {
            $connection['sslmode'] = $connection['ssl_mode'];
        }

        // Timeout is handled via SET statement_timeout in QueryService, not here
        // (Laravel 'options' key maps to PDO options array, not pg options string)

        // Clean up keys not used by Laravel
        unset($connection['search_path'], $connection['ssl_mode'], $connection['connection_timeout']);

        return $connection;
    }

    public function formatVersion(mixed $result): string
    {
        $version = $result[0]->version ?? 'Unknown';
        // PostgreSQL returns full version string, extract meaningful part
        if (preg_match('/PostgreSQL\s+([\d.]+)/', $version, $matches)) {
            return 'PostgreSQL ' . $matches[1];
        }
        return $version;
    }
}
