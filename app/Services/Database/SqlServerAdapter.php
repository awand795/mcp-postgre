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
        $version = $result[0]->version ?? $result[0]->{'(No column name)'} ?? 'Unknown';
        if (preg_match('/Microsoft SQL Server\s+(.*)/', $version, $matches)) {
            return 'SQL Server ' . trim($matches[1]);
        }
        return $version;
    }
}
