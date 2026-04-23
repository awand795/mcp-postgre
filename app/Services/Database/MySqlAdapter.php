<?php

namespace App\Services\Database;

/**
 * MySqlAdapter
 *
 * Metadata queries for MySQL/MariaDB databases.
 * Note: MySQL uses database name where PostgreSQL uses schema.
 * In MySQL, information_schema.tables has TABLE_SCHEMA = database name.
 */
class MySqlAdapter extends DriverAdapter
{
    public function driver(): string
    {
        return 'mysql';
    }

    public function listTablesQuery(): string
    {
        // NOTE: DATABASE() kadang tidak reliable pada temporary/dynamic connections.
        // Gunakan placeholder '?' yang akan di-bind dengan nama database saat query dijalankan.
        // Method getTables() di DatabaseConnection akan men-supply parameter ini.
        return "
            SELECT 
                table_name, 
                table_schema, 
                table_comment as description, 
                table_type 
            FROM information_schema.tables 
            WHERE table_schema = ?
            AND table_type IN ('BASE TABLE', 'VIEW')
            ORDER BY table_name
        ";
    }

    public function listSchemasQuery(): string
    {
        // In MySQL, "schemas" are essentially databases
        // Return empty since we list databases differently
        return "
            SELECT SCHEMA_NAME as schema_name
            FROM information_schema.SCHEMATA
            ORDER BY SCHEMA_NAME
        ";
    }

    public function describeTableQuery(): string
    {
        return "
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = DATABASE()
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
                kcu.referenced_table_name AS foreign_key_table,
                kcu.referenced_column_name AS foreign_key_column,
                c.column_comment AS description
            FROM information_schema.columns c
            LEFT JOIN information_schema.key_column_usage kcu 
                ON c.table_name = kcu.table_name 
                AND c.table_schema = kcu.table_schema 
                AND c.column_name = kcu.column_name
                AND kcu.referenced_table_name IS NOT NULL
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
                index_name,
                column_name,
                IF(index_name = 'PRIMARY', 1, 0) as is_primary,
                IF(non_unique = 0, 1, 0) as is_unique
            FROM information_schema.statistics
            WHERE table_name = ? AND table_schema = ?
            ORDER BY index_name, seq_in_index
        ";
    }

    public function searchSchemaQuery(): string
    {
        return "
            SELECT 
                table_schema, 
                table_name, 
                column_name, 
                column_comment AS description
            FROM information_schema.columns
            WHERE (table_name LIKE ? OR column_name LIKE ? OR column_comment LIKE ?)
            AND table_schema NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
            ORDER BY table_schema, table_name
            LIMIT 100
        ";
    }

    public function usesSchema(): bool
    {
        return false; // MySQL uses database-as-schema concept
    }

    public function defaultSchema(): string
    {
        return ''; // Not applicable for MySQL
    }

    public function getConnectionOptions(array $connection): array
    {
        // MySQL specific options
        $connection['charset'] = 'utf8mb4';
        $connection['collation'] = 'utf8mb4_unicode_ci';

        // MySQL doesn't use schema, use database directly
        unset($connection['schema'], $connection['search_path']);

        // Add SSL if specified
        if (!empty($connection['ssl_mode'])) {
            $connection['pdo'] = [
                \PDO::MYSQL_ATTR_SSL_CA => $connection['ssl_mode'] === 'require' ? true : null,
            ];
        }

        // Add connection timeout
        if (!empty($connection['connection_timeout'])) {
            $connection['options'] = [
                \PDO::ATTR_TIMEOUT => (int) $connection['connection_timeout'],
            ];
            unset($connection['connection_timeout']);
        }

        unset($connection['ssl_mode']);

        return $connection;
    }

    public function formatVersion(mixed $result): string
    {
        $version = $result[0]->version ?? $result[0]->{'@@version'} ?? 'Unknown';
        if (stripos($version, 'mariadb') !== false) {
            return $version;
        }
        if (preg_match('/(\d+\.\d+\.\d+)/', $version, $matches)) {
            return 'MySQL ' . $matches[1];
        }
        return $version;
    }

    /**
     * Override: MySQL uses database name, so describeTable needs database param
     */
    public function describeTableQueryWithDb(): string
    {
        return "
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = ?
            ORDER BY ordinal_position
        ";
    }
}
