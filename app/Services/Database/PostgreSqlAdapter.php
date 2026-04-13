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
                n.nspname AS table_schema,
                c.relname AS table_name,
                CASE c.relkind 
                    WHEN 'r' THEN 'BASE TABLE' 
                    WHEN 'v' THEN 'VIEW' 
                    WHEN 'm' THEN 'VIEW' 
                    WHEN 'f' THEN 'FOREIGN TABLE'
                    WHEN 'p' THEN 'PARTITIONED TABLE'
                END AS table_type,
                obj_description(c.oid) AS description
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname NOT IN ('pg_catalog', 'information_schema')
            AND c.relkind IN ('r', 'v', 'm', 'f', 'p')
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
