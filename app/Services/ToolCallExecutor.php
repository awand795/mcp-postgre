<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * ToolCallExecutor
 *
 * Mengeksekusi tool calls yang diminta AI (OpenAI gpt-5.4).
 *
 * Core Tools:
 *   - list_tables             : Daftar tabel yang boleh diakses user
 *   - describe_table          : Struktur kolom sebuah tabel
 *   - execute_query           : Eksekusi SELECT query ke PostgreSQL
 *   - get_schema_info         : Ringkasan semua tabel + kolom sekaligus
 *   - get_business_context    : Dokumentasi KPI dan business metrics
 *
 * Analysis Tools:
 *   - analyze_trend           : Trend analysis dengan growth rates
 *   - detect_anomalies        : Z-score anomaly detection
 *   - compare_periods         : MoM/YoY period comparison
 *   - predict_future          : Linear regression forecasting
 *   - audit_dataset           : Proactive audit (anomaly + trend + Pareto)
 *   - analyze_root_cause      : KPI change decomposition
 *   - analyze_kpi_correlation : Pearson correlation analysis
 *   - forecast_metric         : Forecast dengan confidence intervals
 *   - forecast_hierarchy      : Multi-entity forecasting
 *   - detect_risk_signals     : Risk detection (Z-score + momentum)
 *   - simulate_scenario       : What-if simulation
 *   - segment_entities        : K-means clustering
 *   - analyze_cohort          : Cohort retention analysis
 *   - generate_business_insight : Executive narrative synthesis
 *
 * Database Analysis Tools (Priority #1):
 *   - analyze_relationships   : Foreign key & table dependency discovery
 *   - suggest_indexes         : Index performance analysis
 *   - check_data_quality      : Data quality checks (nulls, duplicates, consistency)
 *
 * Smart Chain Tools (Priority #2):
 *   - smart_analyze           : Auto-chain analysis (schema → query → trend → anomaly → comparison)
 *   - explain_query_plan      : PostgreSQL EXPLAIN ANALYZE
 *   - run_analysis_template   : Pre-built analysis templates
 *
 * ERP Tools:
 *   - get_erp_menu_navigation : Menu navigation tree (focused, per-module)
 *   - get_erp_guidance        : Search local ERP documentation
 *   - fetch_erp_guidance_from_web : Scrape ERP guidance website
 *   - refresh_all_erp_guidance : Batch refresh ERP docs
 */
class ToolCallExecutor
{
    // FIX: Cache allowed tables so Auth::check() is not needed inside stream
    private ?array $cachedAllowedTables = null;

    public function setAllowedTables(array $tables): void
    {
        $this->cachedAllowedTables = $tables;
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
        Log::info("[ToolCallExecutor] Tool called: {$toolName}", $arguments);

        try {
            return match ($toolName) {
                'list_tables'           => $this->listTables(),
                'describe_table'        => $this->describeTable($arguments['table_name'] ?? ''),
                'execute_query'         => $this->executeQuery($arguments['sql'] ?? '', $arguments['label'] ?? '', $arguments['currency_columns'] ?? []),
                'get_schema_info'       => $this->getSchemaInfo(),
                'get_business_context'  => $this->getBusinessContext(),
                'analyze_trend'         => $this->analyzeTrend($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? ''),
                'detect_anomalies'      => $this->detectAnomalies($arguments['data'] ?? [], $arguments['value_column'] ?? ''),
                'compare_periods'       => $this->comparePeriods($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['base_period'] ?? '', $arguments['compare_period'] ?? ''),
                'predict_future'        => $this->predictFuture($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['periods_to_project'] ?? 3),
                'audit_dataset'         => $this->auditDataset($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['label_column'] ?? ''),
                // ── Advanced Business Analyst Tools ──────────────────────────
                'analyze_root_cause'    => $this->analyzeRootCause($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['dimension_column'] ?? '', $arguments['period_column'] ?? '', $arguments['base_period'] ?? '', $arguments['compare_period'] ?? ''),
                'analyze_kpi_correlation' => $this->analyzeKpiCorrelation($arguments['data'] ?? [], $arguments['target_kpi'] ?? '', $arguments['candidate_columns'] ?? []),
                'forecast_metric'       => $this->forecastMetric($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['periods_to_forecast'] ?? 3, $arguments['include_confidence_interval'] ?? true),
                'forecast_hierarchy'    => $this->forecastHierarchy($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? '', $arguments['hierarchy_column'] ?? '', $arguments['periods_to_forecast'] ?? 3),
                'detect_risk_signals'   => $this->detectRiskSignals($arguments['data'] ?? [], $arguments['value_column'] ?? '', $arguments['period_column'] ?? ''),
                'simulate_scenario'     => $this->simulateScenario($arguments['base_data'] ?? [], $arguments['scenario_name'] ?? '', $arguments['changes'] ?? [], $arguments['output_metric'] ?? ''),
                'segment_entities'      => $this->segmentEntities($arguments['data'] ?? [], $arguments['entity_column'] ?? '', $arguments['feature_columns'] ?? [], $arguments['n_segments'] ?? 3),
                'analyze_cohort'        => $this->analyzeCohort($arguments['data'] ?? [], $arguments['entity_column'] ?? '', $arguments['period_column'] ?? '', $arguments['value_column'] ?? '', $arguments['cohort_definition_column'] ?? ''),
                'generate_business_insight' => $this->generateBusinessInsight($arguments['question'] ?? '', $arguments['data_summary'] ?? '', $arguments['trend_result'] ?? null, $arguments['anomalies'] ?? null, $arguments['root_cause'] ?? null, $arguments['forecast'] ?? null, $arguments['risks'] ?? null, $arguments['language'] ?? 'id'),
                // ── Database Analysis Tools (Priority #1) ──────────────────
                'analyze_relationships' => $this->analyzeRelationships($arguments['table_name'] ?? ''),
                'suggest_indexes'       => $this->suggestIndexes($arguments['table_name'] ?? '', $arguments['query_pattern'] ?? ''),
                'check_data_quality'    => $this->checkDataQuality($arguments['table_name'] ?? '', $arguments['check_type'] ?? 'all', $arguments['key_columns'] ?? []),
                // ── Smart Analysis Chain Tools (Priority #2) ───────────────
                'smart_analyze'         => $this->smartAnalyze($arguments['metric'] ?? '', $arguments['period'] ?? '', $arguments['breakdown_by'] ?? '', $arguments['analysis_types'] ?? ['trend', 'anomaly', 'comparison'], $arguments['top_n'] ?? 10),
                'explain_query_plan'    => $this->explainQueryPlan($arguments['sql'] ?? '', $arguments['suggestions'] ?? true),
                'run_analysis_template' => $this->runAnalysisTemplate($arguments['template'] ?? '', $arguments['period'] ?? '', $arguments['filters'] ?? []),
                'get_erp_menu_navigation' => $this->getErpMenuNavigation($arguments['module'] ?? '', $arguments['menu_keyword'] ?? ''),
                // ── ERP Guidance ───────────────────────────────────────────
                'get_erp_guidance'      => $this->getErpGuidance($arguments['keyword'] ?? '', $arguments['category'] ?? '', $arguments['list_all'] ?? false),
                'fetch_erp_guidance_from_web' => $this->fetchErpGuidanceFromWeb($arguments['url'] ?? ''),
                'refresh_all_erp_guidance'    => $this->refreshAllErpGuidance($arguments['urls'] ?? []),
                default                 => json_encode(['error' => "Unknown tool: {$toolName}"]),
            };
        } catch (\Throwable $e) {
            Log::error("[ToolCallExecutor] Tool {$toolName} failed: " . $e->getMessage());
            return json_encode(['error' => 'Permintaan tidak dapat diproses saat ini. Silakan coba lagi.']);
        }
    }

    // ── list_tables ───────────────────────────────────────────────────────────
    private function listTables(): string
    {
        $allowed = $this->getAllowedTables();
        return json_encode([
            'tables' => $allowed,
            'total'  => count($allowed),
            'schema' => 'sch_mbi',
            'note'   => 'Always prefix table names with "sch_mbi." in queries',
        ]);
    }

    // ── describe_table ────────────────────────────────────────────────────────
    private function describeTable(string $tableName): string
    {
        if (empty($tableName)) {
            return json_encode(['error' => 'table_name is required']);
        }

        $allowed = $this->getAllowedTables();
        if (!in_array($tableName, $allowed)) {
            return json_encode(['error' => "Access denied: table '{$tableName}' is not in your allowed tables list."]);
        }

        $columns = DB::connection('pgsql_mbi')->select("
            SELECT column_name, data_type, is_nullable
            FROM information_schema.columns
            WHERE table_name = ? AND table_schema = 'sch_mbi'
            ORDER BY ordinal_position
        ", [$tableName]);

        if (empty($columns)) {
            return json_encode(['error' => "Table '{$tableName}' not found or has no columns."]);
        }

        $result = [];
        foreach ($columns as $col) {
            $result[] = [
                'column'   => $col->column_name,
                'type'     => $col->data_type,
                'nullable' => $col->is_nullable,
            ];
        }

        return json_encode([
            'table'   => $tableName,
            'schema'  => 'sch_mbi',
            'sql_ref' => "sch_mbi.{$tableName}",
            'columns' => $result,
        ]);
    }

    // ── execute_query ─────────────────────────────────────────────────────────
    private function executeQuery(string $sql, string $label, array $currencyColumns = []): string
    {
        if (empty($sql)) {
            return json_encode(['error' => 'sql is required']);
        }

        // ── LAYER 1: Strip comments ──────────────────────────────────────────
        $sqlStripped = preg_replace('/--[^\n]*/', '', $sql);
        $sqlStripped = preg_replace('/\/\*.*?\*\//s', '', $sqlStripped);
        $sqlStripped = trim($sqlStripped);

        // ── LAYER 2: Harus diawali SELECT ────────────────────────────────────
        if (!preg_match('/^\s*SELECT\b/i', $sqlStripped)) {
            Log::warning("[ToolCallExecutor] Rejected non-SELECT query: " . substr($sql, 0, 200));
            return json_encode(['error' => 'Hanya query SELECT yang diizinkan.']);
        }

        // ── LAYER 3: Blokir kata kunci berbahaya ─────────────────────────────
        $forbidden = [
            'insert', 'update', 'delete', 'merge', 'upsert',
            'drop', 'truncate', 'alter', 'create', 'rename',
            'grant', 'revoke', 'execute', 'exec', 'call', 'do',
            'copy', 'vacuum', 'pg_read_file', 'pg_write_file',
            'lo_import', 'lo_export', 'dblink', 'dblink_exec',
        ];
        $lowerSql = strtolower($sqlStripped);
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $lowerSql)) {
                Log::warning("[ToolCallExecutor] Forbidden keyword '{$kw}'");
                return json_encode(['error' => "Perintah '{$kw}' tidak diizinkan."]);
            }
        }

        // ── LAYER 4: Blokir multiple statements ──────────────────────────────
        $trimmedSql = rtrim($sqlStripped, '; ');
        if (str_contains($trimmedSql, ';')) {
            return json_encode(['error' => 'Hanya satu query per panggilan.']);
        }

        // ── LAYER 5: Validasi akses tabel ─────────────────────────────────────
        $allowed = $this->getAllowedTables();
        if (preg_match_all('/(?:from|join)\s+(?:sch_mbi\.)?([a-zA-Z0-9_]+)/i', $trimmedSql, $matches)) {
            foreach ($matches[1] as $tbl) {
                $tbl = strtolower(trim($tbl));
                if (in_array($tbl, ['select', 'where', 'on', 'and', 'or', 'as', 'lateral'])) continue;
                if (!in_array($tbl, $allowed)) {
                    Log::warning("[ToolCallExecutor] Access denied to table '{$tbl}'");
                    return json_encode(['error' => "Akses ditolak: tabel '{$tbl}' tidak diizinkan."]);
                }
            }
        }

        // ── LAYER 6: Execute Query ─────────────────────────────────────────────
        $cleanSql = $trimmedSql;
        Log::info("[ToolCallExecutor] Executing SQL: " . substr($cleanSql, 0, 300));

        // FIX: Hapus SET TRANSACTION READ ONLY karena tidak kompatibel dengan Laravel DB::transaction()
        // Cukup jalankan langsung — validasi SELECT + forbidden keywords di atas sudah cukup aman
        try {
            // ANTI-LIMIT: No statement timeout for SQL execution
            DB::connection('pgsql_mbi')->statement('SET statement_timeout = 0');
            $rows = DB::connection('pgsql_mbi')->select($cleanSql);
        } catch (\Exception $e) {
            Log::error("[ToolCallExecutor] Query failed: " . $e->getMessage() . " | SQL: " . $cleanSql);
            
            $dbError = $e->getMessage();
            
            $msg = str_contains($dbError, 'statement timeout')
                ? 'Query memakan waktu terlalu lama. Coba persempit data dengan menambahkan filter tahun, bulan, atau wilayah (misal: WHERE periode_tahun = EXTRACT(YEAR FROM NOW())).'
                : "DATABASE_ERROR: {$dbError}. \n\nHINT UNTUK AI: Jika kesalahan disebabkan oleh nama kolom atau tabel yang tidak ditemukan, Anda WAJIB memanggil tool 'get_schema_info' atau 'describe_table' untuk memverifikasi struktur tabel sch_mbi yang benar sebelum mencoba query lagi. Jangan menebak nama kolom.";

            return json_encode(['error' => $msg]);
        }

        if (empty($rows)) {
            return json_encode([
                'label'   => $label,
                'total'   => 0,
                'message' => 'Tidak ada data untuk query ini.',
                'columns' => [],
                'rows'    => [],
            ]);
        }

        $data = array_map(function($row) {
            $r = (array) $row;
            foreach ($r as $k => $v) {
                if (is_string($v) && preg_match('/^-?\d+\.\d+$/', $v)) {
                    if (preg_match('/\.0+$/', $v)) {
                        $r[$k] = (int) $v;
                    } else {
                        $r[$k] = (float) $v;
                    }
                }
            }
            return $r;
        }, $rows);

        $returned = count($data);
        
        // --- SMARTER AI: Auto-detect currency columns as a safety net ---
        $detectedCurrencyCols = $this->autoDetectCurrencyColumns($data[0], $currencyColumns);

        $result = [
            'label'            => $label,
            'rows_returned'    => $returned,
            'columns'          => array_keys($data[0]),
            'currency_columns' => $detectedCurrencyCols,
            'rows'             => $data,
        ];

        // ── LAYER 7: Business Validation Note (Common Sense Check) ───────────
        $validationNotes = [];
        $monetaryCols = ['total_netto', 'total_dpp', 'harga', 'gpn', 'hpp', 'nominal'];
        
        foreach ($data as $row) {
            foreach ($row as $col => $val) {
                if (in_array(strtolower($col), $monetaryCols) && is_numeric($val) && (float)$val < 0) {
                    $validationNotes[] = "Warning: Found negative value in monetary column '{$col}'. Please verify if this is expected (e.g., returns or cancellations).";
                    break 2; // Only need one warning of this type
                }
            }
        }
        
        if (!empty($validationNotes)) {
            $result['business_validation_notes'] = $validationNotes;
        }

        // ANTI-LIMIT: Note removed for cleaner large data output
        return json_encode($result);
    }

    // ── get_schema_info ───────────────────────────────────────────────────────
    // FIX: Batasi jumlah kolom per tabel agar tidak overflow context window AI
    private function getSchemaInfo(): string
    {
        $allowed = $this->getAllowedTables();

        if (empty($allowed)) {
            return json_encode(['error' => 'Anda tidak memiliki izin untuk mengakses data. Silakan hubungi administrator.']);
        }

        // Buat placeholder untuk IN clause
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
        $fullJson = json_encode([
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
            return json_encode([
                'schema'       => 'sch_mbi',
                'total_tables' => count($compact),
                'tables'       => $compact,
                'usage_note'   => 'Schema ringkas karena terlalu besar. Gunakan describe_table untuk detail kolom lengkap.',
            ]);
        }

        return $fullJson;
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

    // ── analyze_trend ─────────────────────────────────────────────────────────
    private function analyzeTrend(array $data, string $valueCol, string $periodCol): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);
        
        $series = collect($data)->sortBy($periodCol)->values();
        $count = $series->count();
        
        if ($count < 2) return json_encode(['error' => 'Not enough data points for trend analysis.']);

        $first = (float)($series[0][$valueCol] ?? 0);
        $last = (float)($series[$count - 1][$valueCol] ?? 0);
        
        $totalGrowth = $first != 0 ? (($last - $first) / abs($first)) * 100 : 0;
        $avgGrowth = 0;
        $growths = [];

        for ($i = 1; $i < $count; $i++) {
            $prev = (float)($series[$i-1][$valueCol] ?? 0);
            $curr = (float)($series[$i][$valueCol] ?? 0);
            $g = $prev != 0 ? (($curr - $prev) / abs($prev)) * 100 : 0;
            $growths[] = $g;
        }
        
        $avgGrowth = count($growths) > 0 ? array_sum($growths) / count($growths) : 0;
        
        return json_encode([
            'trend' => $last > $first ? 'UPWARD' : ($last < $first ? 'DOWNWARD' : 'STABLE'),
            'total_growth_pct' => round($totalGrowth, 2),
            'avg_periodic_growth_pct' => round($avgGrowth, 2),
            'start_value' => $first,
            'end_value' => $last,
            'data_points' => $count
        ]);
    }

    // ── detect_anomalies ──────────────────────────────────────────────────────
    private function detectAnomalies(array $data, string $valueCol): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);

        $values = collect($data)->pluck($valueCol)->map(fn($v) => (float)$v);
        $count = $values->count();
        
        if ($count < 3) return json_encode(['error' => 'Insufficient data for anomaly detection.']);

        $avg = $values->avg();
        // Calculate Standard Deviation
        $variance = $values->reduce(fn($carry, $val) => $carry + pow($val - $avg, 2), 0) / $count;
        $stdDev = sqrt($variance);
        
        $anomalies = [];
        foreach ($data as $index => $row) {
            $val = (float)($row[$valueCol] ?? 0);
            if ($stdDev > 0) {
                $zScore = ($val - $avg) / $stdDev;
                if (abs($zScore) > 2) { // 2 Sigma Threshold
                    $anomalies[] = [
                        'row_index' => $index,
                        'value' => $val,
                        'z_score' => round($zScore, 2),
                        'severity' => abs($zScore) > 3 ? 'HIGH' : 'MEDIUM',
                        'data' => $row
                    ];
                }
            }
        }

        return json_encode([
            'avg_value' => round($avg, 2),
            'std_dev' => round($stdDev, 2),
            'anomalies_found' => count($anomalies),
            'anomalies' => $anomalies
        ]);
    }

    // ── compare_periods ───────────────────────────────────────────────────────
    private function comparePeriods(array $data, string $valueCol, string $periodCol, string $base, string $compare): string
    {
        $baseData = collect($data)->firstWhere($periodCol, $base);
        $compareData = collect($data)->firstWhere($periodCol, $compare);

        if (!$baseData || !$compareData) {
            return json_encode(['error' => "Could not find one or both periods: {$base} or {$compare}"]);
        }

        $vBase = (float)($baseData[$valueCol] ?? 0);
        $vComp = (float)($compareData[$valueCol] ?? 0);
        
        $diff = $vComp - $vBase;
        $diffPct = $vBase != 0 ? ($diff / abs($vBase)) * 100 : 0;

        return json_encode([
            'base_period' => $base,
            'compare_period' => $compare,
            'base_value' => $vBase,
            'compare_value' => $vComp,
            'absolute_difference' => $diff,
            'percentage_difference' => round($diffPct, 2),
            'status' => $diff > 0 ? 'INCREASE' : ($diff < 0 ? 'DECREASE' : 'NO_CHANGE')
        ]);
    }

    // ── predict_future ────────────────────────────────────────────────────────
    private function predictFuture(array $data, string $valueCol, string $periodCol, int $periodsToProject): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);
        
        $series = collect($data)->sortBy($periodCol)->values();
        $n = $series->count();
        
        if ($n < 3) return json_encode(['error' => 'Minimum 3 data points are required for forecasting.']);

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0; $sumYY = 0;
        
        foreach ($series as $i => $row) {
            $x = $i;
            $y = (float)($row[$valueCol] ?? 0);
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
            $sumYY += ($y * $y);
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0) return json_encode(['error' => 'Cannot calculate regression (all dates may be identical).']);

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // Calculate R-Squared (Confidence)
        $avgY = $sumY / $n;
        $ssTot = 0; $ssRes = 0;
        foreach ($series as $i => $row) {
            $y = (float)($row[$valueCol] ?? 0);
            $yPred = ($slope * $i) + $intercept;
            $ssTot += pow($y - $avgY, 2);
            $ssRes += pow($y - $yPred, 2);
        }
        $rSquared = $ssTot != 0 ? 1 - ($ssRes / $ssTot) : 0;

        $projections = [];
        for ($i = 0; $i < $periodsToProject; $i++) {
            $futureX = $n + $i;
            $val = ($slope * $futureX) + $intercept;
            $projections[] = [
                'period_index' => $futureX,
                'projected_value' => round($val, 2)
            ];
        }

        return json_encode([
            'slope' => round($slope, 2),
            'intercept' => round($intercept, 2),
            'confidence_score_r2' => round($rSquared, 2), // R^2: 1 = exact fit, 0 = no fit
            'prediction_strength' => $rSquared > 0.8 ? 'STRONG' : ($rSquared > 0.5 ? 'MODERATE' : 'WEAK'),
            'projections' => $projections,
            'message' => 'Proyeksi berdasarkan tren linear historis.'
        ]);
    }

    // ── audit_dataset (The "Proactive Insight" Wrapper) ───────────────────────
    private function auditDataset(array $data, string $valueCol, string $labelCol): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);

        $collection = collect($data);
        $total = $collection->sum($valueCol);
        
        // 1. Trend Analysis
        // Try to find a period-like column if no explicit periodCol is provided, or just use indices
        $trend = json_decode($this->analyzeTrend($data, $valueCol, $labelCol), true);
        
        // 2. Anomaly Detection
        $anomalies = json_decode($this->detectAnomalies($data, $valueCol), true);
        
        // 3. Pareto Analysis (Top Contributors)
        $sorted = $collection->sortByDesc($valueCol)->values();
        $top3 = $sorted->take(3)->map(function($row) use ($valueCol, $labelCol, $total) {
            $val = (float)$row[$valueCol];
            return [
                'label' => $row[$labelCol] ?? 'Unknown',
                'value' => $val,
                'pct' => $total != 0 ? round(($val / $total) * 100, 1) : 0
            ];
        });

        $top3Pct = $top3->sum('pct');

        // 4. Volatility (CV)
        $values = $collection->pluck($valueCol)->map(fn($v) => (float)$v);
        $mean = $values->avg();
        $variance = $values->reduce(fn($carry, $val) => $carry + pow($val - $mean, 2), 0) / $values->count();
        $stdDev = sqrt($variance);
        $volatility = $mean != 0 ? ($stdDev / abs($mean)) : 0;

        return json_encode([
            'audit_summary' => [
                'total_value' => $total,
                'volatility_score' => round($volatility, 2), // CV
                'volatility_label' => $volatility > 0.5 ? 'HIGH' : ($volatility > 0.2 ? 'MODERATE' : 'STABLE'),
                'is_concentrated' => $top3Pct > 70, // 70-80 rule
                'top_3_drivers_pct' => $top3Pct
            ],
            'top_contributors' => $top3,
            'trend_summary'    => $trend,
            'anomalies'        => $anomalies['anomalies'] ?? [],
            'strategic_hint'   => $top3Pct > 70 ? "Peringatan: Bisnis sangat bergantung pada 3 item teratas ($top3Pct% total). Risiko tinggi jika pasar bergeser." : "Distribusi bisnis cukup sehat dan tersebar."
        ]);
    }

    // ── Helper: daftar tabel yang boleh diakses ───────────────────────────────
    public function getAllowedTables(): array
    {
        // FIX: Return cached tables if already resolved (e.g., set before session_write_close)
        if ($this->cachedAllowedTables !== null) {
            return $this->cachedAllowedTables;
        }

        // Jika tidak login, tidak ada akses sama sekali
        // (route sudah dilindungi middleware 'auth', tapi ini sebagai double-check)
        if (!Auth::check()) {
            return [];
        }

        $user = Auth::user();

        if ($user->is_admin) {
            return cache()->remember('agentic_all_tables_admin', 600, function () {
                $tables = DB::connection('pgsql_mbi')->select(
                    "SELECT table_name FROM information_schema.tables WHERE table_schema = 'sch_mbi' ORDER BY table_name"
                );
                return array_column($tables, 'table_name');
            });
        }

        $roleId = $user->role;
        return cache()->remember("agentic_allowed_tables_role_{$roleId}", 600, function () use ($roleId) {
            return RolePermission::where('role_id', $roleId)->pluck('table_name')->toArray();
        });
    }

    /**
     * Helper to auto-detect currency columns based on common business naming patterns.
     * This acts as a safety net if the AI forgot to include them.
     */
    private function autoDetectCurrencyColumns(array $sampleRow, array $existingCols): array
    {
        $cols = [];
        
        // Base currency patterns (Monetary terms)
        $moneyPatterns = [
            'total_netto', 'total_dpp', 'harga', 'price', 
            'nominal', 'nilai', 'amount', 'biaya', 'fee',
            'ongkir', 'pajak', 'tax', 'diskon', 'discount',
            'laba', 'profit', 'cogs', 'gpn', 'hpp', 'netto',
            'dpp', 'saldo', 'revenue', 'omzet', 'income'
        ];

        // Exclusion patterns (Quantities, IDs, Percents)
        $excludePatterns = [
            'qty', 'count', 'jumlah', 'terjual', 'unit', 
            'stok', 'stock', 'persen', 'percent', 'pencapaian',
            'growth', 'id', 'kode', 'nomor', 'no_', 'bulan', 'tahun',
            'transaksi', 'faktur', 'nota', 'cabang', 'pelanggan',
            'barang', 'produk', 'hari', 'baris', 'freq', 'frekuensi'
        ];

        // 1. FILTER ASURANSI: Kadang AI salah memasukkan kolom non-uang ke currency_columns
        foreach ($existingCols as $col) {
            $lowCol = strtolower($col);
            $shouldExclude = false;
            foreach ($excludePatterns as $e) {
                if (str_contains($lowCol, $e)) {
                    $shouldExclude = true;
                    break;
                }
            }
            // Khusus: Kalau match exclude tapi namanya memang valid uang
            if (!$shouldExclude || in_array($lowCol, ['total_netto', 'total_dpp'])) {
                $cols[] = $col;
            }
        }

        // 2. AUTO-DETECT: Tambahkan kolom uji coba jika cocok dengan pattern uang
        $currentColsLower = array_map('strtolower', $cols);
        foreach (array_keys($sampleRow) as $col) {
            $lowCol = strtolower($col);
            
            if (in_array($lowCol, $currentColsLower)) continue;

            $isMoney = false;
            foreach ($moneyPatterns as $p) {
                if (str_contains($lowCol, $p)) {
                    $isMoney = true;
                    break;
                }
            }

            if ($isMoney) {
                $shouldExclude = false;
                foreach ($excludePatterns as $e) {
                    if (str_contains($lowCol, $e)) {
                        $shouldExclude = true;
                        break;
                    }
                }

                if (!$shouldExclude || in_array($lowCol, ['total_netto', 'total_dpp'])) {
                    $cols[] = $col;
                }
            }
        }

        return array_unique($cols);
    }

    // ── analyze_root_cause ────────────────────────────────────────────────────
    private function analyzeRootCause(array $data, string $valueCol, string $dimCol, string $periodCol, string $base, string $compare): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);

        $col = collect($data);
        $baseData    = $col->where($periodCol, $base)->values();
        $compareData = $col->where($periodCol, $compare)->values();

        if ($baseData->isEmpty() || $compareData->isEmpty()) {
            return json_encode(['error' => "Could not find periods: {$base} or {$compare} in column {$periodCol}."]);
        }

        // Index by dimension
        $baseMap    = $baseData->keyBy($dimCol);
        $compareMap = $compareData->keyBy($dimCol);

        $totalBase    = $baseData->sum(fn($r) => (float)($r[$valueCol] ?? 0));
        $totalCompare = $compareData->sum(fn($r) => (float)($r[$valueCol] ?? 0));
        $totalDelta   = $totalCompare - $totalBase;

        $drivers = [];
        $allDims = $baseMap->keys()->merge($compareMap->keys())->unique();

        foreach ($allDims as $dim) {
            $bVal = (float)(($baseMap->get($dim) ?? [])[$valueCol] ?? 0);
            $cVal = (float)(($compareMap->get($dim) ?? [])[$valueCol] ?? 0);
            $delta = $cVal - $bVal;
            $contribution = $totalDelta != 0 ? round(($delta / abs($totalDelta)) * 100, 1) : 0;
            $drivers[] = [
                'dimension'        => $dim,
                'base_value'       => $bVal,
                'compare_value'    => $cVal,
                'delta'            => round($delta, 2),
                'contribution_pct' => $contribution,
                'direction'        => $delta >= 0 ? 'POSITIVE' : 'NEGATIVE',
            ];
        }

        usort($drivers, fn($a, $b) => abs($b['delta']) <=> abs($a['delta']));

        return json_encode([
            'base_period'           => $base,
            'compare_period'        => $compare,
            'total_base'            => round($totalBase, 2),
            'total_compare'         => round($totalCompare, 2),
            'total_change'          => round($totalDelta, 2),
            'total_change_pct'      => $totalBase != 0 ? round(($totalDelta / abs($totalBase)) * 100, 2) : 0,
            'trigger_threshold_met' => abs($totalDelta / ($totalBase ?: 1)) * 100 > 3,
            'top_drivers'           => array_slice($drivers, 0, 10),
        ]);
    }

    // ── analyze_kpi_correlation ───────────────────────────────────────────────
    private function analyzeKpiCorrelation(array $data, string $targetKpi, array $candidateCols): string
    {
        if (empty($data) || empty($candidateCols)) return json_encode(['error' => 'Data or candidate_columns is empty.']);

        $n = count($data);
        if ($n < 3) return json_encode(['error' => 'Minimum 3 rows required for correlation.']);

        $yValues = array_map(fn($r) => (float)($r[$targetKpi] ?? 0), $data);
        $yMean   = array_sum($yValues) / $n;

        $correlations = [];
        foreach ($candidateCols as $col) {
            if ($col === $targetKpi) continue;
            $xValues = array_map(fn($r) => (float)($r[$col] ?? 0), $data);
            $xMean   = array_sum($xValues) / $n;

            $num = 0; $denX = 0; $denY = 0;
            for ($i = 0; $i < $n; $i++) {
                $dx   = $xValues[$i] - $xMean;
                $dy   = $yValues[$i] - $yMean;
                $num  += $dx * $dy;
                $denX += $dx * $dx;
                $denY += $dy * $dy;
            }
            $denom = sqrt($denX * $denY);
            $r     = $denom != 0 ? $num / $denom : 0;

            $correlations[] = [
                'column'    => $col,
                'r'         => round($r, 4),
                'strength'  => abs($r) > 0.7 ? 'STRONG' : (abs($r) > 0.4 ? 'MODERATE' : 'WEAK'),
                'direction' => $r >= 0 ? 'POSITIVE' : 'NEGATIVE',
            ];
        }

        usort($correlations, fn($a, $b) => abs($b['r']) <=> abs($a['r']));

        return json_encode(['target_kpi' => $targetKpi, 'correlations' => $correlations]);
    }

    // ── forecast_metric ───────────────────────────────────────────────────────
    private function forecastMetric(array $data, string $valueCol, string $periodCol, int $periods, bool $includeCI = true): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);
        $series = collect($data)->sortBy($periodCol)->values();
        $n = $series->count();
        if ($n < 3) return json_encode(['error' => 'Minimum 3 data points required.']);

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        foreach ($series as $i => $row) {
            $y = (float)($row[$valueCol] ?? 0);
            $sumX += $i; $sumY += $y; $sumXY += $i * $y; $sumXX += $i * $i;
        }
        $denom = ($n * $sumXX) - ($sumX * $sumX);
        if ($denom == 0) return json_encode(['error' => 'Cannot calculate regression.']);

        $slope     = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $intercept = ($sumY - ($slope * $sumX)) / $n;
        $avgY      = $sumY / $n;

        $ssTot = 0; $ssRes = 0; $residuals = [];
        foreach ($series as $i => $row) {
            $y    = (float)($row[$valueCol] ?? 0);
            $yHat = ($slope * $i) + $intercept;
            $ssTot   += pow($y - $avgY, 2);
            $ssRes   += pow($y - $yHat, 2);
            $residuals[] = pow($y - $yHat, 2);
        }
        $rSquared = $ssTot != 0 ? 1 - ($ssRes / $ssTot) : 0;
        $se       = $n > 2 ? sqrt($ssRes / ($n - 2)) : 0; // Standard Error

        $projections = [];
        for ($i = 0; $i < $periods; $i++) {
            $futureX = $n + $i;
            $val     = ($slope * $futureX) + $intercept;
            $proj    = ['period_index' => $futureX, 'projected_value' => round($val, 2)];
            if ($includeCI && $se > 0) {
                $proj['ci_95_lower'] = round($val - 1.96 * $se, 2);
                $proj['ci_95_upper'] = round($val + 1.96 * $se, 2);
            }
            $projections[] = $proj;
        }

        return json_encode([
            'r_squared'          => round($rSquared, 4),
            'confidence_score'   => round($rSquared, 2),
            'prediction_strength'=> $rSquared > 0.8 ? 'STRONG' : ($rSquared > 0.5 ? 'MODERATE' : 'WEAK'),
            'slope'              => round($slope, 2),
            'projections'        => $projections,
        ]);
    }

    // ── forecast_hierarchy ────────────────────────────────────────────────────
    private function forecastHierarchy(array $data, string $valueCol, string $periodCol, string $hierarchyCol, int $periods): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);

        $col      = collect($data);
        $entities = $col->pluck($hierarchyCol)->unique()->values();

        $hierarchyForecasts = [];
        $totalProjections   = array_fill(0, $periods, 0);

        foreach ($entities as $entity) {
            $entityData = $col->where($hierarchyCol, $entity)->values()->toArray();
            $decoded    = json_decode($this->forecastMetric($entityData, $valueCol, $periodCol, $periods, false), true);

            if (isset($decoded['error'])) continue;

            $projs = $decoded['projections'] ?? [];
            foreach ($projs as $idx => $p) {
                $totalProjections[$idx] += $p['projected_value'] ?? 0;
            }

            $hierarchyForecasts[] = [
                'entity'         => $entity,
                'r_squared'      => $decoded['r_squared'] ?? 0,
                'strength'       => $decoded['prediction_strength'] ?? 'N/A',
                'projections'    => $projs,
            ];
        }

        // Parent-level total forecast
        $totalData = $col->groupBy($periodCol)->map(fn($g) => [
            $periodCol => $g->first()[$periodCol],
            $valueCol  => $g->sum(fn($r) => (float)($r[$valueCol] ?? 0)),
        ])->values()->toArray();
        $totalForecast = json_decode($this->forecastMetric($totalData, $valueCol, $periodCol, $periods, false), true);
        $parentProjections = $totalForecast['projections'] ?? [];

        // Check alignment
        $aligned = true;
        foreach ($parentProjections as $idx => $p) {
            $childSum = $totalProjections[$idx] ?? 0;
            $parentVal = $p['projected_value'] ?? 0;
            if ($parentVal != 0 && abs(($childSum - $parentVal) / $parentVal) > 0.05) {
                $aligned = false;
                break;
            }
        }

        return json_encode([
            'totals_aligned'       => $aligned,
            'parent_forecast'      => $parentProjections,
            'hierarchy_forecasts'  => $hierarchyForecasts,
        ]);
    }

    // ── detect_risk_signals ───────────────────────────────────────────────────
    private function detectRiskSignals(array $data, string $valueCol, string $periodCol): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);

        $series = collect($data)->sortBy($periodCol)->values();
        $n      = $series->count();
        if ($n < 3) return json_encode(['error' => 'Minimum 3 data points required.']);

        $values = $series->map(fn($r) => (float)($r[$valueCol] ?? 0));
        $avg    = $values->avg();
        $std    = sqrt($values->reduce(fn($c, $v) => $c + pow($v - $avg, 2), 0) / $n);

        $signals = [];

        // Z-score check last point
        $last   = $values->last();
        $zScore = $std > 0 ? ($last - $avg) / $std : 0;
        if ($zScore < -1.5) {
            $signals[] = ['type' => 'ANOMALY_LOW', 'message' => "Latest value is {$zScore} std deviations below average.", 'severity' => $zScore < -2 ? 'HIGH' : 'MEDIUM'];
        }

        // Momentum: trailing 3-period slope
        $recent = $values->slice(max(0, $n - 3))->values();
        $rN     = $recent->count();
        if ($rN >= 2) {
            $momentum = ($recent->last() - $recent->first()) / $rN;
            if ($momentum < 0) {
                $signals[] = ['type' => 'NEGATIVE_MOMENTUM', 'message' => 'Declining trend in last 3 periods.', 'severity' => $momentum < -($avg * 0.1) ? 'HIGH' : 'MEDIUM'];
            }
        }

        // Consecutive decline
        $declines = 0;
        for ($i = $n - 1; $i > 0; $i--) {
            if ($values[$i] < $values[$i - 1]) $declines++;
            else break;
        }
        if ($declines >= 2) {
            $signals[] = ['type' => 'CONSECUTIVE_DECLINE', 'message' => "{$declines} consecutive periods of decline.", 'severity' => $declines >= 3 ? 'HIGH' : 'MEDIUM'];
        }

        $riskLevel = collect($signals)->pluck('severity')->contains('HIGH') ? 'HIGH'
            : (empty($signals) ? 'LOW' : 'MEDIUM');

        return json_encode([
            'risk_level'  => $riskLevel,
            'confidence'  => empty($signals) ? 0.9 : round(0.5 + min(count($signals), 3) * 0.15, 2),
            'signals'     => $signals,
            'latest_value'=> $last,
            'avg_value'   => round($avg, 2),
            'recommendation' => empty($signals)
                ? 'Performance within normal range. Continue monitoring.'
                : 'Risk signals detected. Investigate root cause and adjust strategy.',
        ]);
    }

    // ── simulate_scenario ─────────────────────────────────────────────────────
    private function simulateScenario(array $baseData, string $scenarioName, array $changes, string $outputMetric): string
    {
        if (empty($baseData)) return json_encode(['error' => 'base_data is empty.']);
        if (empty($outputMetric)) return json_encode(['error' => 'output_metric is required.']);

        $baseTotal = array_sum(array_map(fn($r) => (float)($r[$outputMetric] ?? 0), $baseData));

        $simData = $baseData;
        foreach ($changes as $change) {
            $col        = $change['column'] ?? '';
            $changeType = $change['change_type'] ?? 'pct';
            $changeVal  = (float)($change['value'] ?? 0);
            if (empty($col)) continue;

            foreach ($simData as &$row) {
                if (!isset($row[$col])) continue;
                $origVal = (float)$row[$col];
                $row[$col] = $changeType === 'pct'
                    ? $origVal * (1 + $changeVal / 100)
                    : $origVal + $changeVal;
            }
            unset($row);
        }

        $simTotal = array_sum(array_map(fn($r) => (float)($r[$outputMetric] ?? 0), $simData));
        $delta    = $simTotal - $baseTotal;
        $deltaPct = $baseTotal != 0 ? ($delta / abs($baseTotal)) * 100 : 0;

        return json_encode([
            'scenario_name'   => $scenarioName,
            'output_metric'   => $outputMetric,
            'baseline_total'  => round($baseTotal, 2),
            'simulated_total' => round($simTotal, 2),
            'delta_absolute'  => round($delta, 2),
            'delta_pct'       => round($deltaPct, 2),
            'direction'       => $delta >= 0 ? 'INCREASE' : 'DECREASE',
            'changes_applied' => $changes,
        ]);
    }

    // ── segment_entities ──────────────────────────────────────────────────────
    private function segmentEntities(array $data, string $entityCol, array $featureCols, int $nSegments): string
    {
        if (empty($data) || empty($featureCols)) return json_encode(['error' => 'Data or feature_columns is empty.']);
        $nSegments = max(2, min($nSegments, 5));

        // Normalize features
        $mins = []; $maxs = [];
        foreach ($featureCols as $fc) {
            $vals   = array_map(fn($r) => (float)($r[$fc] ?? 0), $data);
            $mins[$fc] = min($vals);
            $maxs[$fc] = max($vals);
        }

        $normalize = function(array $row) use ($featureCols, $mins, $maxs) {
            $vec = [];
            foreach ($featureCols as $fc) {
                $range = ($maxs[$fc] - $mins[$fc]);
                $vec[$fc] = $range != 0 ? ((float)($row[$fc] ?? 0) - $mins[$fc]) / $range : 0;
            }
            return $vec;
        };

        // Initialize centroids (evenly spaced on first feature)
        $centroids = [];
        for ($k = 0; $k < $nSegments; $k++) {
            $centroid = [];
            foreach ($featureCols as $fc) {
                $centroid[$fc] = $k / max(1, $nSegments - 1);
            }
            $centroids[$k] = $centroid;
        }

        $assignments = array_fill(0, count($data), 0);
        for ($iter = 0; $iter < 10; $iter++) {
            // Assign
            foreach ($data as $i => $row) {
                $vec  = $normalize($row);
                $best = 0; $bestDist = PHP_FLOAT_MAX;
                for ($k = 0; $k < $nSegments; $k++) {
                    $dist = 0;
                    foreach ($featureCols as $fc) {
                        $dist += pow(($vec[$fc] ?? 0) - ($centroids[$k][$fc] ?? 0), 2);
                    }
                    if ($dist < $bestDist) { $bestDist = $dist; $best = $k; }
                }
                $assignments[$i] = $best;
            }
            // Update centroids
            for ($k = 0; $k < $nSegments; $k++) {
                $members = array_keys(array_filter($assignments, fn($a) => $a === $k));
                if (empty($members)) continue;
                foreach ($featureCols as $fc) {
                    $centroids[$k][$fc] = array_sum(array_map(fn($i) => $normalize($data[$i])[$fc], $members)) / count($members);
                }
            }
        }

        // Build segment output
        $segments = [];
        for ($k = 0; $k < $nSegments; $k++) {
            $members = array_keys(array_filter($assignments, fn($a) => $a === $k));
            $avgFeats = [];
            foreach ($featureCols as $fc) {
                $vals = array_map(fn($i) => (float)($data[$i][$fc] ?? 0), $members);
                $avgFeats[$fc] = count($vals) ? round(array_sum($vals) / count($vals), 2) : 0;
            }
            $firstFeat = reset($avgFeats);
            $label = $firstFeat > (array_sum(array_column($data, $featureCols[0])) / count($data))
                ? 'High Performer' : 'Low Performer';
            if ($k === 1 && $nSegments > 2) $label = 'Mid Performer';

            $segments[] = [
                'segment_id'   => $k + 1,
                'label'        => $label,
                'entity_count' => count($members),
                'entities'     => array_map(fn($i) => $data[$i][$entityCol] ?? '', $members),
                'avg_features' => $avgFeats,
            ];
        }

        usort($segments, fn($a, $b) => array_sum($b['avg_features']) <=> array_sum($a['avg_features']));
        foreach ($segments as &$s) { $s['label'] = match(true) {
            array_sum($s['avg_features']) === max(array_map(fn($sg) => array_sum($sg['avg_features']), $segments)) => 'High Performer',
            array_sum($s['avg_features']) === min(array_map(fn($sg) => array_sum($sg['avg_features']), $segments)) => 'Low Performer',
            default => 'Mid Performer',
        }; }

        return json_encode(['n_segments' => $nSegments, 'feature_columns' => $featureCols, 'segments' => $segments]);
    }

    // ── analyze_cohort ────────────────────────────────────────────────────────
    private function analyzeCohort(array $data, string $entityCol, string $periodCol, string $valueCol, string $cohortDefCol): string
    {
        if (empty($data)) return json_encode(['error' => 'Data is empty.']);

        $col    = collect($data);
        $cohorts = $col->groupBy($cohortDefCol);

        $cohortResults = [];
        foreach ($cohorts as $cohortName => $cohortData) {
            $byPeriod = $cohortData->groupBy($periodCol)->map(fn($g) => $g->sum(fn($r) => (float)($r[$valueCol] ?? 0)))->sortKeys();
            $periods  = $byPeriod->count();
            if ($periods < 1) continue;

            $first = $byPeriod->first();
            $last  = $byPeriod->last();
            $retentionPct = $first != 0 ? round(($last / $first) * 100, 1) : 0;
            $trend = $last > $first ? 'GROWING' : ($last < $first ? 'DECLINING' : 'STABLE');

            $cohortResults[] = [
                'cohort'         => $cohortName,
                'entity_count'   => $cohortData->pluck($entityCol)->unique()->count(),
                'periods_tracked'=> $periods,
                'first_value'    => round($first, 2),
                'last_value'     => round($last, 2),
                'retention_pct'  => $retentionPct,
                'trend'          => $trend,
                'period_values'  => $byPeriod->toArray(),
            ];
        }

        usort($cohortResults, fn($a, $b) => $b['last_value'] <=> $a['last_value']);

        return json_encode(['cohort_dimension' => $cohortDefCol, 'cohorts' => $cohortResults]);
    }

    // ── generate_business_insight ─────────────────────────────────────────────
    private function generateBusinessInsight(string $question, string $dataSummary, ?array $trendResult, ?array $anomalies, ?array $rootCause, ?array $forecast, ?array $risks, string $language = 'id'): string
    {
        $isEN = $language === 'en';

        // Executive Summary
        $summary = $isEN
            ? "Analysis based on: {$dataSummary}."
            : "Analisis berdasarkan: {$dataSummary}.";

        if ($trendResult) {
            $dir = $trendResult['trend'] ?? 'N/A';
            $pct = $trendResult['total_growth_pct'] ?? 0;
            $summary .= $isEN
                ? " Overall trend: {$dir} ({$pct}% total growth)."
                : " Tren keseluruhan: {$dir} (pertumbuhan total {$pct}%).";
        }

        // Key Drivers
        $keyDrivers = [];
        if (!empty($rootCause['top_drivers'])) {
            foreach (array_slice($rootCause['top_drivers'], 0, 3) as $d) {
                $keyDrivers[] = ($isEN ? "**{$d['dimension']}**: " : "**{$d['dimension']}**: ") .
                    "{$d['direction']} ({$d['contribution_pct']}% contribution, delta {$d['delta']})";
            }
        }
        if (empty($keyDrivers)) {
            $keyDrivers[] = $isEN
                ? 'No root cause decomposition available. Use analyze_root_cause for deeper insight.'
                : 'Dekomposisi penyebab belum tersedia. Gunakan analyze_root_cause untuk insight lebih dalam.';
        }

        // Risk / Opportunity
        $riskSection = $isEN ? 'No significant risk signals detected.' : 'Tidak ada sinyal risiko signifikan.';
        if (!empty($risks)) {
            $level = $risks['risk_level'] ?? 'LOW';
            $riskSection = ($isEN ? "Risk level: **{$level}**. " : "Level risiko: **{$level}**. ") .
                ($risks['recommendation'] ?? '');
            if (!empty($risks['signals'])) {
                foreach ($risks['signals'] as $sig) {
                    $riskSection .= ' ' . ($sig['message'] ?? '');
                }
            }
        }
        if (!empty($anomalies)) {
            $cnt = count($anomalies);
            $riskSection .= $isEN ? " {$cnt} anomaly(ies) detected in data." : " {$cnt} anomali terdeteksi dalam data.";
        }

        // Forecast
        $forecastSection = null;
        if (!empty($forecast['projections'])) {
            $projs = array_map(fn($p) => "Period #{$p['period_index']}: " . number_format($p['projected_value'], 0, '.', '.'), $forecast['projections']);
            $forecastSection = ($isEN ? 'Forecast: ' : 'Prakiraan: ') . implode(' | ', $projs);
            $r2 = $forecast['r_squared'] ?? $forecast['confidence_score'] ?? null;
            if ($r2 !== null) {
                $forecastSection .= $isEN ? " (R²={$r2}, strength: {$forecast['prediction_strength']})" : " (R²={$r2}, kekuatan: {$forecast['prediction_strength']})";
            }
        }

        // Recommended Action
        $action = $isEN ? 'Continue monitoring key metrics and validate findings with latest data.'
            : 'Lanjutkan pemantauan metrik utama dan validasi temuan dengan data terkini.';
        if (!empty($rootCause['top_drivers'][0]['dimension'])) {
            $topDim = $rootCause['top_drivers'][0]['dimension'];
            $action = $isEN
                ? "Focus immediate attention on **{$topDim}** — the primary driver. Investigate root cause and set corrective action within 30 days."
                : "Fokuskan perhatian segera pada **{$topDim}** — sebagai driver utama. Investigasi penyebab dan tetapkan tindakan korektif dalam 30 hari.";
        }

        return json_encode([
            'executive_summary'    => $summary,
            'key_drivers'          => $keyDrivers,
            'risk_or_opportunity'  => $riskSection,
            'forecast_outlook'     => $forecastSection,
            'recommended_action'   => $action,
            'question_answered'    => $question,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // ERP MENU NAVIGATION TOOL (Option B — dedicated tool, focused results)
    // ════════════════════════════════════════════════════════════════════════

    // ── get_erp_menu_navigation ───────────────────────────────────────────
    private function getErpMenuNavigation(string $module = '', string $menuKeyword = ''): string
    {
        $navigationData = $this->getErpNavigationTree();

        // If module specified, return only that module
        if (!empty($module)) {
            $normalizedModule = $this->normalizeModuleName($module);
            if (!isset($navigationData[$normalizedModule])) {
                $availableModules = array_keys($navigationData);
                return json_encode([
                    'error' => "Modul '{$module}' tidak ditemukan.",
                    'available_modules' => $availableModules,
                    'hint' => 'Gunakan parameter "module" dengan salah satu nama modul di atas, atau kosongkan untuk melihat daftar semua modul.',
                ]);
            }

            $subMenus = $navigationData[$normalizedModule];
            $displayLines = [];

            // Corporate-style header
            $displayLines[] = "Modul **{$normalizedModule}** dapat diakses melalui **Sidebar utama → {$normalizedModule}** pada aplikasi ERP.";
            $displayLines[] = '';

            // Describe sub-menus by category with corporate guidance language
            foreach ($subMenus as $category => $items) {
                if (count($items) === 1) {
                    $displayLines[] = "Terdapat {$items[0]['description']} pada menu **{$category}**.";
                    $displayLines[] = '';
                    continue;
                }

                $displayLines[] = "Pada bagian **{$category}**, tersedia beberapa menu berikut:";
                $displayLines[] = '';

                foreach ($items as $item) {
                    $path = $item['path'];
                    $desc = $item['description'];
                    $shortPath = preg_replace('/^' . preg_quote($normalizedModule, '/') . '\s*→\s*/', '', $path);
                    $displayLines[] = "- **{$shortPath}** — {$desc}";
                }
                $displayLines[] = '';
            }

            // Closing note
            $displayLines[] = 'Silakan pilih menu sesuai kebutuhan. Jika memerlukan panduan langkah penggunaan salah satu menu di atas, saya siap membantu.';

            return json_encode([
                'module' => $normalizedModule,
                'location' => "Sidebar utama → {$normalizedModule}",
                'sub_menus' => $subMenus,
                'display_text' => implode("\n", $displayLines),
                'usage_tip' => 'Tampilkan display_text langsung ke user tanpa mengubah format.',
            ]);
        }

        // If menu_keyword specified, search across all modules
        if (!empty($menuKeyword)) {
            $keywordLower = strtolower($menuKeyword);
            $results = [];
            $displayLines = [];
            $displayLines[] = "Berikut menu yang terkait dengan **\"{$menuKeyword}**\" di sistem ERP:";
            $displayLines[] = '';

            foreach ($navigationData as $moduleName => $subMenus) {
                $matchedSubMenus = [];
                foreach ($subMenus as $category => $items) {
                    $matchedItems = [];
                    foreach ($items as $item) {
                        $path = strtolower($item['path'] ?? '');
                        $desc = strtolower($item['description'] ?? '');
                        if (strpos($path, $keywordLower) !== false || strpos($desc, $keywordLower) !== false) {
                            $matchedItems[] = $item;
                        }
                    }
                    if (!empty($matchedItems)) {
                        $matchedSubMenus[$category] = $matchedItems;
                    }
                }

                if (!empty($matchedSubMenus)) {
                    $results[$moduleName] = [
                        'module' => $moduleName,
                        'location' => "Sidebar utama → {$moduleName}",
                        'matched_sub_menus' => $matchedSubMenus,
                    ];

                    $displayLines[] = "Pada modul **{$moduleName}** (Sidebar utama → {$moduleName}):";
                    $displayLines[] = '';

                    foreach ($matchedSubMenus as $category => $items) {
                        foreach ($items as $item) {
                            $shortPath = preg_replace('/^' . preg_quote($moduleName, '/') . '\s*→\s*/', '', $item['path']);
                            $displayLines[] = "- **{$shortPath}** — {$item['description']}";
                        }
                    }
                    $displayLines[] = '';
                }
            }

            if (empty($results)) {
                return json_encode([
                    'error' => "Tidak ditemukan menu yang cocok untuk keyword '{$menuKeyword}'.",
                    'hint' => 'Coba gunakan keyword yang lebih spesifik seperti "pembayaran", "piutang", "stok", dll.',
                ]);
            }

            $displayLines[] = 'Silakan pilih menu yang sesuai. Jika memerlukan panduan langkah penggunaan, saya siap membantu.';

            return json_encode([
                'search_keyword' => $menuKeyword,
                'total_modules_matched' => count($results),
                'results' => array_values($results),
                'display_text' => implode("\n", $displayLines),
            ]);
        }

        // No filter: return list of module names only (NOT full details)
        $moduleNames = array_keys($navigationData);
        $displayLines = [];
        $displayLines[] = 'Sistem ERP memiliki beberapa modul utama yang dapat diakses melalui sidebar kiri aplikasi. Berikut daftar modul yang tersedia:';
        $displayLines[] = '';
        foreach ($moduleNames as $name) {
            $displayLines[] = "- **{$name}**";
        }
        $displayLines[] = '';
        $displayLines[] = 'Silakan sebutkan nama modul tertentu untuk melihat panduan lokasi dan daftar menu yang tersedia.';

        return json_encode([
            'message' => 'Daftar modul ERP yang tersedia. Sebutkan nama modul spesifik untuk melihat detail navigasi.',
            'modules' => array_map(fn($name) => ['name' => $name], $moduleNames),
            'display_text' => implode("\n", $displayLines),
            'usage_hint' => 'Panggil tool ini lagi dengan parameter "module" untuk melihat path navigasi lengkap satu modul tertentu.',
        ]);
    }

    // ── ERP Navigation Tree Data ──────────────────────────────────────────
    private function getErpNavigationTree(): array
    {
        return [
            'Finance' => [
                'Transaksi' => [
                    ['path' => 'Finance → Transaksi → Penyelesaian PDC/Giro Masuk', 'description' => 'Proses giro masuk yang sudah di-kliring'],
                    ['path' => 'Finance → Transaksi → Pembayaran DP Pembelian', 'description' => 'Bayar uang muka ke supplier'],
                    ['path' => 'Finance → Transaksi → Terima Pembayaran Piutang', 'description' => 'Terima piutang dari pelanggan'],
                    ['path' => 'Finance → Transaksi → Pembayaran Tagihan Hutang', 'description' => 'Bayar hutang ke supplier'],
                ],
            ],
            'Account Payable' => [
                'Transaksi' => [
                    ['path' => 'Account Payable → Transaksi → Terima Tagihan Hutang', 'description' => 'Catat tagihan hutang dari faktur pembelian'],
                    ['path' => 'Account Payable → Transaksi → Pembayaran Hutang', 'description' => 'Proses pembayaran hutang dagang'],
                ],
            ],
            'Account Receivable' => [
                'Transaksi' => [
                    ['path' => 'Account Receivable → Transaksi → Terima Penagihan Piutang', 'description' => 'Catat hasil penagihan dari sales/collector'],
                    ['path' => 'Account Receivable → Transaksi → Pelunasan Piutang', 'description' => 'Lunasi piutang setelah pembayaran diterima'],
                ],
                'Cetak' => [
                    ['path' => 'Account Receivable → Cetak → Cetak Tagihan Piutang', 'description' => 'Cetak tagihan piutang pelanggan'],
                ],
            ],
            'Inventory' => [
                'Transaksi' => [
                    ['path' => 'Inventory → Transaksi → Order Pembelian', 'description' => 'Buat purchase order ke supplier'],
                    ['path' => 'Inventory → Transaksi → Permintaan Pembelian', 'description' => 'Request pembelian dari cabang'],
                    ['path' => 'Inventory → Transaksi → Penerimaan Barang', 'description' => 'Terima barang dari supplier'],
                    ['path' => 'Inventory → Transaksi → Pengeluaran Barang', 'description' => 'Keluarkan barang dari gudang'],
                    ['path' => 'Inventory → Transaksi → Penyesuaian Stok', 'description' => 'Sesuaikan stok fisik dengan sistem'],
                    ['path' => 'Inventory → Transaksi → Klaim Barang', 'description' => 'Proses klaim barang retur/rusak'],
                ],
                'Pembelian' => [
                    ['path' => 'Inventory → Transaksi → Pembelian → Pengajuan DP Pembelian', 'description' => 'Ajukan DP untuk PO'],
                ],
                'Insentif Sales' => [
                    ['path' => 'Inventory → Transaksi → Insentif Sales → Perhitungan Insentif Sales', 'description' => 'Hitung insentif tim penjualan'],
                    ['path' => 'Inventory → Transaksi → Insentif Sales → Pengajuan Proposal Insentif', 'description' => 'Buat proposal insentif'],
                ],
                'Lain-lain' => [
                    ['path' => 'Inventory → Transaksi → Lain-lain → Penerimaan Lain-lain — HPP', 'description' => 'Set HPP untuk penerimaan khusus'],
                ],
            ],
            'Warehouse' => [
                'Navigasi' => [
                    ['path' => 'Warehouse → Transfer Antar Gudang', 'description' => 'Pindah barang antar gudang'],
                    ['path' => 'Warehouse → Opname', 'description' => 'Stock opname fisik vs sistem'],
                    ['path' => 'Warehouse → Mutasi Stok', 'description' => 'Pindah stok antar lokasi/gudang'],
                ],
            ],
            'Report Center' => [
                'Fitur' => [
                    ['path' => 'Report Center → Laporan Tersedia', 'description' => 'Daftar semua laporan yang bisa diakses'],
                    ['path' => 'Report Center → Riwayat Laporan', 'description' => 'Histori laporan yang pernah dibuka'],
                    ['path' => 'Report Center → Setting', 'description' => 'Kustomisasi kolom & format laporan (PDF, XLS, CSV)'],
                ],
            ],
            'Document' => [
                'Navigasi' => [
                    ['path' => 'Document → Serah Dokumen', 'description' => 'Proses serah terima dokumen antar departemen'],
                    ['path' => 'Document → Nota Kredit Penjualan', 'description' => 'Buat nota kredit untuk retur penjualan'],
                ],
            ],
        ];
    }

    // ── Normalize module name for matching ────────────────────────────────
    private function normalizeModuleName(string $name): string
    {
        $normalized = strtolower(trim($name));

        // Direct mapping
        $map = [
            'finance' => 'Finance',
            'account payable' => 'Account Payable',
            'ap' => 'Account Payable',
            'hutang' => 'Account Payable',
            'account receivable' => 'Account Receivable',
            'ar' => 'Account Receivable',
            'piutang' => 'Account Receivable',
            'inventory' => 'Inventory',
            'inventory management' => 'Inventory',
            'warehouse' => 'Warehouse',
            'gudang' => 'Warehouse',
            'report center' => 'Report Center',
            'report' => 'Report Center',
            'laporan' => 'Report Center',
            'document' => 'Document',
            'dokumen' => 'Document',
        ];

        return $map[$normalized] ?? $this->fuzzyMatchModule($normalized);
    }

    // ── Fuzzy match module name ───────────────────────────────────────────
    private function fuzzyMatchModule(string $input): string
    {
        $modules = array_keys($this->getErpNavigationTree());

        foreach ($modules as $module) {
            if (stripos(strtolower($module), $input) !== false || stripos($input, strtolower($module)) !== false) {
                return $module;
            }
        }

        return ''; // No match
    }

    // ════════════════════════════════════════════════════════════════════════
    // ERP GUIDANCE TOOLS
    // ════════════════════════════════════════════════════════════════════════

    // ── get_erp_guidance ────────────────────────────────────────────────────────
    private function getErpGuidance(string $keyword = '', string $category = '', bool $listAll = false): string
    {
        $path = config_path('erp_guidance.json');
        
        if (!file_exists($path)) {
            Log::error("[ToolCallExecutor] ERP Guidance file not found at: {$path}");
            return json_encode([
                'error' => 'Data panduan ERP belum tersedia atau file konfigurasi tidak ditemukan.'
            ]);
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (!$data || !isset($data['guides'])) {
            return json_encode([
                'error' => 'Format file panduan ERP tidak valid.'
            ]);
        }

        $guides = $data['guides'];
        $results = [];

        // Jika minta semua, kirimkan daftar judul/akses saja (karena kepanjangan kalau diload utuh)
        if ($listAll) {
            $summary = array_map(function($g) {
                return [
                    'id' => $g['id'] ?? '',
                    'title' => $g['title'] ?? '',
                    'category' => $g['category'] ?? '',
                    // Cuplikan singkat dari detail
                    'summary' => substr($g['detail_panduan_lengkap'] ?? '', 0, 100) . '...'
                ];
            }, $guides);

            return json_encode([
                'source' => $data['source'] ?? '',
                'total_found' => count($summary),
                'message' => 'Ini adalah daftar kategori dan judul panduan yang tersedia. Jika ingin melihat detail langkah-langkah, lakukan pencarian dengan parameter keyword spesifik sesuai judul ini.',
                'guides' => $summary
            ]);
        }

        // Kalau tidak cari keyword/kategori tapi mau akses list/search
        if (empty($keyword) && empty($category)) {
             return json_encode([
                'message' => 'Harap berikan kata kunci atau kategori untuk mencari panduan.'
            ]);
        }

        $keywordLower = strtolower(trim($keyword));
        $categoryLower = strtolower(trim($category));

        // First pass: searching and scoring
        foreach ($guides as $guide) {
            $score = 0;
            $gTitle = strtolower($guide['title'] ?? '');
            $gDetail = strtolower($guide['detail_panduan_lengkap'] ?? '');

            $gKeys = [];
            if (isset($guide['keywords']) && is_array($guide['keywords'])) {
                $gKeys = array_map('strtolower', $guide['keywords']);
            }

            // Category Bonus (Replaced hard filter with priority boost)
            if (!empty($categoryLower)) {
                $gCat = strtolower($guide['category'] ?? '');
                if (strpos($gCat, $categoryLower) !== false) {
                    $score += 200; // Priority boost for target category
                }
            }

            if (!empty($keywordLower)) {
                // Normalize search keyword: collapse multiple spaces, dashes, underscores
                $normalizedKeyword = preg_replace('/[\s\-_]+/', ' ', trim($keywordLower));
                $keywordTokens = explode(' ', $normalizedKeyword);
                $keywordTokens = array_filter($keywordTokens, fn($t) => strlen($t) > 0);

                // Tier 1: Title match (Strongest signal)
                $normalizedGTitle = preg_replace('/[\s\-_]+/', ' ', $gTitle);
                if (strpos($normalizedGTitle, $normalizedKeyword) !== false) {
                    $score += 500; // Contains
                    if (strpos($normalizedGTitle, $normalizedKeyword) === 0) $score += 300; // Starts with
                    if ($normalizedGTitle === $normalizedKeyword) $score += 1000; // Exact match
                } else {
                    // Partial title match: check if most keyword tokens appear in title
                    $matchedTokens = 0;
                    foreach ($keywordTokens as $token) {
                        if (strpos($normalizedGTitle, $token) !== false) {
                            $matchedTokens++;
                        }
                    }
                    if ($matchedTokens > 0 && $matchedTokens <= count($keywordTokens)) {
                        $matchRatio = $matchedTokens / count($keywordTokens);
                        if ($matchRatio >= 0.5) {
                            $score += 300 * $matchRatio; // Partial match bonus
                        }
                    }
                }

                // Tier 2: Keyword match (bidirectional matching)
                foreach ($gKeys as $key) {
                    $normalizedKey = preg_replace('/[\s\-_]+/', ' ', $key);
                    
                    // Check if search keyword is in stored keyword
                    if (strpos($normalizedKey, $normalizedKeyword) !== false) {
                        $score += 100;
                        if ($normalizedKey === $normalizedKeyword) $score += 300;
                        break;
                    }
                    
                    // Check if stored keyword is in search keyword (reverse match)
                    if (strpos($normalizedKeyword, $normalizedKey) !== false) {
                        $score += 80;
                        break;
                    }
                    
                    // Token-based matching: check if most tokens match
                    $keyTokens = explode(' ', $normalizedKey);
                    $keyTokens = array_filter($keyTokens, fn($t) => strlen($t) > 0);
                    $matchedTokens = 0;
                    foreach ($keywordTokens as $token) {
                        foreach ($keyTokens as $keyToken) {
                            if (strpos($keyToken, $token) !== false || strpos($token, $keyToken) !== false) {
                                $matchedTokens++;
                                break;
                            }
                        }
                    }
                    if ($matchedTokens > 0 && count($keyTokens) > 0) {
                        $matchRatio = $matchedTokens / max(count($keywordTokens), count($keyTokens));
                        if ($matchRatio >= 0.6) {
                            $score += 60 * $matchRatio;
                            break;
                        }
                    }
                }

                // Tier 3: Detail match (Low priority fallback)
                if (strpos($gDetail, $keywordLower) !== false) {
                    $score += 1;
                }
            } else {
                $score = 1;
            }

            if ($score > 0) {
                // Prepend category to title for better AI disambiguation
                $catPrefix = "[" . ($guide['category'] ?? 'General') . "] ";
                $guide['title'] = $catPrefix . ($guide['title'] ?? 'Untitled');

                $guide['_relevance_score'] = $score;
                $results[] = $guide;
            }
        }

        // Sort results by relevance score descending
        usort($results, function($a, $b) {
            return ($b['_relevance_score'] ?? 0) <=> ($a['_relevance_score'] ?? 0);
        });

        // Noise Suppression: If we have a very strong Title-Match result (> 500), 
        // filter out all low-confidence matches (< 10) to avoid AI confusion.
        if (!empty($results) && ($results[0]['_relevance_score'] ?? 0) >= 500) {
            $results = array_filter($results, function($r) {
                return ($r['_relevance_score'] ?? 0) >= 10;
            });
            $results = array_values($results); // Re-index
        }

        // Limit results to top 5 to prevent context overflow and AI selection fatigue
        $results = array_slice($results, 0, 5);

        // Clean up internal score before returning
        foreach ($results as &$r) {
            unset($r['_relevance_score']);
        }

        if (empty($results)) {
             return json_encode([
                'total_found' => 0,
                'message' => 'Tidak ditemukan panduan ERP yang cocok dengan kriteria pencarian: ' . ($keyword ?: $category),
            ]);
        }

        return json_encode([
            'total_found' => count($results),
            'source'      => $data['source'] ?? '',
            'guides'      => $results
        ]);
    }

    /**
     * ── fetch_erp_guidance_from_web ──────────────────────────────────────────
     * Mengambil panduan langsung dari web (scraping) dengan login.
     */
    private function fetchErpGuidanceFromWeb(string $url): string
    {
        if (empty($url)) {
            return json_encode(['error' => 'URL wajib diisi.']);
        }

        if (!str_contains($url, 'erp-guidance.online')) {
            return json_encode(['error' => 'Hanya URL dari erp-guidance.online yang diizinkan.']);
        }

        try {
            Log::info("[ToolCallExecutor] Fetching ERP Guidance from web: {$url}");

            $response = $this->requestWithAuth($url);

            if (!$response->successful()) {
                return json_encode(['error' => "Gagal mengambil halaman. Status: " . $response->status()]);
            }

            $html = $response->body();
            $data = $this->parseErpGuidancePage($html, $url);

            if (isset($data['error'])) {
                return json_encode($data);
            }

            // Opsi: Simpan ke local JSON jika belum ada atau ingin update
            $this->updateLocalGuidance($data);

            return json_encode([
                'message' => 'Panduan berhasil diambil dari web.',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error("[ToolCallExecutor] fetchErpGuidanceFromWeb failed: " . $e->getMessage());
            return json_encode(['error' => 'Terjadi kesalahan saat mengambil data dari web.']);
        }
    }

    private function requestWithAuth(string $url)
    {
        $email = env('ERP_GUIDANCE_EMAIL');
        $password = env('ERP_GUIDANCE_PASSWORD');

        if (!$email || !$password) {
            throw new \Exception("Kredensial ERP Guidance belum dikonfigurasi di .env");
        }

        $loginUrl = 'https://erp-guidance.online/wp-login.php';
        $cookieJar = new \GuzzleHttp\Cookie\CookieJar();
        
        // Step 1: Login
        Http::asForm()->withOptions([
            'cookies' => $cookieJar,
            'allow_redirects' => true
        ])->post($loginUrl, [
            'log' => $email,
            'pwd' => $password,
            'wp-submit' => 'Log In',
            'testcookie' => 1
        ]);

        // Step 2: Fetch the actual URL with the same cookies
        return Http::withOptions([
            'cookies' => $cookieJar,
            'allow_redirects' => true
        ])->get($url);
    }

    private function parseErpGuidancePage(string $html, string $url): array
    {
        $crawler = new Crawler($html);

        if ($crawler->filter('form#loginform')->count() > 0) {
            Log::warning("[ToolCallExecutor] Scraper hit login page at: " . $url);
            return ['error' => 'Gagal login ke website. Periksa kredensial di .env'];
        }

        $title = $crawler->filter('h1.entry-title')->count() > 0 
            ? $crawler->filter('h1.entry-title')->text() 
            : 'Tanpa Judul';

        $contentNode = $crawler->filter('.entry-content');
        if ($contentNode->count() === 0) {
            return ['error' => 'Konten panduan tidak ditemukan di halaman ini.'];
        }

        // --- SECTION EXTRACTION & PREMIUM FORMATTING ---
        $sections = [
            'fungsi' => '',
            'persyaratan' => '',
            'petunjuk' => '',
            'catatan' => '',
            'video' => ''
        ];

        $currentSection = '';
        $formFields = [];
        $images = [];

        // Parse content elements for sectioning
        $contentNode->filter('h2, h3, h4, p, li, b, strong')->each(function (Crawler $node) use (&$sections, &$currentSection, &$formFields, &$images) {
            $text = trim($node->text());
            if (empty($text)) return;

            $lowerText = strtolower($text);
            if (str_contains($lowerText, 'fungsi :') || str_contains($lowerText, 'fungsi:')) {
                $currentSection = 'fungsi';
                $sections['fungsi'] .= str_replace(['Fungsi :', 'Fungsi:'], '', $text) . " ";
            } elseif (str_contains($lowerText, 'persyaratan data') || str_contains($lowerText, 'persyaratan:')) {
                $currentSection = 'persyaratan';
            } elseif (str_contains($lowerText, 'petunjuk pemakaian')) {
                $currentSection = 'petunjuk';
            } elseif (str_contains($lowerText, 'catatan :') || str_contains($lowerText, 'catatan:')) {
                $currentSection = 'catatan';
            } elseif (str_contains($lowerText, 'video :') || str_contains($lowerText, 'video:')) {
                $currentSection = 'video';
            } else {
                if ($currentSection && isset($sections[$currentSection])) {
                    $sections[$currentSection] .= $text . " ";
                }

                // Detect form fields while parsing text
                if (preg_match('/^([a-zA-Z0-9\.\s\/]+)\s*[:\-]\s*(.+)$/', $text, $matches)) {
                    $formFields[] = [
                        'field' => trim($matches[1]),
                        'description' => trim($matches[2])
                    ];
                }
            }
        });

        // Extract Images & Descriptions
        $contentNode->filter('img')->each(function (Crawler $node) use (&$images) {
            $src = $node->attr('src');
            $alt = $node->attr('alt');
            $caption = "";
            $parent = $node->closest('.wp-caption, figure');
            if ($parent && $parent->filter('.wp-caption-text, figcaption')->count() > 0) {
                $caption = $parent->filter('.wp-caption-text, figcaption')->text();
            }
            $images[] = ['src' => $src, 'alt' => $alt ?: 'Gambar Panduan', 'caption' => $caption];
        });

        // --- IMPROVED STEP-BY-STEP LOGIC ---
        $petunjukText = trim($sections['petunjuk']);
        $steps = [];
        
        // Split by sentences that start with common action verbs OR punctuation followed by space
        $rawSteps = preg_split('/(?<=[.!?])\s+(?=[A-Z])|(?<=[.!?])\s+(?=Klik)|(?<=[.!?])\s+(?=Pilih)|(?<=[.!?])\s+(?=Buka)|(?<=[.!?])\s+(?=Isi)|(?<=[.!?])\s+(?=Simpan)/', $petunjukText);
        
        $stepCounter = 1;
        foreach ($rawSteps as $rs) {
            $rs = trim($rs);
            if (empty($rs)) continue;
            // Detect step-like headers (e.g., - Input, - Update) and avoid making them steps if they are titles
            if (preg_match('/^[-–—]\s*(Input|Update)/i', $rs)) {
                $steps[] = "### " . ltrim($rs, '-–— ');
                continue;
            }
            $steps[] = ($stepCounter++) . ". " . $rs;
        }

        // --- GENERATE PREMIUM MARKDOWN ---
        $markdown = "# " . $title . " 🚀\n\n";
        
        if (!empty($sections['fungsi'])) {
            $markdown .= "> **Fungsi:** " . trim($sections['fungsi']) . "\n\n---\n\n";
        }

        if (!empty($sections['persyaratan'])) {
            $markdown .= "### 📋 Persyaratan Data\n" . trim($sections['persyaratan']) . "\n\n---\n\n";
        }

        $markdown .= "### 🛠️ Langkah-Langkah Pemakaian (Step-by-Step)\n" . implode("\n", $steps) . "\n\n";

        if (!empty($formFields)) {
            $markdown .= "#### 📝 Bidang Isian (Form Fields)\n";
            $markdown .= "> Berikut adalah rincian data yang harus Anda isi pada formulir ini:\n\n";
            $markdown .= "| Nama Field | Deskripsi / Petunjuk |\n";
            $markdown .= "| :--- | :--- |\n";
            foreach ($formFields as $ff) {
                $markdown .= "| **" . $ff['field'] . "** | " . $ff['description'] . " |\n";
            }
            $markdown .= "\n";
        }

        if (!empty($images)) {
            $markdown .= "#### 🖼️ Panduan Visual (Screenshot)\n";
            foreach ($images as $img) {
                if (!empty($img['caption'])) {
                    $markdown .= "- **" . $img['caption'] . "**: Menjelaskan antarmuka bagian ini.\n";
                }
            }
            $markdown .= "\n";
        }

        if (!empty($sections['catatan'])) {
            $markdown .= "---\n\n### 💡 Catatan Penting\n" . trim($sections['catatan']) . "\n\n";
        }

        $videoUrl = ($crawler->filter('video source')->count() > 0) 
            ? $crawler->filter('video source')->attr('src') 
            : (str_contains($sections['video'] ?? '', 'http') ? trim($sections['video']) : null);

        return [
            'id' => md5($url),
            'title' => $title,
            'category' => $this->guessCategory($url, $html),
            'url' => $url,
            'detail_panduan_lengkap' => $markdown,
            'form_fields' => $formFields,
            'images' => $images,
            'video' => $videoUrl,
            'last_fetched' => date('Y-m-d H:i:s')
        ];
    }

    private function guessCategory(string $url, string $html): string
    {
        if (str_contains($url, 'account-payable')) return 'Account Payable';
        if (str_contains($url, 'account-receivable')) return 'Account Receivable';
        if (str_contains($url, 'inventory')) return 'Inventory';
        if (str_contains($url, 'finance')) return 'Finance';
        if (str_contains($url, 'warehouse')) return 'Warehouse';
        if (str_contains($url, 'document')) return 'Document';
        return 'Uncategory';
    }

    private function updateLocalGuidance(array $newData): void
    {
        $path = config_path('erp_guidance.json');
        if (!file_exists($path)) return;

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (!isset($data['guides'])) return;

        // Cek apakah sudah ada (berdasarkan title atau URL)
        $found = false;
        foreach ($data['guides'] as &$guide) {
            if (($guide['title'] ?? '') === $newData['title']) {
                $guide = array_merge($guide, $newData);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $data['guides'][] = $newData;
            $data['total_guides'] = count($data['guides']);
        }

        $data['last_updated'] = date('Y-m-d H:i:s');
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    public function refreshAllErpGuidance(array $urls): string
    {
        if (empty($urls)) {
            return json_encode(['error' => 'Daftar URL kosong.']);
        }

        $results = [
            'success_count' => 0,
            'failed_count' => 0,
            'errors' => []
        ];

        foreach ($urls as $url) {
            try {
                $response = $this->requestWithAuth($url);
                if ($response->successful()) {
                    $parsed = $this->parseErpGuidancePage($response->body(), $url);
                    if (isset($parsed['error'])) {
                        $results['failed_count']++;
                        $results['errors'][] = "[$url] " . $parsed['error'];
                        continue;
                    }

                    $this->updateLocalGuidance($parsed);
                    $results['success_count']++;
                } else {
                    $results['failed_count']++;
                    $results['errors'][] = "[$url] HTTP " . $response->status();
                }
            } catch (\Exception $e) {
                $results['failed_count']++;
                $results['errors'][] = "[$url] " . $e->getMessage();
            }
        }

        return json_encode([
            'message' => "Proses pembaruan selesai.",
            'summary' => $results
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRIORITY #1: DATABASE ANALYSIS TOOLS
    // ════════════════════════════════════════════════════════════════════════

    // ── analyze_relationships ─────────────────────────────────────────────
    private function analyzeRelationships(string $tableName = ''): string
    {
        $allowed = $this->getAllowedTables();

        try {
            // Query PostgreSQL system catalogs for foreign key relationships
            $tableFilter = '';
            $params = [];

            if (!empty($tableName)) {
                if (!in_array($tableName, $allowed)) {
                    return json_encode(['error' => "Access denied: table '{$tableName}' is not in your allowed list."]);
                }
                $tableFilter = "AND (tc.table_name = ? OR ccu.table_name = ?)";
                $params = [$tableName, $tableName];
            }

            $relationships = DB::connection('pgsql_mbi')->select("
                SELECT
                    tc.table_name AS source_table,
                    kcu.column_name AS source_column,
                    ccu.table_name AS target_table,
                    ccu.column_name AS target_column,
                    tc.constraint_name
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name
                    AND tc.table_schema = kcu.table_schema
                JOIN information_schema.constraint_column_usage AS ccu
                    ON ccu.constraint_name = tc.constraint_name
                    AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                    AND tc.table_schema = 'sch_mbi'
                    {$tableFilter}
                ORDER BY tc.table_name, ccu.table_name
            ", $params);

            // Also check for implicit relationships via column naming patterns
            // (e.g., kolom bernama id_pelanggan likely references pelanggan table)
            $implicitRelationships = [];
            $tablesToCheck = !empty($tableName) ? [$tableName] : $allowed;

            foreach ($tablesToCheck as $tbl) {
                $columns = DB::connection('pgsql_mbi')->select("
                    SELECT column_name
                    FROM information_schema.columns
                    WHERE table_schema = 'sch_mbi'
                        AND table_name = ?
                        AND (column_name LIKE 'id_%' OR column_name LIKE '%_id')
                ", [$tbl]);

                foreach ($columns as $col) {
                    $colName = $col->column_name;
                    // Extract potential referenced table name
                    $potentialTable = str_replace(['id_', '_id'], ['', ''], $colName);
                    // Handle edge cases
                    if (empty($potentialTable) || strlen($potentialTable) < 2) continue;

                    // Check if potential table exists in allowed tables
                    $normalizedTarget = null;
                    foreach ($allowed as $a) {
                        if (stripos($a, $potentialTable) !== false || stripos($potentialTable, $a) !== false) {
                            $normalizedTarget = $a;
                            break;
                        }
                    }

                    if ($normalizedTarget) {
                        $implicitRelationships[] = [
                            'source_table' => $tbl,
                            'source_column' => $colName,
                            'target_table' => $normalizedTarget,
                            'target_column' => 'id', // assumed primary key
                            'type' => 'implicit (naming pattern)',
                        ];
                    }
                }
            }

            $result = [];

            // Format explicit FK relationships
            foreach ($relationships as $rel) {
                $key = $rel->source_table . '.' . $rel->source_column;
                if (!isset($result[$key])) {
                    $result[$key] = [
                        'source_table' => $rel->source_table,
                        'source_column' => $rel->source_column,
                        'references' => [],
                        'constraint_name' => $rel->constraint_name,
                        'type' => 'explicit (foreign key)',
                    ];
                }
                $result[$key]['references'][] = [
                    'table' => $rel->target_table,
                    'column' => $rel->target_column,
                ];
            }

            // Add implicit relationships
            foreach ($implicitRelationships as $imp) {
                $key = $imp['source_table'] . '.' . $imp['source_column'];
                if (!isset($result[$key])) {
                    $result[$key] = [
                        'source_table' => $imp['source_table'],
                        'source_column' => $imp['source_column'],
                        'references' => [],
                        'type' => $imp['type'],
                    ];
                }
                $result[$key]['references'][] = [
                    'table' => $imp['target_table'],
                    'column' => $imp['target_column'],
                ];
            }

            // Build a relationship graph summary
            $graphSummary = [];
            foreach ($result as $rel) {
                foreach ($rel['references'] as $ref) {
                    $edge = $rel['source_table'] . ' → ' . $ref['table'];
                    if (!isset($graphSummary[$edge])) {
                        $graphSummary[$edge] = [
                            'from' => $rel['source_table'],
                            'to' => $ref['table'],
                            'columns' => [],
                        ];
                    }
                    $graphSummary[$edge]['columns'][] = $rel['source_column'] . ' → ' . $ref['column'];
                }
            }

            return json_encode([
                'total_relationships' => count($result),
                'explicit_fks' => count(array_filter($result, fn($r) => $r['type'] === 'explicit (foreign key)')),
                'implicit_patterns' => count(array_filter($result, fn($r) => $r['type'] === 'implicit (naming pattern)')),
                'relationships' => array_values($result),
                'relationship_graph' => array_values($graphSummary),
                'usage_hint' => 'Use these relationships to write accurate JOIN queries. Explicit FK relationships are guaranteed; implicit ones should be verified.',
            ]);

        } catch (\Throwable $e) {
            return json_encode(['error' => 'Failed to analyze relationships: ' . $e->getMessage()]);
        }
    }

    // ── suggest_indexes ───────────────────────────────────────────────────
    private function suggestIndexes(string $tableName, string $queryPattern = ''): string
    {
        if (empty($tableName)) {
            return json_encode(['error' => 'table_name is required.']);
        }

        $allowed = $this->getAllowedTables();
        if (!in_array($tableName, $allowed)) {
            return json_encode(['error' => "Access denied: table '{$tableName}' is not in your allowed list."]);
        }

        try {
            // Get existing indexes
            $existingIndexes = DB::connection('pgsql_mbi')->select("
                SELECT
                    i.relname AS index_name,
                    ix.indisunique AS is_unique,
                    ix.indisprimary AS is_primary,
                    array_to_string(
                        array(
                            SELECT pg_get_indexdef(ix.indexrelid, k + 1, true)
                            FROM generate_subscripts(ix.indkey, 1) AS k
                            ORDER BY k
                        ), ','
                    ) AS columns
                FROM pg_index ix
                JOIN pg_class t ON t.oid = ix.indrelid
                JOIN pg_class i ON i.oid = ix.indexrelid
                JOIN pg_namespace n ON n.oid = t.relnamespace
                WHERE t.relname = ?
                    AND n.nspname = 'sch_mbi'
            ", [$tableName]);

            // Get table columns and their cardinality estimates
            // First, get column names
            $columnList = DB::connection('pgsql_mbi')->select("
                SELECT column_name FROM information_schema.columns
                WHERE table_schema = 'sch_mbi' AND table_name = ?
                ORDER BY ordinal_position
            ", [$tableName]);

            $columns = [];
            foreach ($columnList as $colInfo) {
                $colName = $colInfo->column_name;
                $distinctResult = DB::connection('pgsql_mbi')->select(
                    "SELECT COUNT(DISTINCT \"{$colName}\") AS distinct_count FROM sch_mbi.{$tableName}"
                );
                $distinctCount = $distinctResult[0]->distinct_count ?? 0;

                $totalResult = DB::connection('pgsql_mbi')->select(
                    "SELECT COUNT(*) AS total_rows FROM sch_mbi.{$tableName}"
                );
                $totalRows = $totalResult[0]->total_rows ?? 0;

                $columns[] = (object)[
                    'column_name' => $colName,
                    'data_type' => $colInfo->data_type ?? 'unknown',
                    'total_rows' => $totalRows,
                    'distinct_count' => $distinctCount,
                ];
            }

            $suggestions = [];

            // Analyze columns for index candidates
            foreach ($columns as $col) {
                if ($col->total_rows == 0) continue;

                $selectivity = $col->distinct_count / $col->total_rows;

                // High selectivity columns are good index candidates
                if ($selectivity > 0.5 && $col->distinct_count > 10) {
                    // Check if already indexed
                    $isIndexed = false;
                    foreach ($existingIndexes as $idx) {
                        if (stripos($idx->columns, $col->column_name) !== false) {
                            $isIndexed = true;
                            break;
                        }
                    }

                    if (!$isIndexed) {
                        $suggestions[] = [
                            'column' => $col->column_name,
                            'selectivity' => round($selectivity, 3),
                            'distinct_values' => $col->distinct_count,
                            'total_rows' => $col->total_rows,
                            'suggested_index' => "CREATE INDEX idx_{$tableName}_{$col->column_name} ON sch_mbi.{$tableName} ({$col->column_name})",
                            'reason' => 'High selectivity — good for filtering and JOINs',
                            'priority' => $selectivity > 0.9 ? 'HIGH' : ($selectivity > 0.7 ? 'MEDIUM' : 'LOW'),
                        ];
                    }
                }
            }

            // Check for common query pattern columns (WHERE, ORDER BY, GROUP BY candidates)
            $commonPatterns = ['id', 'kode', 'tanggal', 'created_at', 'updated_at', 'status', 'kategori'];
            foreach ($commonPatterns as $pattern) {
                foreach ($columns as $col) {
                    if (stripos($col->column_name, $pattern) !== false) {
                        $isIndexed = false;
                        foreach ($existingIndexes as $idx) {
                            if (stripos($idx->columns, $col->column_name) !== false) {
                                $isIndexed = true;
                                break;
                            }
                        }

                        if (!$isIndexed) {
                            $suggestions[] = [
                                'column' => $col->column_name,
                                'suggested_index' => "CREATE INDEX idx_{$tableName}_{$col->column_name} ON sch_mbi.{$tableName} ({$col->column_name})",
                                'reason' => "Common query pattern column (contains '{$pattern}') — likely used in WHERE/ORDER BY",
                                'priority' => 'MEDIUM',
                            ];
                        }
                    }
                }
            }

            // Sort by priority
            $priorityOrder = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];
            usort($suggestions, function ($a, $b) use ($priorityOrder) {
                return ($priorityOrder[$a['priority']] ?? 3) - ($priorityOrder[$b['priority']] ?? 3);
            });

            return json_encode([
                'table' => $tableName,
                'existing_indexes' => count($existingIndexes),
                'index_details' => $existingIndexes,
                'suggestions' => $suggestions,
                'total_suggestions' => count($suggestions),
                'usage_note' => 'Review suggestions before creating indexes. HIGH priority = most impact. Consult DBA before production changes.',
            ]);

        } catch (\Throwable $e) {
            return json_encode(['error' => 'Failed to analyze indexes: ' . $e->getMessage()]);
        }
    }

    // ── check_data_quality ────────────────────────────────────────────────
    private function checkDataQuality(string $tableName, string $checkType = 'all', array $keyColumns = []): string
    {
        if (empty($tableName)) {
            return json_encode(['error' => 'table_name is required.']);
        }

        $allowed = $this->getAllowedTables();
        if (!in_array($tableName, $allowed)) {
            return json_encode(['error' => "Access denied: table '{$tableName}' is not in your allowed list."]);
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

            return json_encode([
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
            return json_encode(['error' => 'Failed to check data quality: ' . $e->getMessage()]);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRIORITY #2: SMART ANALYSIS CHAIN TOOLS
    // ════════════════════════════════════════════════════════════════════════

    // ── smart_analyze ─────────────────────────────────────────────────────
    private function smartAnalyze(
        string $metric,
        string $period,
        string $breakdownBy = '',
        array $analysisTypes = ['trend', 'anomaly', 'comparison'],
        int $topN = 10
    ): string {
        if (empty($metric) || empty($period)) {
            return json_encode(['error' => 'Both metric and period are required.']);
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
            $schemaInfo = json_decode($this->getSchemaInfo(), true);
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
                $queryResult = json_decode($this->executeQuery($sql, "Smart analysis: {$metric} - {$period}"), true);

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
                            $trendResult = json_decode($this->analyzeTrend($data, $valueCol, $periodCol), true);
                            $results['trend'] = $trendResult;
                            $results['analyses_run'][] = 'trend';
                        }

                        if (in_array('anomaly', $analysisTypes) && $valueCol) {
                            $anomalyResult = json_decode($this->detectAnomalies($data, $valueCol), true);
                            $results['anomalies'] = $anomalyResult;
                            $results['analyses_run'][] = 'anomaly';
                        }

                        if (in_array('comparison', $analysisTypes) && $valueCol && $periodCol) {
                            $periods = collect($data)->pluck($periodCol)->unique()->sort()->values();
                            if ($periods->count() >= 2) {
                                $comparisonResult = json_decode($this->comparePeriods(
                                    $data, $valueCol, $periodCol,
                                    $periods[$periods->count() - 2],
                                    $periods[$periods->count() - 1]
                                ), true);
                                $results['comparison'] = $comparisonResult;
                                $results['analyses_run'][] = 'comparison';
                            }
                        }

                        if (in_array('forecast', $analysisTypes) && $valueCol && $periodCol) {
                            $forecastResult = json_decode($this->forecastMetric($data, $valueCol, $periodCol, 3, true), true);
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

            return json_encode($results);

        } catch (\Throwable $e) {
            return json_encode(['error' => 'Smart analysis failed: ' . $e->getMessage()]);
        }
    }

    // ── explain_query_plan ────────────────────────────────────────────────
    private function explainQueryPlan(string $sql, bool $suggestions = true): string
    {
        if (empty($sql)) {
            return json_encode(['error' => 'sql is required.']);
        }

        // Security: same SELECT-only check as executeQuery
        if (!preg_match('/^\s*SELECT\b/i', trim($sql))) {
            return json_encode(['error' => 'Hanya query SELECT yang diizinkan.']);
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

            return json_encode([
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
            return json_encode(['error' => 'EXPLAIN ANALYZE failed: ' . $e->getMessage()]);
        }
    }

    // ── run_analysis_template ─────────────────────────────────────────────
    private function runAnalysisTemplate(string $template, string $period, array $filters = []): string
    {
        if (empty($template) || empty($period)) {
            return json_encode(['error' => 'Both template and period are required.']);
        }

        $templates = $this->getAnalysisTemplates();

        if (!isset($templates[$template])) {
            return json_encode([
                'error' => "Unknown template: {$template}",
                'available_templates' => array_keys($templates),
            ]);
        }

        $tpl = $templates[$template];

        try {
            // Build query from template
            $sql = $tpl['build_query']($period, $filters);

            if (!$sql) {
                return json_encode(['error' => 'Failed to build query for template: ' . $template]);
            }

            // Execute query
            $queryResult = json_decode($this->executeQuery($sql, $tpl['label']), true);

            if (isset($queryResult['error'])) {
                return json_encode(['error' => 'Template execution failed: ' . $queryResult['error']]);
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
                        $results['analyses']['trend'] = json_decode(
                            $this->analyzeTrend($data, $valueCol, $periodCol), true
                        );
                    }

                    if ($analysis['type'] === 'anomaly') {
                        $results['analyses']['anomalies'] = json_decode(
                            $this->detectAnomalies($data, $valueCol), true
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

            return json_encode($results);

        } catch (\Throwable $e) {
            return json_encode(['error' => 'Template execution failed: ' . $e->getMessage()]);
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
