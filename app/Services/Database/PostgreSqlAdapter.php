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

    public function describeTableWithKeysQuery(): string
    {
        return "
            SELECT 
                c.column_name, 
                c.data_type, 
                c.is_nullable, 
                c.column_default,
                ccu.table_name AS foreign_key_table,
                ccu.column_name AS foreign_key_column,
                col_description(pg_class.oid, c.ordinal_position) AS description
            FROM information_schema.columns c
            JOIN pg_class ON pg_class.relname = c.table_name
            JOIN pg_namespace ON pg_namespace.oid = pg_class.relnamespace AND pg_namespace.nspname = c.table_schema
            LEFT JOIN information_schema.key_column_usage kcu 
                ON c.table_name = kcu.table_name 
                AND c.table_schema = kcu.table_schema 
                AND c.column_name = kcu.column_name
            LEFT JOIN information_schema.constraint_column_usage ccu
                ON kcu.constraint_name = ccu.constraint_name
                AND kcu.table_schema = ccu.table_schema
            WHERE c.table_name = ? AND c.table_schema = ?
            ORDER BY c.ordinal_position
        ";
    }

    public function getViewDefinitionQuery(): string
    {
        return "
            SELECT view_definition
            FROM information_schema.views
            WHERE table_name = ? AND table_schema = ?
        ";
    }

    public function getTableIndexesQuery(): string
    {
        return "
            SELECT
                i.relname as index_name,
                a.attname as column_name,
                ix.indisprimary as is_primary,
                ix.indisunique as is_unique
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE t.relname = ? AND n.nspname = ?
            ORDER BY i.relname
        ";
    }

    public function searchSchemaQuery(): string
    {
        return "
            SELECT 
                c.table_schema, 
                c.table_name, 
                c.column_name, 
                obj_description(t.oid) AS description
            FROM information_schema.columns c
            JOIN pg_class t ON t.relname = c.table_name
            JOIN pg_namespace n ON n.oid = t.relnamespace AND n.nspname = c.table_schema
            WHERE (c.table_name ILIKE ? OR c.column_name ILIKE ?)
            AND c.table_schema NOT IN ('pg_catalog', 'information_schema')
            ORDER BY c.table_schema, c.table_name
            LIMIT 100
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
