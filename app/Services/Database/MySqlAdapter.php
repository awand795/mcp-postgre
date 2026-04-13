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
        return "
            SELECT
                t.table_name,
                t.table_schema,
                '' as description,
                t.table_type
            FROM information_schema.tables t
            WHERE t.table_schema = DATABASE()
            AND t.table_type IN ('BASE TABLE', 'VIEW')
            ORDER BY t.table_type DESC, t.table_name
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
        $version = $result[0]->version ?? 'Unknown';
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
