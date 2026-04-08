<?php

namespace App\Services;

use App\Services\Analysis\SmartAnalysisService;
use App\Services\Analysis\StatisticalAnalysisService;
use App\Services\Core\QueryService;
use App\Services\Core\SchemaService;
use App\Services\ERP\ERPService;
use Illuminate\Support\Facades\Log;

/**
 * ToolCallExecutor
 *
 * Facade pattern - dispatches tool calls to specialized services.
 * This class is the entry point for the AI agentic loop.
 *
 * Tool Definitions remain here (single source of truth).
 * Execution is delegated to:
 *   - QueryService (execute_query, RBAC, currency detection)
 *   - SchemaService (get_schema_info, describe_table, relationships, indexes, data quality)
 *   - StatisticalAnalysisService (13 statistical methods)
 *   - SmartAnalysisService (smart_analyze, explain_query_plan, templates)
 *   - ERPService (navigation, guidance, web scraping)
 */
class ToolCallExecutor
{
    private QueryService $queryService;
    private SchemaService $schemaService;
    private StatisticalAnalysisService $statisticalService;
    private SmartAnalysisService $smartService;
    private ERPService $erpService;

    public function __construct()
    {
        // Initialize services with dependencies
        $this->queryService = new QueryService();
        $this->schemaService = new SchemaService($this->queryService);
        $this->statisticalService = new StatisticalAnalysisService();
        $this->smartService = new SmartAnalysisService(
            $this->queryService,
            $this->schemaService,
            $this->statisticalService
        );
        $this->erpService = new ERPService();
    }

    // FIX: Cache allowed tables so Auth::check() is not needed inside stream
    public function setAllowedTables(array $tables): void
    {
        $this->queryService->setAllowedTables($tables);
    }

    /**
     * Get allowed tables for current user (delegated to QueryService).
     * Used by controller before streaming.
     */
    public function getAllowedTables(): array
    {
        return $this->queryService->getAllowedTables();
    }

    // ── Definisi tools yang dikirim ke OpenAI ─────────────────────────────────
    // FIX: properties kosong harus pakai stdClass agar JSON encode jadi {} bukan []
    // FIX: deskripsi lebih detail agar AI tahu kapan memanggil tiap tool
    public static function getToolDefinitions(): array
    {
        return [
            [
                'type'        => 'function',
                'name'        => 'get_schema_info',
                'description' => 'Get a complete overview of all accessible tables with their columns and data types in one single call. ALWAYS call this first before writing any SQL query. This gives you everything you need to understand the database structure.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),  // FIX: {} bukan []
                    'required'   => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'list_tables',
                'description' => 'List all database table names the current user is allowed to access. Use this only if you need a quick list of table names without column details.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),  // FIX: {} bukan []
                    'required'   => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'describe_table',
                'description' => 'Get all columns and their data types for a specific table. Use this when you need detailed information about a single table after already knowing the table name.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'table_name' => [
                            'type'        => 'string',
                            'description' => 'The exact table name without schema prefix, e.g. "view_data_penjualan_rinci_mbi"',
                        ],
                    ],
                    'required' => ['table_name'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'execute_query',
                'description' => 'Execute a SQL SELECT query to retrieve business data from the PostgreSQL database (schema: sch_mbi). Always prefix table names with "sch_mbi.". USE LIMIT when the user asks for a specific number (e.g. "top 10"), but do NOT use LIMIT for general "show/list" requests where the user wants to see all data.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'sql'   => [
                            'type'        => 'string',
                            'description' => 'A valid PostgreSQL SELECT query. Must include sch_mbi. prefix for all table names. Use LIMIT only if explicitly requested or for performance on "Top N" queries. Example: SELECT nama_barang, SUM(qty_jual) as total FROM sch_mbi.view_data_penjualan_rinci_mbi GROUP BY nama_barang ORDER BY total DESC LIMIT 10',
                        ],
                        'label' => [
                            'type'        => 'string',
                            'description' => 'A short business-friendly description of what this query retrieves, e.g. "10 produk terlaris" or "Total penjualan per cabang"',
                        ],
                        'currency_columns' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'A list of column names in the result that should be formatted as Indonesian Rupiah (currency), e.g. ["total_netto", "harga_satuan", "profit", "biaya", "ongkir"]. YOU MUST ALWAYS IDENTIFY and include columns that represent MONEY/MONETARY values from the schema.',
                        ],
                    ],
                    'required' => ['sql', 'label'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'get_business_context',
                'description' => 'Retrieve documentation about business metrics (KPIs), definitions, calculations, and regional hierarchies. Use this when you need to understand how to interpret specific data fields or calculate metrics like Gross Profit Margin or Turnover.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'analyze_trend',
                'description' => 'Perform statistical trend analysis on a dataset. Calculates growth rates and identifies the overall direction (upward/downward) of a series of values over time.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The dataset returned by execute_query.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The column name containing the numeric values to analyze.',
                        ],
                        'period_column' => [
                            'type'        => 'string',
                            'description' => 'The column name containing the time periods (e.g., month, year).',
                        ],
                    ],
                    'required' => ['data', 'value_column', 'period_column'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'detect_anomalies',
                'description' => 'Identify significant outliers in a dataset that deviate from the business norm (e.g., unusually low sales or high stock).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The dataset to check for anomalies.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The numeric column to check for outliers.',
                        ],
                    ],
                    'required' => ['data', 'value_column'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'compare_periods',
                'description' => 'Precisely compare two specific time periods in a dataset to calculate growth or decline in absolute and percentage terms (MoM/YoY analysis).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The dataset containing multiple periods.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The column name for the values being compared.',
                        ],
                        'period_column' => [
                            'type'        => 'string',
                            'description' => 'The column name for time periods.',
                        ],
                        'base_period' => [
                            'type'        => 'string',
                            'description' => 'The period to use as a baseline (e.g., "YYYY-01" for January, "bulan_lalu", "2_period_lalu").',
                        ],
                        'compare_period' => [
                            'type'        => 'string',
                            'description' => 'The period to compare against the baseline (e.g., "YYYY-02" for February, "bulan_ini", "1_period_lalu").',
                        ],
                    ],
                    'required' => ['data', 'value_column', 'period_column', 'base_period', 'compare_period'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'predict_future',
                'description' => 'Predict future values based on a historical dataset using linear regression. Use this when the user asks for "forecast", "projection", or "predictions" for upcoming periods.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The historical dataset containing a numeric value column and a period column.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The column name containing the numeric values to project.',
                        ],
                        'period_column' => [
                            'type'        => 'string',
                            'description' => 'The column name for the time sequence (used for sorting).',
                        ],
                        'periods_to_project' => [
                            'type'        => 'integer',
                            'description' => 'Number of future units to project (e.g., 3 months). Max recommended: 6.',
                            'default'     => 3,
                        ],
                    ],
                    'required' => ['data', 'value_column', 'period_column', 'periods_to_project'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'audit_dataset',
                'description' => 'Perform a comprehensive "Proactive Audit" on a dataset. It automatically detects anomalies, trends, top contributors (Pareto), and volatility. Use this to find "hidden stories" or when a user asks for a general "audit" or "insight" on a list of data.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'object'],
                            'description' => 'The dataset to audit.',
                        ],
                        'value_column' => [
                            'type'        => 'string',
                            'description' => 'The main numeric metric to audit (e.g., "total_netto").',
                        ],
                        'label_column' => [
                            'type'        => 'string',
                            'description' => 'The descriptive column (e.g., "nama_cabang" or "bulan").',
                        ],
                    ],
                    'required' => ['data', 'value_column', 'label_column'],
                ],
            ],
            // ── ADVANCED BUSINESS ANALYST TOOLS ─────────────────────────────
            [
                'type'        => 'function',
                'name'        => 'analyze_root_cause',
                'description' => 'Decompose WHY a KPI changed by ranking contribution of each dimension (e.g. region, product_category, channel). Use when absolute KPI change > 3%. Returns ranked drivers sorted by impact.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data'             => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Dataset with at least two period rows per dimension.'],
                        'value_column'     => ['type' => 'string', 'description' => 'The KPI column to decompose (e.g. "total_netto").'],
                        'dimension_column' => ['type' => 'string', 'description' => 'The grouping column (e.g. "nama_cabang", "nama_regional", "kategori").'],
                        'period_column'    => ['type' => 'string', 'description' => 'Column that identifies the time period.'],
                        'base_period'      => ['type' => 'string', 'description' => 'The baseline period (e.g. "YYYY-01" or "2_months_ago").'],
                        'compare_period'   => ['type' => 'string', 'description' => 'The comparison period (e.g. "YYYY-02" or "last_month").'],
                    ],
                    'required' => ['data', 'value_column', 'dimension_column', 'period_column', 'base_period', 'compare_period'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'analyze_kpi_correlation',
                'description' => 'Calculate Pearson correlation between a target KPI and candidate driver columns to identify which metrics most strongly influence the KPI. Use when an optimization decision is needed.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data'              => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Dataset with numeric columns.'],
                        'target_kpi'        => ['type' => 'string', 'description' => 'The KPI column to analyze (e.g. "gpn").'],
                        'candidate_columns' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'List of numeric columns to test as drivers.'],
                    ],
                    'required' => ['data', 'target_kpi', 'candidate_columns'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'forecast_metric',
                'description' => 'Forecast a single KPI using linear regression with 95% confidence intervals. Better than predict_future — use this when a user asks for forecast/projection.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data'                      => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Historical dataset.'],
                        'value_column'             => ['type' => 'string', 'description' => 'Numeric column to forecast.'],
                        'period_column'            => ['type' => 'string', 'description' => 'Time period column for sorting.'],
                        'periods_to_forecast'      => ['type' => 'integer', 'description' => 'Number of future periods (default: 3, max: 6).', 'default' => 3],
                        'include_confidence_interval' => ['type' => 'boolean', 'description' => 'Include 95% CI in output.', 'default' => true],
                    ],
                    'required' => ['data', 'value_column', 'period_column', 'periods_to_forecast'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'forecast_hierarchy',
                'description' => 'Forecast multiple entities (e.g. each branch/region) separately and ensure totals align with a parent-level forecast. Use when hierarchy consistency is required.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data'                => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Dataset with hierarchy, period, and value columns.'],
                        'value_column'        => ['type' => 'string', 'description' => 'The numeric KPI to forecast.'],
                        'period_column'       => ['type' => 'string', 'description' => 'Time column.'],
                        'hierarchy_column'    => ['type' => 'string', 'description' => 'Column that defines entities (e.g. "nama_cabang").'],
                        'periods_to_forecast' => ['type' => 'integer', 'description' => 'Future periods to project.', 'default' => 3],
                    ],
                    'required' => ['data', 'value_column', 'period_column', 'hierarchy_column', 'periods_to_forecast'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'detect_risk_signals',
                'description' => 'Forward-looking risk detection. Combines Z-score anomaly detection with momentum analysis to identify early warning signals. Use for proactive risk alerts.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data'          => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Time-series dataset.'],
                        'value_column'  => ['type' => 'string', 'description' => 'The KPI column to monitor.'],
                        'period_column' => ['type' => 'string', 'description' => 'Time column for ordering.'],
                    ],
                    'required' => ['data', 'value_column', 'period_column'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'simulate_scenario',
                'description' => 'What-if simulation: apply price/cost/volume changes to baseline data and measure impact on output metric. Use when user asks "what if price increases 10%" or wants target planning.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'base_data'     => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Baseline dataset.'],
                        'scenario_name' => ['type' => 'string', 'description' => 'Label for the scenario (e.g. "Price +10%").'],
                        'changes'       => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'List of changes: [{"column": "harga", "change_type": "pct", "value": 10}]. change_type: pct (percentage) or abs (absolute).'],
                        'output_metric' => ['type' => 'string', 'description' => 'Column to measure as outcome (e.g. "gpn", "total_netto").'],
                    ],
                    'required' => ['base_data', 'scenario_name', 'changes', 'output_metric'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'segment_entities',
                'description' => 'Cluster entities (branches, products, customers) into performance segments using a simplified K-means approach. Use to identify high-value clusters.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data'            => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Dataset to segment.'],
                        'entity_column'   => ['type' => 'string', 'description' => 'Column to identify each entity (e.g. "nama_cabang").'],
                        'feature_columns' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Numeric columns to use for clustering.'],
                        'n_segments'      => ['type' => 'integer', 'description' => 'Number of segments/clusters (default: 3).', 'default' => 3],
                    ],
                    'required' => ['data', 'entity_column', 'feature_columns', 'n_segments'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'analyze_cohort',
                'description' => 'Lifecycle and retention analysis. Groups entities by a cohort definition and tracks their value trajectory over periods. Use for customer behavior or branch maturity analysis.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'data'                     => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Dataset with entity, period, value, and cohort columns.'],
                        'entity_column'            => ['type' => 'string', 'description' => 'Column identifying entities (e.g. "nama_pelanggan").'],
                        'period_column'            => ['type' => 'string', 'description' => 'Time period column.'],
                        'value_column'             => ['type' => 'string', 'description' => 'Numeric metric to track.'],
                        'cohort_definition_column' => ['type' => 'string', 'description' => 'Column defining cohort membership (e.g. "tahun_bergabung", "kategori").'],
                    ],
                    'required' => ['data', 'entity_column', 'period_column', 'value_column', 'cohort_definition_column'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'generate_business_insight',
                'description' => 'ALWAYS call this as the FINAL step after all analysis is complete. Synthesizes all findings into a structured executive-level narrative: Executive Summary, Key Drivers, Risk/Opportunity, and Recommended Action.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'question'      => ['type' => 'string', 'description' => 'The original user question.'],
                        'data_summary'  => ['type' => 'string', 'description' => 'Brief description of data retrieved (e.g. "Monthly sales 12 periods, 91 branches").'],
                        'trend_result'  => ['type' => 'object', 'description' => 'Output from analyze_trend or forecast_metric (optional).'],
                        'anomalies'     => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Anomalies found (optional).'],
                        'root_cause'    => ['type' => 'object', 'description' => 'Output from analyze_root_cause (optional).'],
                        'forecast'      => ['type' => 'object', 'description' => 'Output from forecast_metric (optional).'],
                        'risks'         => ['type' => 'object', 'description' => 'Output from detect_risk_signals (optional).'],
                        'language'      => ['type' => 'string', 'description' => 'Response language: "id" for Indonesian, "en" for English.', 'default' => 'id'],
                    ],
                    'required' => ['question', 'data_summary'],
                ],
            ],
            // ── DATABASE ANALYSIS TOOLS (Priority #1) ──────────────────────
            [
                'type'        => 'function',
                'name'        => 'analyze_relationships',
                'description' => 'Discover foreign key relationships and table dependencies in the database. Use this BEFORE writing JOIN queries to understand which tables are related and how. Critical for accurate multi-table queries.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'table_name' => [
                            'type'        => 'string',
                            'description' => 'Optional: focus on relationships for a specific table. Leave empty to analyze all tables.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'suggest_indexes',
                'description' => 'Analyze query patterns and table structures to suggest missing indexes that could improve performance. Use when query performance is a concern or when optimizing database access.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'table_name' => [
                            'type'        => 'string',
                            'description' => 'Table to analyze for index suggestions.',
                        ],
                        'query_pattern' => [
                            'type'        => 'string',
                            'description' => 'Optional: a sample query pattern to optimize.',
                        ],
                    ],
                    'required' => ['table_name'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'check_data_quality',
                'description' => 'Perform comprehensive data quality checks: NULL patterns, duplicate records, orphaned references, and data consistency issues. Use when data reliability is in question or before critical analysis.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'table_name' => [
                            'type'        => 'string',
                            'description' => 'Table to check for data quality.',
                        ],
                        'check_type' => [
                            'type'        => 'string',
                            'description' => 'Specific check to run: "nulls", "duplicates", "consistency", or "all" (default).',
                            'enum'        => ['nulls', 'duplicates', 'consistency', 'all'],
                            'default'     => 'all',
                        ],
                        'key_columns' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Columns that should be unique (for duplicate checks).',
                        ],
                    ],
                    'required' => ['table_name'],
                ],
            ],
            // ── SMART ANALYSIS CHAIN TOOLS (Priority #2) ───────────────────
            [
                'type'        => 'function',
                'name'        => 'smart_analyze',
                'description' => 'ONE-STOP analysis tool that automatically chains: schema discovery → data retrieval → trend analysis → anomaly detection → period comparison → insights. Use this instead of calling multiple analysis tools separately. Returns comprehensive results in one call.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'metric' => [
                            'type'        => 'string',
                            'description' => 'The business metric to analyze (e.g. "penjualan", "stok", "profit", "gpn").',
                        ],
                        'period' => [
                            'type'        => 'string',
                            'description' => 'Time period to analyze (e.g. "6 bulan terakhir", "tahun berjalan", "tahun ini").',
                        ],
                        'breakdown_by' => [
                            'type'        => 'string',
                            'description' => 'Dimension to group results by (e.g. "cabang", "regional", "kategori", "bulan").',
                        ],
                        'analysis_types' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string', 'enum' => ['trend', 'anomaly', 'comparison', 'forecast', 'root_cause']],
                            'description' => 'Which analyses to run. Default: ["trend", "anomaly", "comparison"].',
                            'default'     => ['trend', 'anomaly', 'comparison'],
                        ],
                        'top_n' => [
                            'type'        => 'integer',
                            'description' => 'If analyzing rankings, return top N (default: 10).',
                            'default'     => 10,
                        ],
                    ],
                    'required' => ['metric', 'period'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'explain_query_plan',
                'description' => 'Run PostgreSQL EXPLAIN ANALYZE on a query to show execution plan, cost estimates, and actual runtime statistics. Use to diagnose slow queries, suggest optimizations, or verify index usage.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'sql' => [
                            'type'        => 'string',
                            'description' => 'The SELECT query to analyze with EXPLAIN.',
                        ],
                        'suggestions' => [
                            'type'        => 'boolean',
                            'description' => 'Include optimization suggestions (default: true).',
                            'default'     => true,
                        ],
                    ],
                    'required' => ['sql'],
                ],
            ],
            // ── PRE-BUILT ANALYSIS TEMPLATES (Priority #2) ─────────────────
            [
                'type'        => 'function',
                'name'        => 'run_analysis_template',
                'description' => 'Execute a pre-built analysis template for common business scenarios. Faster than manual tool chaining — returns comprehensive results instantly.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'template' => [
                            'type'        => 'string',
                            'description' => 'Template to execute.',
                            'enum'        => [
                                'sales_performance',
                                'inventory_health',
                                'customer_analysis',
                                'profitability',
                                'regional_comparison',
                                'monthly_trend',
                                'top_products',
                                'sales_efficiency',
                            ],
                        ],
                        'period' => [
                            'type'        => 'string',
                            'description' => 'Time period (e.g. "6 bulan", "tahun berjalan", "tahun ini").',
                        ],
                        'filters' => [
                            'type'        => 'object',
                            'description' => 'Optional filters to apply (e.g. {"cabang": "Jakarta", "kategori": "Spare Parts"}).',
                        ],
                    ],
                    'required' => ['template', 'period'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'get_erp_menu_navigation',
                'description' => 'Get ERP menu navigation path for a specific module or sub-menu. Use this when user asks "where is X menu?", "how to access Y module?", "dimana letak menu Z?". Returns focused result for ONE module only — not all modules. FORMAT THE RESULT TO USER LIKE THIS: Use a simple, clean format. Start with a one-line summary of where the module is located, then list each sub-menu as bullet points with short descriptions on the same line (e.g., "→ Transaksi → Pembayaran Hutang — Bayar hutang ke supplier"). Do NOT add "Ringkasan Eksekutif", "Analisis & Rekomendasi", or overly formal language. Keep it direct, clean, and easy to scan.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'module' => [
                            'type'        => 'string',
                            'description' => 'Specific module name to get navigation for. Examples: "Finance", "Account Payable", "Account Receivable", "Inventory", "Warehouse", "Report Center", "Document". Leave empty to list all module names only.',
                            'enum'        => ['', 'Finance', 'Account Payable', 'Account Receivable', 'Inventory', 'Warehouse', 'Report Center', 'Document'],
                        ],
                        'menu_keyword' => [
                            'type'        => 'string',
                            'description' => 'Optional keyword to search for a specific sub-menu across all modules. Examples: "pembayaran", "piutang", "stok".',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            // ── ERP Guidance Tool ────────────────────────────────────────────
            [
                'type'        => 'function',
                'name'        => 'get_erp_guidance',
                'description' => 'Cari dan tampilkan panduan operasional ERP (cara menggunakan modul/fitur ERP) berdasarkan kata kunci atau kategori. GUNAKAN tool ini ketika user bertanya tentang cara menggunakan ERP, seperti: cara input Sales Order, bagaimana proses faktur pembelian, cara bayar hutang, cara terima pembayaran piutang, dll. Panduan mencakup: Report Center, Document, Finance, Account Payable, Account Receivable, dan Inventory.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'keyword'  => [
                            'type'        => 'string',
                            'description' => 'Kata kunci pencarian panduan ERP, misalnya: "sales order", "nota kredit", "faktur pembelian", "hutang", "piutang", "stok". Gunakan kata kunci yang relevan dengan pertanyaan user.',
                        ],
                        'category' => [
                            'type'        => 'string',
                            'description' => 'Filter berdasarkan kategori/modul ERP. Pilih salah satu: "Report Center", "Document", "Finance", "Account Payable", "Account Receivable", "Inventory". Kosongkan jika tidak ingin filter kategori.',
                            'enum'        => ['Report Center', 'Document', 'Finance', 'Account Payable', 'Account Receivable', 'Inventory', ''],
                        ],
                        'list_all' => [
                            'type'        => 'boolean',
                            'description' => 'Jika true, tampilkan semua panduan yang tersedia. Gunakan ini jika user meminta daftar semua panduan ERP.',
                            'default'     => false,
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'fetch_erp_guidance_from_web',
                'description' => 'Ambil panduan ERP terbaru langsung dari website erp-guidance.online menggunakan URL spesifik. Gunakan ini jika user memberikan URL panduan atau jika informasi di local guidance (get_erp_guidance) dirasa kurang lengkap atau butuh pembaruan. Tool ini akan mengekstrak langkah-langkah, detail form, dan deskripsi gambar.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'url' => [
                            'type'        => 'string',
                            'description' => 'URL lengkap halaman panduan, misal: https://erp-guidance.online/account-payable/ni6r9oqxn7/',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
        ];
    }

    // ── Dispatch tool call dari AI ────────────────────────────────────────────
    public function execute(string $toolName, array $arguments): string
    {
        $this->logToolCall($toolName, $arguments);

        try {
            return match ($toolName) {
                // Core Tools
                'list_tables'           => $this->schemaService->listTables(),
                'describe_table'        => $this->schemaService->describeTable($arguments['table_name'] ?? ''),
                'execute_query'         => $this->queryService->executeQuery($arguments['sql'] ?? '', $arguments['label'] ?? '', $arguments['currency_columns'] ?? []),
                'get_schema_info'       => $this->schemaService->getSchemaInfo(),
                'get_business_context'  => $this->getBusinessContext(),

                // Analysis Tools
                'analyze_trend'         => $this->statisticalService->analyzeTrend($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? ''),
                'detect_anomalies'      => $this->statisticalService->detectAnomalies($arguments['data'] ?? [], $arguments['value_column'] ?? ''),
                'compare_periods'       => $this->statisticalService->comparePeriods($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['base_period'] ?? '', $arguments['compare_period'] ?? ''),
                'predict_future'        => $this->statisticalService->predictFuture($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['periods_to_project'] ?? 3),
                'audit_dataset'         => $this->statisticalService->auditDataset($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['label_column'] ?? ''),

                // ── Advanced Business Analyst Tools ──────────────────────────
                'analyze_root_cause'    => $this->statisticalService->analyzeRootCause($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['dimension_column'] ?? '', $arguments['period_column'] ?? '', $arguments['base_period'] ?? '', $arguments['compare_period'] ?? ''),
                'analyze_kpi_correlation' => $this->statisticalService->analyzeKpiCorrelation($arguments['data'] ?? [], $arguments['target_kpi'] ?? '', $arguments['candidate_columns'] ?? []),
                'forecast_metric'       => $this->statisticalService->forecastMetric($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['periods_to_forecast'] ?? 3, $arguments['include_confidence_interval'] ?? true),
                'forecast_hierarchy'    => $this->statisticalService->forecastHierarchy($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['hierarchy_column'] ?? '', $arguments['periods_to_forecast'] ?? 3),
                'detect_risk_signals'   => $this->statisticalService->detectRiskSignals($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? ''),
                'simulate_scenario'     => $this->statisticalService->simulateScenario($arguments['base_data'] ?? [], $arguments['scenario_name'] ?? '', $arguments['changes'] ?? [], $arguments['output_metric'] ?? ''),
                'segment_entities'      => $this->statisticalService->segmentEntities($arguments['data'] ?? [], $arguments['entity_column'] ?? '', $arguments['feature_columns'] ?? [], $arguments['n_segments'] ?? 3),
                'analyze_cohort'        => $this->statisticalService->analyzeCohort($arguments['data'] ?? [], $arguments['entity_column'] ?? '', $arguments['period_column'] ?? '', $arguments['value_column'] ?? '', $arguments['cohort_definition_column'] ?? ''),
                'generate_business_insight' => $this->statisticalService->generateBusinessInsight($arguments['question'] ?? '', $arguments['data_summary'] ?? '', $arguments['trend_result'] ?? null, $arguments['anomalies'] ?? null, $arguments['root_cause'] ?? null, $arguments['forecast'] ?? null, $arguments['risks'] ?? null, $arguments['language'] ?? 'id'),

                // ── Database Analysis Tools (Priority #1) ──────────────────
                'analyze_relationships' => $this->schemaService->analyzeRelationships($arguments['table_name'] ?? ''),
                'suggest_indexes'       => $this->schemaService->suggestIndexes($arguments['table_name'] ?? '', $arguments['query_pattern'] ?? ''),
                'check_data_quality'    => $this->schemaService->checkDataQuality($arguments['table_name'] ?? '', $arguments['check_type'] ?? 'all', $arguments['key_columns'] ?? []),

                // ── Smart Analysis Chain Tools (Priority #2) ───────────────
                'smart_analyze'         => $this->smartService->smartAnalyze($arguments['metric'] ?? '', $arguments['period'] ?? '', $arguments['breakdown_by'] ?? '', $arguments['analysis_types'] ?? ['trend', 'anomaly', 'comparison'], $arguments['top_n'] ?? 10),
                'explain_query_plan'    => $this->smartService->explainQueryPlan($arguments['sql'] ?? '', $arguments['suggestions'] ?? true),
                'run_analysis_template' => $this->smartService->runAnalysisTemplate($arguments['template'] ?? '', $arguments['period'] ?? '', $arguments['filters'] ?? []),

                // ── ERP Tools ──────────────────────────────────────────────
                'get_erp_menu_navigation' => $this->erpService->getErpMenuNavigation($arguments['module'] ?? '', $arguments['menu_keyword'] ?? ''),
                'get_erp_guidance'      => $this->erpService->getErpGuidance($arguments['keyword'] ?? '', $arguments['category'] ?? '', $arguments['list_all'] ?? false),
                'fetch_erp_guidance_from_web' => $this->erpService->fetchErpGuidanceFromWeb($arguments['url'] ?? ''),
                'refresh_all_erp_guidance'    => $this->erpService->refreshAllErpGuidance($arguments['urls'] ?? []),

                default => json_encode(['error' => "Unknown tool: {$toolName}"]),
            };
        } catch (\Throwable $e) {
            $this->logToolFailure($toolName, $e);
            return json_encode(['error' => 'Permintaan tidak dapat diproses saat ini. Silakan coba lagi.']);
        }
    }

    // ── get_business_context ──────────────────────────────────────────────────
    private function getBusinessContext(): string
    {
        $path = config_path('business_metrics.json');
        if (!file_exists($path)) {
            return json_encode(['error' => 'Business metrics configuration not found.']);
        }

        $content = file_get_contents($path);
        return $content ?: json_encode(['error' => 'Failed to read business metrics config.']);
    }

    // ── Logging helpers ───────────────────────────────────────────────────────
    private function logToolCall(string $toolName, array $arguments): void
    {
        Log::info("[ToolCallExecutor] Tool called: {$toolName}", $arguments);
    }

    private function logToolFailure(string $toolName, \Throwable $e): void
    {
        Log::error("[ToolCallExecutor] Tool {$toolName} failed: " . $e->getMessage());
    }
}
