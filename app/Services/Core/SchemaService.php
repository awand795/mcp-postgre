<?php

namespace App\Services\Core;

use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SchemaService
 *
 * Handles schema discovery, table descriptions, relationship analysis,
 * index suggestions, and data quality checks.
 */
class SchemaService extends BaseService
{
    /**
     * Reference to QueryService for RBAC.
     */
    private QueryService $queryService;

    public function __construct(QueryService $queryService)
    {
        $this->queryService = $queryService;
    }

    /**
     * List all accessible tables.
     */
    public function listTables(): string
    {
        $allowed = $this->queryService->getAllowedTables();
        return $this->safeJsonEncode([
            'tables' => $allowed,
            'total'  => count($allowed),
            'schema' => 'sch_mbi',
            'note'   => 'Always prefix table names with "sch_mbi." in queries',
        ]);
    }

    /**
     * Get columns and data types for a specific table.
     */
    public function describeTable(string $tableName): string
    {
        if (empty($tableName)) {
            return $this->errorResponse('table_name is required');
        }

        $allowed = $this->queryService->getAllowedTables();
        if (!in_array($tableName, $allowed)) {
            return $this->errorResponse("Access denied: table '{$tableName}' is not in your allowed tables list.");
        }

        $columns = DB::connection('pgsql_mbi')->select("
            SELECT column_name, data_type, is_nullable
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = 'sch_mbi'
            ORDER BY ordinal_position
        ", [$tableName]);

        if (empty($columns)) {
            return $this->errorResponse("Table '{$tableName}' not found or has no columns.");
        }

        $result = [];
        foreach ($columns as $col) {
            $result[] = [
                'column'   => $col->column_name,
                'type'     => $col->data_type,
                'nullable' => $col->is_nullable,
            ];
        }

        return $this->safeJsonEncode([
            'table'   => $tableName,
            'schema'  => 'sch_mbi',
            'sql_ref' => "sch_mbi.{$tableName}",
            'columns' => $result,
        ]);
    }

    /**
     * Get complete schema overview (capped at 30 cols/table, 20KB JSON limit).
     */
    public function getSchemaInfo(): string
    {
        $allowed = $this->queryService->getAllowedTables();

        if (empty($allowed)) {
            return $this->errorResponse('Anda tidak memiliki izin untuk mengakses data. Silakan hubungi administrator.');
        }

        $placeholders = implode(',', array_fill(0, count($allowed), '?'));

        $results = DB::connection('pgsql_mbi')->select("
            SELECT table_name, column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = 'sch_mbi'
            AND table_name IN ({$placeholders})
            ORDER BY table_name, ordinal_position
        ", $allowed);

        $schema = [];
        foreach ($results as $row) {
            if (!isset($schema[$row->table_name])) {
                $schema[$row->table_name] = [];
            }
            // FIX: Batasi max 30 kolom per tabel agar JSON tidak overflow context AI
            if (count($schema[$row->table_name]) < 30) {
                $schema[$row->table_name][] = $row->column_name . ' (' . $row->data_type . ')';
            }
        }

        // FIX: Cek total ukuran JSON, jika terlalu besar kirim versi ringkas (nama tabel saja)
        $fullJson = $this->safeJsonEncode([
            'schema'       => 'sch_mbi',
            'total_tables' => count($schema),
            'tables'       => $schema,
            'usage_note'   => 'Prefix all table names with "sch_mbi." in SQL queries.',
        ]);

        // Jika > 20KB, kirim versi ringkas: nama tabel + jumlah kolom saja
        if (strlen($fullJson) > 20000) {
            Log::warning('[ToolCallExecutor] getSchemaInfo terlalu besar (' . strlen($fullJson) . ' chars), mengirim versi ringkas.');
            $compact = [];
            foreach ($schema as $tbl => $cols) {
                $compact[$tbl] = count($cols) . ' columns: ' . implode(', ', array_slice($cols, 0, 5)) . (count($cols) > 5 ? '...' : '');
            }
            return $this->safeJsonEncode([
                'schema'       => 'sch_mbi',
                'total_tables' => count($compact),
                'tables'       => $compact,
                'usage_note'   => 'Schema ringkas karena terlalu besar. Gunakan describe_table untuk detail kolom lengkap.',
            ]);
        }

        return $fullJson;
    }

    /**
     * Discover foreign key relationships and table dependencies.
     */
    public function analyzeRelationships(string $tableName = ''): string
    {
        try {
            $allowed = $this->queryService->getAllowedTables();
            if (empty($allowed)) {
                return $this->errorResponse('Anda tidak memiliki izin untuk mengakses data.');
            }

            $relationships = [];
            $tablesToAnalyze = !empty($tableName) ? [$tableName] : $allowed;

            foreach ($tablesToAnalyze as $table) {
                if (!in_array($table, $allowed)) continue;

                // Explicit Foreign Keys
                $fks = DB::connection('pgsql_mbi')->select("
                    SELECT
                        kcu.column_name,
                        ccu.table_name AS foreign_table_name,
                        ccu.column_name AS foreign_column_name
                    FROM information_schema.table_constraints AS tc
                    JOIN information_schema.key_column_usage AS kcu
                        ON tc.constraint_name = kcu.constraint_name
                        AND tc.table_schema = kcu.table_schema
                    JOIN information_schema.constraint_column_usage AS ccu
                        ON ccu.constraint_name = tc.constraint_name
                        AND ccu.table_schema = tc.table_schema
                    WHERE tc.constraint_type = 'FOREIGN KEY'
                    AND tc.table_schema = 'sch_mbi'
                    AND tc.table_name = ?
                ", [$table]);

                if (!empty($fks)) {
                    $relationships[$table] = [
                        'foreign_keys' => array_map(fn($fk) => [
                            'column' => $fk->column_name,
                            'references' => "sch_mbi.{$fk->foreign_table_name}.{$fk->foreign_column_name}",
                        ], $fks)
                    ];
                }

                // Implicit Relationships (naming pattern matching)
                $columns = DB::connection('pgsql_mbi')->select("
                    SELECT column_name FROM information_schema.columns
                    WHERE table_schema = 'sch_mbi' AND table_name = ?
                ", [$table]);

                foreach ($columns as $col) {
                    $colName = $col->column_name;
                    if (preg_match('/^(.*)_id$/', $colName, $matches)) {
                        $potentialTable = $matches[1];
                        // Try to find matching table
                        foreach ($allowed as $allowedTable) {
                            if (stripos($allowedTable, $potentialTable) !== false && $allowedTable !== $table) {
                                if (!isset($relationships[$table])) {
                                    $relationships[$table] = ['foreign_keys' => [], 'implicit_relationships' => []];
                                }
                                $relationships[$table]['implicit_relationships'][] = [
                                    'column' => $colName,
                                    'likely_references' => "sch_mbi.{$allowedTable}",
                                    'confidence' => 'MEDIUM (naming pattern match)',
                                ];
                                break;
                            }
                        }
                    }
                }
            }

            return $this->safeJsonEncode([
                'schema' => 'sch_mbi',
                'total_relationships' => count($relationships),
                'relationships' => $relationships,
                'usage_note' => 'Use these relationships to write accurate JOIN queries.',
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to analyze relationships: ' . $e->getMessage());
        }
    }

    /**
     * Suggest missing indexes based on table structure.
     */
    public function suggestIndexes(string $tableName, string $queryPattern = ''): string
    {
        if (empty($tableName)) {
            return $this->errorResponse('table_name is required');
        }

        $allowed = $this->queryService->getAllowedTables();
        if (!in_array($tableName, $allowed)) {
            return $this->errorResponse("Access denied: table '{$tableName}' is not in your allowed list.");
        }

        try {
            // Get existing indexes
            $existingIndexes = DB::connection('pgsql_mbi')->select("
                SELECT
                    i.relname AS index_name,
                    ix.indisunique AS is_unique,
                    array_agg(a.attname) AS columns
                FROM pg_class t,
                     pg_class i,
                     pg_index ix,
                     pg_attribute a
                WHERE t.oid = ix.indrelid
                    AND i.oid = ix.indexrelid
                    AND a.attrelid = t.oid
                    AND a.attnum = ANY(ix.indkey)
                    AND t.relkind = 'r'
                    AND t.relname = ?
                    AND t.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'sch_mbi')
                GROUP BY i.relname, ix.indisunique
                ORDER BY i.relname
            ", [$tableName]);

            $indexedCols = [];
            foreach ($existingIndexes as $idx) {
                foreach ($idx->columns as $col) {
                    $indexedCols[] = $col;
                }
            }
            $indexedCols = array_unique($indexedCols);

            // Get columns
            $columns = DB::connection('pgsql_mbi')->select("
                SELECT column_name, data_type
                FROM information_schema.columns
                WHERE table_schema = 'sch_mbi' AND table_name = ?
                ORDER BY ordinal_position
            ", [$tableName]);

            // Analyze column cardinality
            $suggestions = [];
            $commonFilterCols = ['tanggal', 'periode_tahun', 'periode_bulan', 'nama_cabang', 'nama_regional', 'nama_pelanggan', 'nama_barang'];

            foreach ($columns as $col) {
                $colName = $col->column_name;
                if (in_array($colName, $indexedCols)) continue;

                try {
                    $totalCount = DB::connection('pgsql_mbi')->select("SELECT COUNT(*) AS cnt FROM sch_mbi.{$tableName}")[0]->cnt;
                    $distinctCount = DB::connection('pgsql_mbi')->select("SELECT COUNT(DISTINCT \"{$colName}\") AS cnt FROM sch_mbi.{$tableName}")[0]->cnt;

                    $selectivity = $totalCount > 0 ? $distinctCount / $totalCount : 0;

                    if ($selectivity > 0.1 || in_array($colName, $commonFilterCols)) {
                        $suggestions[] = [
                            'column' => $colName,
                            'selectivity' => round($selectivity, 3),
                            'distinct_values' => (int) $distinctCount,
                            'priority' => $selectivity > 0.5 ? 'HIGH' : ($selectivity > 0.2 ? 'MEDIUM' : 'LOW'),
                            'recommendation' => "CREATE INDEX idx_{$tableName}_{$colName} ON sch_mbi.{$tableName} (\"{$colName}\");",
                        ];
                    }
                } catch (\Throwable $e) {
                    // Skip if column can't be analyzed
                }
            }

            return $this->safeJsonEncode([
                'table' => $tableName,
                'existing_indexes' => count($existingIndexes),
                'index_details' => $existingIndexes,
                'suggestions' => $suggestions,
                'total_suggestions' => count($suggestions),
                'usage_note' => 'Review suggestions before creating indexes. HIGH priority = most impact. Consult DBA before production changes.',
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to analyze indexes: ' . $e->getMessage());
        }
    }

    /**
     * Perform comprehensive data quality checks.
     */
    public function checkDataQuality(string $tableName, string $checkType = 'all', array $keyColumns = []): string
    {
        if (empty($tableName)) {
            return $this->errorResponse('table_name is required.');
        }

        $allowed = $this->queryService->getAllowedTables();
        if (!in_array($tableName, $allowed)) {
            return $this->errorResponse("Access denied: table '{$tableName}' is not in your allowed list.");
        }

        try {
            $results = [];

            // Get columns
            $columns = DB::connection('pgsql_mbi')->select("
                SELECT column_name, data_type
                FROM information_schema.columns
                WHERE table_schema = 'sch_mbi' AND table_name = ?
                ORDER BY ordinal_position
            ", [$tableName]);

            $columnNames = array_map(fn($c) => $c->column_name, $columns);

            // 1. NULL pattern analysis
            if ($checkType === 'all' || $checkType === 'nulls') {
                $nullAnalysis = [];
                $totalRows = DB::connection('pgsql_mbi')->select("SELECT COUNT(*) AS cnt FROM sch_mbi.{$tableName}")[0]->cnt;

                foreach ($columnNames as $col) {
                    $nullCount = DB::connection('pgsql_mbi')->select("
                        SELECT COUNT(*) AS cnt FROM sch_mbi.{$tableName} WHERE \"{$col}\" IS NULL
                    ")[0]->cnt;

                    if ($nullCount > 0) {
                        $nullAnalysis[] = [
                            'column' => $col,
                            'null_count' => (int)$nullCount,
                            'null_percentage' => $totalRows > 0 ? round(($nullCount / $totalRows) * 100, 2) : 0,
                            'severity' => ($nullCount / $totalRows) > 0.5 ? 'HIGH' : (($nullCount / $totalRows) > 0.2 ? 'MEDIUM' : 'LOW'),
                        ];
                    }
                }

                $results['null_analysis'] = [
                    'total_rows' => (int)$totalRows,
                    'columns_with_nulls' => count($nullAnalysis),
                    'details' => $nullAnalysis,
                ];
            }

            // 2. Duplicate analysis
            if ($checkType === 'all' || $checkType === 'duplicates') {
                $duplicateAnalysis = [];
                $checkCols = !empty($keyColumns) ? $keyColumns : ['id', 'kode'];

                foreach ($checkCols as $col) {
                    if (!in_array($col, $columnNames)) continue;

                    $duplicates = DB::connection('pgsql_mbi')->select("
                        SELECT \"{$col}\", COUNT(*) AS cnt
                        FROM sch_mbi.{$tableName}
                        WHERE \"{$col}\" IS NOT NULL
                        GROUP BY \"{$col}\"
                        HAVING COUNT(*) > 1
                        ORDER BY cnt DESC
                        LIMIT 10
                    ");

                    if (!empty($duplicates)) {
                        $duplicateAnalysis[] = [
                            'column' => $col,
                            'duplicate_count' => count($duplicates),
                            'worst_offenders' => $duplicates,
                        ];
                    }
                }

                $results['duplicate_analysis'] = $duplicateAnalysis;
            }

            // 3. Consistency checks
            if ($checkType === 'all' || $checkType === 'consistency') {
                $consistencyChecks = [];

                // Check for date columns with future dates
                $dateColumns = array_filter($columnNames, function($col) use ($columns) {
                    $colInfo = collect($columns)->firstWhere('column_name', $col);
                    return $colInfo && in_array(strtolower($colInfo->data_type), ['date', 'timestamp', 'timestamp without time zone', 'timestamp with time zone']);
                });

                foreach ($dateColumns as $dateCol) {
                    $futureDates = DB::connection('pgsql_mbi')->select("
                        SELECT COUNT(*) AS cnt FROM sch_mbi.{$tableName}
                        WHERE \"{$dateCol}\" > NOW()
                    ")[0]->cnt;

                    if ((int)$futureDates > 0) {
                        $consistencyChecks[] = [
                            'check' => 'future_dates',
                            'column' => $dateCol,
                            'count' => (int)$futureDates,
                            'severity' => 'MEDIUM',
                        ];
                    }
                }

                // Check for negative values in typically-positive columns
                $positiveColumns = array_filter($columnNames, function($col) {
                    $lowerCol = strtolower($col);
                    return preg_match('/(total|jumlah|qty|harga|profit|gpn|dpp|netto)/', $lowerCol);
                });

                foreach ($positiveColumns as $posCol) {
                    $negatives = DB::connection('pgsql_mbi')->select("
                        SELECT COUNT(*) AS cnt FROM sch_mbi.{$tableName}
                        WHERE \"{$posCol}\" < 0
                    ")[0]->cnt;

                    if ((int)$negatives > 0) {
                        $consistencyChecks[] = [
                            'check' => 'negative_values',
                            'column' => $posCol,
                            'count' => (int)$negatives,
                            'severity' => 'LOW',
                            'note' => 'May be valid for returns/cancellations',
                        ];
                    }
                }

                $results['consistency_checks'] = $consistencyChecks;
            }

            // Overall quality score
            $issuesCount = 0;
            if (isset($results['null_analysis'])) {
                $issuesCount += count(array_filter($results['null_analysis']['details'] ?? [], fn($d) => $d['severity'] === 'HIGH'));
            }
            if (isset($results['duplicate_analysis'])) {
                $issuesCount += count($results['duplicate_analysis']);
            }
            if (isset($results['consistency_checks'])) {
                $issuesCount += count(array_filter($results['consistency_checks'] ?? [], fn($c) => $c['severity'] === 'HIGH'));
            }

            $qualityScore = max(0, 100 - ($issuesCount * 10));

            return $this->safeJsonEncode([
                'table' => $tableName,
                'quality_score' => $qualityScore,
                'severity_summary' => [
                    'high' => $issuesCount,
                    'medium' => 0,
                    'low' => 0,
                ],
                'results' => $results,
                'recommendation' => $qualityScore >= 80 ? 'Data quality is acceptable.' : ($qualityScore >= 60 ? 'Some data quality issues found — review recommended.' : 'Significant data quality issues detected — immediate review needed.'),
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to check data quality: ' . $e->getMessage());
        }
    }
}
