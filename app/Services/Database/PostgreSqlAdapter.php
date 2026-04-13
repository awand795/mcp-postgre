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
            SELECT DISTINCT
                t.table_name,
                t.table_schema,
                COALESCE(d.description, '') as description,
                t.table_type
            FROM information_schema.tables t
            LEFT JOIN pg_namespace n ON n.nspname = t.table_schema
            LEFT JOIN pg_class c ON c.relname = t.table_name AND c.relnamespace = n.oid
            LEFT JOIN pg_description d ON d.objoid = c.oid AND d.objsubid = 0
            WHERE t.table_schema NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
            AND t.table_type IN ('BASE TABLE', 'VIEW')
            ORDER BY t.table_type DESC, t.table_schema, t.table_name
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

        // Build PostgreSQL options string for Laravel
        $optionsParts = [];

        if (!empty($connection['connection_timeout'])) {
            $timeoutMs = (int) $connection['connection_timeout'] * 1000;
            $optionsParts[] = "-c statement_timeout={$timeoutMs}";
        }

        if (!empty($optionsParts)) {
            $connection['options'] = implode(' ', $optionsParts);
        } else {
            // Remove options key if empty to avoid Laravel config confusion
            unset($connection['options']);
        }

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
