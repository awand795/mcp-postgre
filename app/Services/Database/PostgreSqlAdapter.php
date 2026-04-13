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
                t.table_name,
                t.table_schema,
                COALESCE(
                    (SELECT description FROM pg_description
                     WHERE objoid = (t.table_schema || '.' || t.table_name)::regclass::oid),
                    ''
                ) as description,
                t.table_type
            FROM information_schema.tables t
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
        $connection['search_path'] = [$connection['schema'] ?? 'public', 'public'];

        // Add SSL mode if specified
        if (!empty($connection['ssl_mode'])) {
            $connection['sslmode'] = $connection['ssl_mode'];
        }

        // Add connection timeout
        if (!empty($connection['connection_timeout'])) {
            $connection['options'] = array_merge(
                $connection['options'] ?? [],
                ['options' => "-c statement_timeout={$connection['connection_timeout']}000"]
            );
        }

        unset($connection['ssl_mode'], $connection['connection_timeout']);

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
