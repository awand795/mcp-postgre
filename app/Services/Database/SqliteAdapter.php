<?php

namespace App\Services\Database;

/**
 * SqliteAdapter
 *
 * Metadata queries for SQLite databases.
 * SQLite doesn't have schema concept - uses a single file database.
 */
class SqliteAdapter extends DriverAdapter
{
    public function driver(): string
    {
        return 'sqlite';
    }

    public function listTablesQuery(): string
    {
        return "
            SELECT
                name as table_name,
                'main' as table_schema,
                '' as description,
                type as table_type
            FROM sqlite_master
            WHERE type IN ('table', 'view')
            AND name NOT LIKE 'sqlite_%'
            ORDER BY type DESC, name
        ";
    }

    public function listSchemasQuery(): string
    {
        // SQLite doesn't support schemas
        return "SELECT 'main' as schema_name";
    }

    public function describeTableQuery(): string
    {
        // SQLite uses PRAGMA - can't use parameterized query
        // This will be handled differently in the model
        return 'PRAGMA table_info(?)';
    }

    public function describeTableWithKeysQuery(): string
    {
        // SQLite uses PRAGMA foreign_key_list(?) - handled in service
        return 'PRAGMA table_info(?)';
    }

    public function getViewDefinitionQuery(): string
    {
        return "SELECT sql FROM sqlite_master WHERE type = 'view' AND name = ?";
    }

    public function getTableIndexesQuery(): string
    {
        return "PRAGMA index_list(?)";
    }

    public function searchSchemaQuery(): string
    {
        return "
            SELECT 'main' as table_schema, name as table_name, '' as column_name, '' as description
            FROM sqlite_master
            WHERE type = 'table' AND name LIKE ?
            LIMIT 100
        ";
    }

    public function usesSchema(): bool
    {
        return false;
    }

    public function defaultSchema(): string
    {
        return 'main';
    }

    public function getConnectionOptions(array $connection): array
    {
        // SQLite uses file path as database
        // The 'host' field is not used, database field contains the file path
        $connection['database'] = $connection['database'] ?? $connection['host'] ?? ':memory:';
        unset($connection['host'], $connection['port'], $connection['username'], $connection['password']);

        return $connection;
    }

    public function formatVersion(mixed $result): string
    {
        $version = $result[0]->version ?? 'Unknown';
        return 'SQLite ' . $version;
    }
}
