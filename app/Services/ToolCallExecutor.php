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
 * Tools:
 *   - list_tables     : Daftar tabel yang boleh diakses user
 *   - describe_table  : Struktur kolom sebuah tabel
 *   - execute_query   : Eksekusi SELECT query ke PostgreSQL
 *   - get_schema_info : Ringkasan semua tabel + kolom sekaligus
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
                            'description' => 'The period to use as a baseline (e.g., "2024-01").',
                        ],
                        'compare_period' => [
                            'type'        => 'string',
                            'description' => 'The period to compare against the baseline (e.g., "2024-02").',
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
                        'base_period'      => ['type' => 'string', 'description' => 'The baseline period (e.g. "2025-01").'],
                        'compare_period'   => ['type' => 'string', 'description' => 'The comparison period (e.g. "2025-02").'],
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
                        'data_summary'  => ['type' => 'string', 'description' => 'Brief description of data retrieved (e.g. "Monthly sales 2025, 12 periods, 91 branches").'],
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
                ? 'Query memakan waktu terlalu lama. Coba persempit data dengan menambahkan filter tahun, bulan, atau wilayah (misal: WHERE periode_tahun = \'2025\').'
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
                // Tier 1: Title match (Strongest signal)
                if (strpos($gTitle, $keywordLower) !== false) {
                    $score += 500; // Contains
                    if (strpos($gTitle, $keywordLower) === 0) $score += 300; // Starts with
                    if ($gTitle === $keywordLower) $score += 1000; // Exact match
                }

                // Tier 2: Keyword match
                foreach ($gKeys as $key) {
                    if (strpos($key, $keywordLower) !== false) {
                        $score += 100;
                        if ($key === $keywordLower) $score += 300;
                        break;
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
}
