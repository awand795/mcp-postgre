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
        // PENTING: Alias eksplisit lowercase agar kompatibel dengan semua server MySQL
        // (beberapa cloud provider seperti Aiven mengembalikan nama kolom UPPERCASE).
        return "
            SELECT 
                TABLE_NAME      AS table_name,
                TABLE_SCHEMA    AS table_schema,
                TABLE_COMMENT   AS description,
                TABLE_TYPE      AS table_type
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ?
            AND TABLE_TYPE IN ('BASE TABLE', 'VIEW')
            ORDER BY TABLE_NAME
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
        // PENTING: Gunakan 2 parameter (table_name, table_schema) agar tidak bergantung DATABASE()
        // yang tidak reliable pada dynamic/temporary connections.
        // Pemanggil wajib supply: [$tableName, $databaseName]
        return "
            SELECT
                COLUMN_NAME     AS column_name,
                DATA_TYPE       AS data_type,
                IS_NULLABLE     AS is_nullable,
                COLUMN_DEFAULT  AS column_default
            FROM information_schema.COLUMNS
            WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?
            ORDER BY ORDINAL_POSITION
        ";
    }

    public function describeTableWithKeysQuery(): string
    {
        // PENTING: Alias eksplisit lowercase untuk kompatibilitas lintas server MySQL.
        // Parameter: [$tableName, $tableSchema]
        return "
            SELECT
                c.COLUMN_NAME                       AS column_name,
                c.DATA_TYPE                         AS data_type,
                c.IS_NULLABLE                       AS is_nullable,
                c.COLUMN_DEFAULT                    AS column_default,
                kcu.REFERENCED_TABLE_NAME           AS foreign_key_table,
                kcu.REFERENCED_COLUMN_NAME          AS foreign_key_column,
                c.COLUMN_COMMENT                    AS description
            FROM information_schema.COLUMNS c
            LEFT JOIN information_schema.KEY_COLUMN_USAGE kcu
                ON  c.TABLE_NAME   = kcu.TABLE_NAME
                AND c.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                AND c.COLUMN_NAME  = kcu.COLUMN_NAME
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            WHERE c.TABLE_NAME = ? AND c.TABLE_SCHEMA = ?
            ORDER BY c.ORDINAL_POSITION
        ";
    }

    public function getViewDefinitionQuery(): string
    {
        return "
            SELECT VIEW_DEFINITION AS view_definition
            FROM information_schema.VIEWS
            WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?
        ";
    }

    public function getTableIndexesQuery(): string
    {
        // Alias eksplisit lowercase agar kompatibel dengan semua server MySQL.
        return "
            SELECT
                INDEX_NAME                              AS index_name,
                COLUMN_NAME                             AS column_name,
                IF(INDEX_NAME = 'PRIMARY', 1, 0)        AS is_primary,
                IF(NON_UNIQUE = 0, 1, 0)                AS is_unique
            FROM information_schema.STATISTICS
            WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ";
    }

    public function searchSchemaQuery(): string
    {
        // Alias eksplisit lowercase agar kompatibel dengan semua server MySQL.
        return "
            SELECT
                TABLE_SCHEMA    AS table_schema,
                TABLE_NAME      AS table_name,
                COLUMN_NAME     AS column_name,
                COLUMN_COMMENT  AS description
            FROM information_schema.COLUMNS
            WHERE (TABLE_NAME LIKE ? OR COLUMN_NAME LIKE ? OR COLUMN_COMMENT LIKE ?)
            AND TABLE_SCHEMA NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
            ORDER BY TABLE_SCHEMA, TABLE_NAME
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

        $pdoOptions = is_array($connection['options'] ?? null) ? $connection['options'] : [];

        // Add connection timeout
        if (!empty($connection['connection_timeout'])) {
            $pdoOptions[\PDO::ATTR_TIMEOUT] = (int) $connection['connection_timeout'];
        }
        unset($connection['connection_timeout']);

        // SSL handling untuk cloud providers (Aiven, PlanetScale, Railway, dll)
        // Aiven MySQL wajib SSL tapi tidak perlu verify CA cert
        $sslMode = $connection['ssl_mode'] ?? '';
        if (!empty($sslMode)) {
            // Matikan verifikasi server cert — Aiven pakai self-signed CA
            // yang tidak ada di system trust store server
            $pdoOptions[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        unset($connection['ssl_mode']);

        $connection['options'] = $pdoOptions;

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
     * Override: MySQL uses backticks for identifiers, not double quotes.
     * Also MySQL uses database name as schema, not a separate schema namespace.
     */
    public function getDistinctValuesQuery(string $schemaName, string $tableName, string $columnName, int $limit = 20): string
    {
        // MySQL: tidak ada konsep schema terpisah, tabel cukup disebut dengan nama tabel saja
        // (koneksi sudah diarahkan ke database yang benar via config).
        return "SELECT DISTINCT `{$columnName}` FROM `{$tableName}` WHERE `{$columnName}` IS NOT NULL LIMIT {$limit}";
    }

    /**
     * Override: MySQL uses backticks for table preview.
     */
    public function getTablePreviewQuery(string $schemaName, string $tableName, int $limit = 5): string
    {
        return "SELECT * FROM `{$tableName}` LIMIT {$limit}";
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
