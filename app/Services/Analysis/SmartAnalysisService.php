<?php

namespace App\Services\Analysis;

use App\Services\BaseService;
use App\Services\Core\QueryService;
use App\Services\Core\SchemaService;
use Illuminate\Support\Facades\DB;

/**
 * SmartAnalysisService
 *
 * Handles smart analysis chains, EXPLAIN query plans,
 * and pre-built analysis templates.
 */
class SmartAnalysisService extends BaseService
{
    private QueryService $queryService;
    private SchemaService $schemaService;
    private StatisticalAnalysisService $statisticalService;

    public function __construct(
        QueryService $queryService,
        SchemaService $schemaService,
        StatisticalAnalysisService $statisticalService
    ) {
        $this->queryService = $queryService;
        $this->schemaService = $schemaService;
        $this->statisticalService = $statisticalService;
    }

    // ── smart_analyze ─────────────────────────────────────────────────────
    public function smartAnalyze(
        string $metric,
        string $period,
        string $breakdownBy = '',
        array $analysisTypes = ['trend', 'anomaly', 'comparison'],
        int $topN = 10
    ): string {
        if (empty($metric) || empty($period)) {
            return $this->errorResponse('Both metric and period are required.');
        }

        try {
            $results = [
                'metric' => $metric,
                'period' => $period,
                'breakdown_by' => $breakdownBy,
                'analyses_run' => [],
                'errors' => [],
            ];

            // Step 1: Discover relevant tables
            $schemaInfo = $this->decodeJson($this->schemaService->getSchemaInfo(), true);
            $relevantTables = [];

            if (isset($schemaInfo['tables']) && is_array($schemaInfo['tables'])) {
                $metricKeywords = $this->getMetricTableMapping($metric);
                foreach ($schemaInfo['tables'] as $tbl => $cols) {
                    foreach ($metricKeywords as $mWord) {
                        if (stripos($tbl, $mWord) !== false) {
                            $relevantTables[] = $tbl;
                            break;
                        }
                    }
                }
            }

            if (empty($relevantTables)) {
                // Fallback: try common sales/inventory tables
                $relevantTables = ['view_data_penjualan_rinci_mbi', 'data_penjualan_mbi', 'data_stok_mbi'];
            }

            // Step 2: Build and execute query
            $primaryTable = $relevantTables[0];
            $sql = $this->buildSmartQuery($metric, $period, $primaryTable, $breakdownBy, $topN);

            if ($sql) {
                $queryResult = $this->decodeJson($this->queryService->executeQuery($sql, "Smart analysis: {$metric} - {$period}"), true);

                if (isset($queryResult['error'])) {
                    $results['errors'][] = "Query execution failed: " . $queryResult['error'];
                } else {
                    $data = $queryResult['rows'] ?? [];
                    $results['data_summary'] = [
                        'total_rows' => $queryResult['rows_returned'] ?? 0,
                        'columns' => $queryResult['columns'] ?? [],
                    ];

                    if (!empty($data)) {
                        $valueCol = $this->detectValueColumn($data, $metric);
                        $periodCol = $this->detectPeriodColumn($data);
                        $labelCol = $breakdownBy ? $this->detectColumnByName($data, $breakdownBy) : null;

                        // Step 3: Run requested analyses
                        if (in_array('trend', $analysisTypes) && $valueCol && $periodCol) {
                            $trendResult = $this->decodeJson($this->statisticalService->analyzeTrend($data, $valueCol, $periodCol), true);
                            $results['trend'] = $trendResult;
                            $results['analyses_run'][] = 'trend';
                        }

                        if (in_array('anomaly', $analysisTypes) && $valueCol) {
                            $anomalyResult = $this->decodeJson($this->statisticalService->detectAnomalies($data, $valueCol), true);
                            $results['anomalies'] = $anomalyResult;
                            $results['analyses_run'][] = 'anomaly';
                        }

                        if (in_array('comparison', $analysisTypes) && $valueCol && $periodCol) {
                            $periods = collect($data)->pluck($periodCol)->unique()->sort()->values();
                            if ($periods->count() >= 2) {
                                $comparisonResult = $this->decodeJson($this->statisticalService->comparePeriods(
                                    $data, $valueCol, $periodCol,
                                    $periods[$periods->count() - 2],
                                    $periods[$periods->count() - 1]
                                ), true);
                                $results['comparison'] = $comparisonResult;
                                $results['analyses_run'][] = 'comparison';
                            }
                        }

                        if (in_array('forecast', $analysisTypes) && $valueCol && $periodCol) {
                            $forecastResult = $this->decodeJson($this->statisticalService->forecastMetric($data, $valueCol, $periodCol, 3, true), true);
                            $results['forecast'] = $forecastResult;
                            $results['analyses_run'][] = 'forecast';
                        }

                        // Top N ranking
                        if ($valueCol && $labelCol) {
                            $ranking = collect($data)
                                ->sortByDesc($valueCol)
                                ->take($topN)
                                ->map(fn($row) => [
                                    $labelCol => $row[$labelCol] ?? 'Unknown',
                                    $valueCol => $row[$valueCol] ?? 0,
                                ])
                                ->values()
                                ->toArray();
                            $results['top_ranking'] = $ranking;
                        }
                    }
                }
            }

            $results['next_steps'] = 'Review the analysis results above. Call generate_business_insight to synthesize findings into an executive narrative.';

            return $this->safeJsonEncode($results);

        } catch (\Throwable $e) {
            return $this->errorResponse('Smart analysis failed: ' . $e->getMessage());
        }
    }

    // ── explain_query_plan ────────────────────────────────────────────────
    public function explainQueryPlan(string $sql, bool $suggestions = true): string
    {
        if (empty($sql)) {
            return $this->errorResponse('sql is required.');
        }

        // Security: same SELECT-only check as executeQuery
        if (!preg_match('/^\s*SELECT\b/i', trim($sql))) {
            return $this->errorResponse('Hanya query SELECT yang diizinkan.');
        }

        try {
            // Run EXPLAIN ANALYZE
            $explainResult = DB::connection('pgsql_mbi')->select("EXPLAIN ANALYZE {$sql}");

            // Parse the EXPLAIN output
            $planLines = [];
            $totalCost = null;
            $actualTime = null;
            $rowsProcessed = null;
            $indexUsed = false;
            $seqScanDetected = false;

            foreach ($explainResult as $row) {
                $line = is_object($row) ? ($row->{'QUERY PLAN'} ?? json_encode($row)) : $row;
                $planLines[] = $line;

                $lineLower = strtolower($line);
                if (preg_match('/actual\s+time=\S+\.\S+\.\.(\S+)/', $line, $matches)) {
                    $actualTime = (float)$matches[1];
                }
                if (preg_match('/rows=(\d+)/', $line, $matches)) {
                    $rowsProcessed = (int)$matches[1];
                }
                if (stripos($lineLower, 'index') !== false || stripos($lineLower, 'idx_') !== false) {
                    $indexUsed = true;
                }
                if (stripos($lineLower, 'seq scan') !== false) {
                    $seqScanDetected = true;
                }
                if (preg_match('/Planning\s+Time:\s+([\d.]+)/', $line, $matches)) {
                    $planningTime = (float)$matches[1];
                }
                if (preg_match('/Execution\s+Time:\s+([\d.]+)/', $line, $matches)) {
                    $actualTime = (float)$matches[1];
                }
            }

            $optimizationSuggestions = [];

            if ($seqScanDetected && $suggestions) {
                $optimizationSuggestions[] = [
                    'issue' => 'Sequential scan detected',
                    'severity' => 'HIGH',
                    'suggestion' => 'Consider adding an index on the filtered/joined columns to avoid full table scans.',
                    'action' => 'Use suggest_indexes tool on the relevant table to get specific index recommendations.',
                ];
            }

            if (!$indexUsed && $suggestions) {
                $optimizationSuggestions[] = [
                    'issue' => 'No index usage detected',
                    'severity' => 'MEDIUM',
                    'suggestion' => 'Query is not using any indexes. Review WHERE and JOIN conditions.',
                    'action' => 'Add indexes on columns used in WHERE, JOIN, and ORDER BY clauses.',
                ];
            }

            if ($actualTime !== null && $actualTime > 1000) {
                $optimizationSuggestions[] = [
                    'issue' => 'Slow execution time (' . round($actualTime, 2) . ' ms)',
                    'severity' => 'HIGH',
                    'suggestion' => 'Query takes more than 1 second. Consider optimization or adding LIMIT.',
                    'action' => 'Review query structure, add appropriate indexes, or reduce data scope.',
                ];
            }

            return $this->safeJsonEncode([
                'query' => $sql,
                'execution_plan' => $planLines,
                'metrics' => [
                    'actual_time_ms' => $actualTime,
                    'rows_processed' => $rowsProcessed,
                    'index_used' => $indexUsed,
                    'sequential_scan' => $seqScanDetected,
                ],
                'optimization_suggestions' => $optimizationSuggestions,
                'performance_rating' => empty($optimizationSuggestions) ? 'GOOD' : (
                    count(array_filter($optimizationSuggestions, fn($s) => $s['severity'] === 'HIGH')) > 0 ? 'NEEDS_OPTIMIZATION' : 'ACCEPTABLE'
                ),
            ]);

        } catch (\Throwable $e) {
            return $this->errorResponse('EXPLAIN ANALYZE failed: ' . $e->getMessage());
        }
    }

    // ── run_analysis_template ─────────────────────────────────────────────
    public function runAnalysisTemplate(string $template, string $period, array $filters = []): string
    {
        if (empty($template) || empty($period)) {
            return $this->errorResponse('Both template and period are required.');
        }

        $templates = $this->getAnalysisTemplates();

        if (!isset($templates[$template])) {
            return $this->safeJsonEncode([
                'error' => "Unknown template: {$template}",
                'available_templates' => array_keys($templates),
            ]);
        }

        $tpl = $templates[$template];

        try {
            // Build query from template
            $sql = $tpl['build_query']($period, $filters);

            if (!$sql) {
                return $this->errorResponse('Failed to build query for template: ' . $template);
            }

            // Execute query
            $queryResult = $this->decodeJson($this->queryService->executeQuery($sql, $tpl['label']), true);

            if (isset($queryResult['error'])) {
                return $this->errorResponse('Template execution failed: ' . $queryResult['error']);
            }

            $data = $queryResult['rows'] ?? [];
            $results = [
                'template' => $template,
                'label' => $tpl['label'],
                'description' => $tpl['description'],
                'period' => $period,
                'data_summary' => [
                    'total_rows' => $queryResult['rows_returned'] ?? 0,
                    'columns' => $queryResult['columns'] ?? [],
                ],
                'analyses' => [],
            ];

            if (!empty($data)) {
                // Run template-specific analyses
                foreach ($tpl['analyses'] as $analysis) {
                    $valueCol = $analysis['value_column'];
                    $periodCol = $analysis['period_column'] ?? null;

                    if ($analysis['type'] === 'trend' && $periodCol) {
                        $results['analyses']['trend'] = $this->decodeJson(
                            $this->statisticalService->analyzeTrend($data, $valueCol, $periodCol), true
                        );
                    }

                    if ($analysis['type'] === 'anomaly') {
                        $results['analyses']['anomalies'] = $this->decodeJson(
                            $this->statisticalService->detectAnomalies($data, $valueCol), true
                        );
                    }

                    if ($analysis['type'] === 'ranking') {
                        $labelCol = $analysis['label_column'] ?? null;
                        if ($labelCol) {
                            $ranking = collect($data)
                                ->sortByDesc($valueCol)
                                ->take($tpl['top_n'] ?? 10)
                                ->values()
                                ->toArray();
                            $results['analyses']['ranking'] = $ranking;
                        }
                    }
                }
            }

            return $this->safeJsonEncode($results);

        } catch (\Throwable $e) {
            return $this->errorResponse('Template execution failed: ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPER METHODS FOR SMART ANALYSIS
    // ════════════════════════════════════════════════════════════════════════

    private function getMetricTableMapping(string $metric): array
    {
        $mapping = [
            'penjualan' => ['jual', 'sales', 'penjualan', 'transaksi'],
            'stok' => ['stok', 'stock', 'inventory', 'gudang'],
            'profit' => ['profit', 'gpn', 'margin', 'netto', 'laba'],
            'gpn' => ['gpn', 'profit', 'netto', 'laba'],
            'pembelian' => ['beli', 'purchase', 'pembelian', 'po_'],
            'piutang' => ['piutang', 'receivable', 'tagihan', 'invoice'],
            'hutang' => ['hutang', 'payable', 'utang', 'kewajiban'],
            'pelanggan' => ['pelanggan', 'customer', 'client', 'member'],
            'barang' => ['barang', 'product', 'item', 'sku'],
            'cabang' => ['cabang', 'branch', 'store', 'outlet'],
        ];

        foreach ($mapping as $key => $words) {
            if (stripos($metric, $key) !== false) {
                return $words;
            }
        }

        // Default: use the metric itself as keyword
        return [$metric];
    }

    private function buildSmartQuery(string $metric, string $period, string $table, string $breakdownBy, int $topN): ?string
    {
        // Parse period into SQL date filter
        $dateFilter = $this->parsePeriodToSql($period);

        // Map metric to common column names
        $valueColumn = $this->mapMetricToColumn($metric);

        if (!$valueColumn) {
            return null;
        }

        // Check if table has a 'tanggal' column
        try {
            $hasDateColumn = DB::connection('pgsql_mbi')->select("
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'sch_mbi' AND table_name = ? AND column_name = 'tanggal'
                LIMIT 1
            ", [$table]);
        } catch (\Throwable $e) {
            $hasDateColumn = false;
        }

        if (!empty($breakdownBy)) {
            $breakdownCol = $this->mapBreakdownToColumn($breakdownBy);
            if ($breakdownCol) {
                return "SELECT
                            {$breakdownCol},
                            SUM({$valueColumn}) as total_{$valueColumn},
                            COUNT(*) as transaction_count
                        FROM sch_mbi.{$table}
                        WHERE {$dateFilter}
                        GROUP BY {$breakdownCol}
                        ORDER BY total_{$valueColumn} DESC
                        LIMIT {$topN}";
            }
        }

        // Time-series breakdown (default)
        if (!empty($hasDateColumn)) {
            return "SELECT
                        TO_CHAR(tanggal, 'YYYY-MM') as periode,
                        SUM({$valueColumn}) as total_{$valueColumn},
                        COUNT(*) as transaction_count
                    FROM sch_mbi.{$table}
                    WHERE {$dateFilter}
                    GROUP BY TO_CHAR(tanggal, 'YYYY-MM')
                    ORDER BY periode ASC";
        }

        // Fallback: simple aggregation
        return "SELECT
                    SUM({$valueColumn}) as total_{$valueColumn},
                    COUNT(*) as transaction_count
                FROM sch_mbi.{$table}
                WHERE {$dateFilter}
                LIMIT 1";
    }

    private function parsePeriodToSql(string $period): string
    {
        $lowerPeriod = strtolower($period);

        if (preg_match('/(\d+)\s*(bulan|month)/', $lowerPeriod, $matches)) {
            $months = (int)$matches[1];
            return "tanggal >= NOW() - INTERVAL '{$months} months'";
        }

        if (preg_match('/(\d{4})/', $period, $matches)) {
            $year = $matches[1];
            return "EXTRACT(YEAR FROM tanggal) = {$year}";
        }

        if (stripos($lowerPeriod, 'tahun ini') !== false || stripos($lowerPeriod, 'this year') !== false) {
            return "EXTRACT(YEAR FROM tanggal) = EXTRACT(YEAR FROM NOW())";
        }

        if (stripos($lowerPeriod, 'tahun lalu') !== false || stripos($lowerPeriod, 'last year') !== false) {
            return "EXTRACT(YEAR FROM tanggal) = EXTRACT(YEAR FROM NOW()) - 1";
        }

        // Default: last 6 months
        return "tanggal >= NOW() - INTERVAL '6 months'";
    }

    private function mapMetricToColumn(string $metric): ?string
    {
        $mapping = [
            'penjualan' => 'total_netto',
            'sales' => 'total_netto',
            'stok' => 'qty',
            'stock' => 'qty',
            'profit' => 'gpn',
            'gpn' => 'gpn',
            'laba' => 'gpn',
            'qty' => 'qty_jual',
            'jumlah' => 'qty_jual',
            'hpp' => 'hpp',
            'dpp' => 'total_dpp',
        ];

        foreach ($mapping as $key => $col) {
            if (stripos($metric, $key) !== false) {
                return $col;
            }
        }

        return 'total_netto'; // default
    }

    private function mapBreakdownToColumn(string $breakdownBy): ?string
    {
        $mapping = [
            'cabang' => 'nama_cabang',
            'branch' => 'nama_cabang',
            'regional' => 'nama_regional',
            'region' => 'nama_regional',
            'kategori' => 'nama_kategori_barang',
            'category' => 'nama_kategori_barang',
            'produk' => 'nama_barang',
            'product' => 'nama_barang',
            'pelanggan' => 'nama_pelanggan',
            'customer' => 'nama_pelanggan',
            'bulan' => 'tanggal',
            'month' => 'tanggal',
            'salesman' => 'nama_salesman',
            'sales' => 'nama_salesman',
        ];

        foreach ($mapping as $key => $col) {
            if (stripos($breakdownBy, $key) !== false) {
                return $col;
            }
        }

        return null;
    }

    private function detectValueColumn(array $data, string $metric): ?string
    {
        if (empty($data)) return null;

        $keys = array_keys($data[0]);
        $mapping = $this->mapMetricToColumn($metric);

        // Direct match
        if (in_array($mapping, $keys)) return $mapping;

        // Fuzzy match: look for total, sum, or metric-like columns
        foreach ($keys as $key) {
            if (preg_match('/(total|sum|amount|value|netto|gpn|profit)/i', $key)) {
                return $key;
            }
        }

        // Return first numeric column
        foreach ($keys as $key) {
            if (is_numeric($data[0][$key])) {
                return $key;
            }
        }

        return null;
    }

    private function detectPeriodColumn(array $data): ?string
    {
        if (empty($data)) return null;

        $keys = array_keys($data[0]);
        $periodKeywords = ['tanggal', 'bulan', 'periode', 'month', 'year', 'date', 'period', 'waktu', 'waktu_transaksi'];

        foreach ($periodKeywords as $kw) {
            foreach ($keys as $key) {
                if (stripos($key, $kw) !== false) {
                    return $key;
                }
            }
        }

        return null;
    }

    private function detectColumnByName(array $data, string $name): ?string
    {
        if (empty($data)) return null;

        $keys = array_keys($data[0]);
        $columnMap = $this->mapBreakdownToColumn($name);

        if ($columnMap && in_array($columnMap, $keys)) return $columnMap;

        // Fuzzy match
        foreach ($keys as $key) {
            if (stripos($key, $name) !== false) {
                return $key;
            }
        }

        return null;
    }

    private function getAnalysisTemplates(): array
    {
        return [
            'sales_performance' => [
                'label' => 'Sales Performance Analysis',
                'description' => 'Comprehensive sales analysis: trends, top performers, and anomalies.',
                'build_query' => function(string $period, array $filters) {
                    $dateFilter = $this->parsePeriodToSql($period);
                    $whereClauses = [$dateFilter];
                    foreach ($filters as $col => $val) {
                        $whereClauses[] = "{$col} = '{$val}'";
                    }
                    $where = implode(' AND ', $whereClauses);
                    return "SELECT
                                nama_cabang,
                                nama_regional,
                                SUM(total_netto) as total_penjualan,
                                SUM(gpn) as total_gpn,
                                COUNT(DISTINCT nomor_transaksi) as transaction_count,
                                COUNT(DISTINCT nama_pelanggan) as customer_count
                            FROM sch_mbi.view_data_penjualan_rinci_mbi
                            WHERE {$where}
                            GROUP BY nama_cabang, nama_regional
                            ORDER BY total_penjualan DESC
                            LIMIT 50";
                },
                'analyses' => [
                    ['type' => 'trend', 'value_column' => 'total_penjualan', 'period_column' => 'nama_cabang'],
                    ['type' => 'anomaly', 'value_column' => 'total_penjualan'],
                    ['type' => 'ranking', 'value_column' => 'total_penjualan', 'label_column' => 'nama_cabang'],
                ],
                'top_n' => 10,
            ],
            'inventory_health' => [
                'label' => 'Inventory Health Analysis',
                'description' => 'Stock levels, turnover, and potential dead stock identification.',
                'build_query' => function(string $period, array $filters) {
                    return "SELECT
                                nama_barang,
                                nama_kategori_barang,
                                SUM(qty) as current_stock,
                                COUNT(DISTINCT nomor_transaksi) as movement_count
                            FROM sch_mbi.data_stok_mbi
                            GROUP BY nama_barang, nama_kategori_barang
                            ORDER BY current_stock DESC
                            LIMIT 100";
                },
                'analyses' => [
                    ['type' => 'anomaly', 'value_column' => 'current_stock'],
                    ['type' => 'ranking', 'value_column' => 'current_stock', 'label_column' => 'nama_barang'],
                ],
                'top_n' => 20,
            ],
            'customer_analysis' => [
                'label' => 'Customer Analysis',
                'description' => 'Customer purchasing patterns, top customers, and behavior trends.',
                'build_query' => function(string $period, array $filters) {
                    $dateFilter = $this->parsePeriodToSql($period);
                    return "SELECT
                                nama_pelanggan,
                                SUM(total_netto) as total_pembelian,
                                COUNT(DISTINCT nomor_transaksi) as frequency,
                                SUM(total_netto) / COUNT(DISTINCT nomor_transaksi) as avg_transaction
                            FROM sch_mbi.view_data_penjualan_rinci_mbi
                            WHERE {$dateFilter}
                            GROUP BY nama_pelanggan
                            ORDER BY total_pembelian DESC
                            LIMIT 50";
                },
                'analyses' => [
                    ['type' => 'trend', 'value_column' => 'total_pembelian', 'period_column' => 'nama_pelanggan'],
                    ['type' => 'anomaly', 'value_column' => 'total_pembelian'],
                    ['type' => 'ranking', 'value_column' => 'total_pembelian', 'label_column' => 'nama_pelanggan'],
                ],
                'top_n' => 10,
            ],
            'profitability' => [
                'label' => 'Profitability Analysis',
                'description' => 'GPN analysis, margin trends, and profit drivers.',
                'build_query' => function(string $period, array $filters) {
                    $dateFilter = $this->parsePeriodToSql($period);
                    return "SELECT
                                nama_cabang,
                                SUM(total_netto) as total_penjualan,
                                SUM(gpn) as total_gpn,
                                CASE WHEN SUM(total_netto) > 0
                                    THEN ROUND((SUM(gpn) / SUM(total_netto)) * 100, 2)
                                    ELSE 0
                                END as margin_pct
                            FROM sch_mbi.view_data_penjualan_rinci_mbi
                            WHERE {$dateFilter}
                            GROUP BY nama_cabang
                            ORDER BY total_gpn DESC
                            LIMIT 50";
                },
                'analyses' => [
                    ['type' => 'trend', 'value_column' => 'total_gpn', 'period_column' => 'nama_cabang'],
                    ['type' => 'anomaly', 'value_column' => 'margin_pct'],
                    ['type' => 'ranking', 'value_column' => 'total_gpn', 'label_column' => 'nama_cabang'],
                ],
                'top_n' => 10,
            ],
            'regional_comparison' => [
                'label' => 'Regional Comparison',
                'description' => 'Compare performance across sales regions.',
                'build_query' => function(string $period, array $filters) {
                    $dateFilter = $this->parsePeriodToSql($period);
                    return "SELECT
                                nama_regional,
                                SUM(total_netto) as total_penjualan,
                                SUM(gpn) as total_gpn,
                                COUNT(DISTINCT nama_cabang) as branch_count
                            FROM sch_mbi.view_data_penjualan_rinci_mbi
                            WHERE {$dateFilter}
                            GROUP BY nama_regional
                            ORDER BY total_penjualan DESC";
                },
                'analyses' => [
                    ['type' => 'anomaly', 'value_column' => 'total_penjualan'],
                    ['type' => 'ranking', 'value_column' => 'total_penjualan', 'label_column' => 'nama_regional'],
                ],
                'top_n' => 10,
            ],
            'monthly_trend' => [
                'label' => 'Monthly Trend Analysis',
                'description' => 'Track monthly trends for key metrics.',
                'build_query' => function(string $period, array $filters) {
                    $dateFilter = $this->parsePeriodToSql($period);
                    return "SELECT
                                TO_CHAR(tanggal, 'YYYY-MM') as bulan,
                                SUM(total_netto) as total_penjualan,
                                SUM(gpn) as total_gpn,
                                COUNT(*) as transaction_count
                            FROM sch_mbi.view_data_penjualan_rinci_mbi
                            WHERE {$dateFilter}
                            GROUP BY TO_CHAR(tanggal, 'YYYY-MM')
                            ORDER BY bulan ASC";
                },
                'analyses' => [
                    ['type' => 'trend', 'value_column' => 'total_penjualan', 'period_column' => 'bulan'],
                    ['type' => 'anomaly', 'value_column' => 'total_penjualan'],
                ],
            ],
            'top_products' => [
                'label' => 'Top Products Analysis',
                'description' => 'Identify best-selling products and categories.',
                'build_query' => function(string $period, array $filters) {
                    $dateFilter = $this->parsePeriodToSql($period);
                    return "SELECT
                                nama_barang,
                                nama_kategori_barang,
                                SUM(qty_jual) as total_qty,
                                SUM(total_netto) as total_penjualan,
                                SUM(gpn) as total_gpn
                            FROM sch_mbi.view_data_penjualan_rinci_mbi
                            WHERE {$dateFilter}
                            GROUP BY nama_barang, nama_kategori_barang
                            ORDER BY total_penjualan DESC
                            LIMIT 100";
                },
                'analyses' => [
                    ['type' => 'anomaly', 'value_column' => 'total_penjualan'],
                    ['type' => 'ranking', 'value_column' => 'total_penjualan', 'label_column' => 'nama_barang'],
                ],
                'top_n' => 20,
            ],
            'sales_efficiency' => [
                'label' => 'Sales Efficiency Analysis',
                'description' => 'Analyze salesperson performance and efficiency metrics.',
                'build_query' => function(string $period, array $filters) {
                    $dateFilter = $this->parsePeriodToSql($period);
                    return "SELECT
                                nama_salesman,
                                nama_cabang,
                                SUM(total_netto) as total_penjualan,
                                COUNT(DISTINCT nomor_transaksi) as transaction_count,
                                SUM(total_netto) / COUNT(DISTINCT nomor_transaksi) as avg_deal_size
                            FROM sch_mbi.view_data_penjualan_rinci_mbi
                            WHERE {$dateFilter}
                            GROUP BY nama_salesman, nama_cabang
                            ORDER BY total_penjualan DESC
                            LIMIT 50";
                },
                'analyses' => [
                    ['type' => 'anomaly', 'value_column' => 'total_penjualan'],
                    ['type' => 'ranking', 'value_column' => 'total_penjualan', 'label_column' => 'nama_salesman'],
                ],
                'top_n' => 10,
            ],
        ];
    }
}
