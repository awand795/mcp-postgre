<?php

namespace App\Services\Database;

/**
 * SqlServerAdapter
 *
 * Metadata queries for Microsoft SQL Server.
 */
class SqlServerAdapter extends DriverAdapter
{
    public function driver(): string
    {
        return 'sqlsrv';
    }

    public function listTablesQuery(): string
    {
        return "
            SELECT
                t.name as table_name,
                s.name as table_schema,
                CAST(ep.value AS NVARCHAR(MAX)) as description,
                CASE t.type WHEN 'U' THEN 'BASE TABLE' WHEN 'V' THEN 'VIEW' ELSE t.type_desc END as table_type
            FROM sys.tables t
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            LEFT JOIN sys.extended_properties ep ON ep.major_id = t.object_id
                AND ep.minor_id = 0
                AND ep.name = 'MS_Description'
            WHERE t.type IN ('U', 'V')
            UNION ALL
            SELECT
                v.name as table_name,
                s.name as table_schema,
                CAST(ep.value AS NVARCHAR(MAX)) as description,
                'VIEW' as table_type
            FROM sys.views v
            INNER JOIN sys.schemas s ON v.schema_id = s.schema_id
            LEFT JOIN sys.extended_properties ep ON ep.major_id = v.object_id
                AND ep.minor_id = 0
                AND ep.name = 'MS_Description'
            ORDER BY table_type DESC, table_schema, table_name
        ";
    }

    public function listSchemasQuery(): string
    {
        return "
            SELECT name as schema_name
            FROM sys.schemas
            WHERE name NOT IN ('sys', 'INFORMATION_SCHEMA')
            ORDER BY name
        ";
    }

    public function describeTableQuery(): string
    {
        return "
            SELECT
                c.name as column_name,
                TYPE_NAME(c.user_type_id) as data_type,
                CASE c.is_nullable WHEN 1 THEN 'YES' ELSE 'NO' END as is_nullable,
                OBJECT_DEFINITION(c.default_object_id) as column_default
            FROM sys.columns c
            INNER JOIN sys.tables t ON c.object_id = t.object_id
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            WHERE t.name = ? AND s.name = ?
            ORDER BY c.column_id
        ";
    }

    public function describeTableWithKeysQuery(): string
    {
        return "
            SELECT 
                c.name as column_name,
                TYPE_NAME(c.user_type_id) as data_type,
                CASE c.is_nullable WHEN 1 THEN 'YES' ELSE 'NO' END as is_nullable,
                OBJECT_DEFINITION(c.default_object_id) as column_default,
                fk.referenced_table_name AS foreign_key_table,
                fk.referenced_column_name AS foreign_key_column,
                CAST(ep.value AS NVARCHAR(MAX)) as description
            FROM sys.columns c
            INNER JOIN sys.tables t ON c.object_id = t.object_id
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            LEFT JOIN sys.extended_properties ep ON ep.major_id = t.object_id 
                AND ep.minor_id = c.column_id
                AND ep.name = 'MS_Description'
            LEFT JOIN (
                SELECT 
                    parent_table.name AS table_name,
                    parent_column.name AS column_name,
                    referenced_table.name AS referenced_table_name,
                    referenced_column.name AS referenced_column_name
                FROM sys.foreign_key_columns fkc
                INNER JOIN sys.tables parent_table ON fkc.parent_object_id = parent_table.object_id
                INNER JOIN sys.columns parent_column ON fkc.parent_object_id = parent_column.object_id AND fkc.parent_column_id = parent_column.column_id
                INNER JOIN sys.tables referenced_table ON fkc.referenced_object_id = referenced_table.object_id
                INNER JOIN sys.columns referenced_column ON fkc.referenced_object_id = referenced_column.object_id AND fkc.referenced_column_id = referenced_column.column_id
            ) fk ON t.name = fk.table_name AND c.name = fk.column_name
            WHERE t.name = ? AND s.name = ?
            ORDER BY c.column_id
        ";
    }

    public function getViewDefinitionQuery(): string
    {
        return "
            SELECT definition
            FROM sys.sql_modules
            WHERE object_id = OBJECT_ID(?)
        ";
    }

    public function getTableIndexesQuery(): string
    {
        return "
            SELECT 
                i.name AS index_name,
                c.name AS column_name,
                i.is_primary_key AS is_primary,
                i.is_unique AS is_unique
            FROM sys.indexes i
            INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
            INNER JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
            INNER JOIN sys.tables t ON i.object_id = t.object_id
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            WHERE t.name = ? AND s.name = ?
            ORDER BY i.name, ic.key_ordinal
        ";
    }

    public function searchSchemaQuery(): string
    {
        return "
            SELECT TOP 100
                s.name as table_schema, 
                t.name as table_name, 
                c.name as column_name, 
                CAST(ep.value AS NVARCHAR(MAX)) AS description
            FROM sys.columns c
            INNER JOIN sys.tables t ON c.object_id = t.object_id
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            LEFT JOIN sys.extended_properties ep ON ep.major_id = t.object_id 
                AND ep.minor_id = c.column_id
                AND ep.name = 'MS_Description'
            WHERE (t.name LIKE ? OR c.name LIKE ? OR CAST(ep.value AS NVARCHAR(MAX)) LIKE ?)
            AND s.name NOT IN ('sys', 'INFORMATION_SCHEMA')
            ORDER BY s.name, t.name
        ";
    }

    public function usesSchema(): bool
    {
        return true;
    }

    public function defaultSchema(): string
    {
        return 'dbo';
    }

    public function getConnectionOptions(array $connection): array
    {
        // SQL Server specific options
        $connection['TrustServerCertificate'] = true;

        // SSL mode mapping
        if (!empty($connection['ssl_mode'])) {
            $connection['Encrypt'] = in_array($connection['ssl_mode'], ['require', 'verify-full']);
        }

        // Default schema for SQL Server
        $connection['search_path'] = [$connection['schema'] ?? 'dbo'];

        unset($connection['ssl_mode']);

        return $connection;
    }

    public function formatVersion(mixed $result): string
    {
        $version = $result[0]->version ?? $result[0]->{'(No column name)'} ?? $result[0]->{'@@version'} ?? 'Unknown';
        if (preg_match('/Microsoft SQL Server\s+(.*)/', $version, $matches)) {
            return 'SQL Server ' . trim($matches[1]);
        }
        return $version;
    }
}
