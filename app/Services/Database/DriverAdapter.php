<?php

namespace App\Services\Database;

/**
 * DriverAdapter (Abstract)
 *
 * Defines the contract for database-specific metadata queries.
 * Each database driver must implement these methods to return
 * standardized table, schema, and column information.
 */
abstract class DriverAdapter
{
    /**
     * Get the driver type name
     */
    abstract public function driver(): string;

    /**
     * SQL query to list all tables and views across all schemas
     *
     * Returns columns: table_name, table_schema, description, table_type
     */
    abstract public function listTablesQuery(): string;

    /**
     * SQL query to list all schemas in the database
     *
     * Returns column: schema_name
     */
    abstract public function listSchemasQuery(): string;

    /**
     * SQL query to describe columns of a specific table
     *
     * Returns columns: column_name, data_type, is_nullable, column_default
     *
     * @param string $tableName Table name (will be bound as parameter)
     * @param string|null $schemaName Schema name (will be bound as parameter)
     */
    abstract public function describeTableQuery(): string;

    /**
     * Check if this driver uses the schema concept (vs database-as-schema)
     */
    public function usesSchema(): bool
    {
        return true;
    }

    /**
     * Get default schema name for this driver
     */
    public function defaultSchema(): string
    {
        return 'public';
    }

    /**
     * Get connection-specific configuration options
     * Subclasses can override to add driver-specific options
     */
    public function getConnectionOptions(array $connection): array
    {
        return $connection;
    }

    /**
     * Format version string from query result
     */
    public function formatVersion(mixed $result): string
    {
        return $result->version ?? $result[0]->version ?? 'Unknown';
    }

    /**
     * Sanitize SQL query for this driver (basic protection)
     * Subclasses can override for driver-specific sanitization
     */
    public function sanitizeQuery(string $sql): string
    {
        return rtrim(trim($sql), ';');
    }

    /**
     * Check if a column name pattern suggests monetary/currency data
     */
    public function isCurrencyColumn(string $columnName): bool
    {
        $moneyPatterns = [
            'total_netto', 'total_dpp', 'harga', 'price',
            'nominal', 'nilai', 'amount', 'biaya', 'fee',
            'ongkir', 'pajak', 'tax', 'diskon', 'discount',
            'laba', 'profit', 'cogs', 'gpn', 'hpp', 'netto',
            'dpp', 'saldo', 'revenue', 'omzet', 'income'
        ];

        $lowCol = strtolower($columnName);
        foreach ($moneyPatterns as $pattern) {
            if (str_contains($lowCol, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
