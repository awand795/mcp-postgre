<?php

namespace App\Services;

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
    private ERPService $erpService;

    public function __construct()
    {
        // Initialize services with dependencies
        $this->queryService = new QueryService();
        $this->schemaService = new SchemaService($this->queryService);
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
                'name'        => 'get_database_schema_info',
                'description' => 'Mendapatkan daftar lengkap database, schema, dan tabel yang diizinkan untuk diakses oleh pengguna saat ini. SELALU panggil tool ini pertama kali sebelum menulis query SQL agar Anda tahu database apa saja yang tersedia.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'describe_table',
                'description' => 'Mendapatkan informasi semua kolom dan tipe datanya untuk tabel tertentu di database dan schema yang spesifik. Gunakan ini saat Anda butuh informasi struktur presisi dari suatu tabel.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'database_code' => [
                            'type'        => 'string',
                            'description' => 'Kode database.',
                        ],
                        'schema_name' => [
                            'type'        => 'string',
                            'description' => 'Nama schema.',
                        ],
                        'table_name' => [
                            'type'        => 'string',
                            'description' => 'Nama tabel (tanpa prefix schema).',
                        ],
                    ],
                    'required' => ['database_code', 'schema_name', 'table_name'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'execute_query',
                'description' => 'Mengeksekusi SQL SELECT query untuk mengambil data dari database tertentu.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'database_code' => [
                            'type'        => 'string',
                            'description' => 'Kode database target di mana query akan dieksekusi.',
                        ],
                        'sql'   => [
                            'type'        => 'string',
                            'description' => 'Query PostgreSQL SELECT yang valid. Gunakan format schema_name.table_name.',
                        ],
                        'label' => [
                            'type'        => 'string',
                            'description' => 'Deskripsi singkat tentang data yang diambil.',
                        ],
                        'currency_columns' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Daftar kolom yang mewakili nilai uang/rupiah untuk format laporan.',
                        ],
                    ],
                    'required' => ['database_code', 'sql', 'label'],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'get_erp_menu_navigation',
                'description' => 'Get ERP menu navigation path for a specific module or sub-menu.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'module' => [
                            'type'        => 'string',
                            'description' => 'Specific module name to get navigation for.',
                            'enum'        => ['', 'Finance', 'Account Payable', 'Account Receivable', 'Inventory', 'Warehouse', 'Report Center', 'Document'],
                        ],
                        'menu_keyword' => [
                            'type'        => 'string',
                            'description' => 'Optional keyword to search for a specific sub-menu.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'get_erp_guidance',
                'description' => 'Cari panduan operasional ERP.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'keyword'  => [
                            'type'        => 'string',
                            'description' => 'Kata kunci pencarian panduan ERP.',
                        ],
                        'category' => [
                            'type'        => 'string',
                            'description' => 'Filter kategori modul.',
                            'enum'        => ['Report Center', 'Document', 'Finance', 'Account Payable', 'Account Receivable', 'Inventory', ''],
                        ],
                        'list_all' => [
                            'type'        => 'boolean',
                            'description' => 'Tampilkan semua panduan.',
                            'default'     => false,
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'type'        => 'function',
                'name'        => 'fetch_erp_guidance_from_web',
                'description' => 'Ambil panduan ERP dari web jika perlu.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'url' => [
                            'type'        => 'string',
                            'description' => 'URL lengkap.',
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
                'get_database_schema_info' => $this->schemaService->getSchemaInfo(),
                'describe_table'        => $this->schemaService->describeTable($arguments['database_code'] ?? '', $arguments['schema_name'] ?? '', $arguments['table_name'] ?? ''),
                'execute_query'         => $this->queryService->executeQuery($arguments['database_code'] ?? '', $arguments['sql'] ?? '', $arguments['label'] ?? '', $arguments['currency_columns'] ?? []),

                // ERP Tools
                'get_erp_menu_navigation' => $this->erpService->getErpMenuNavigation($arguments['module'] ?? '', $arguments['menu_keyword'] ?? ''),
                'get_erp_guidance'      => $this->erpService->getErpGuidance($arguments['keyword'] ?? '', $arguments['category'] ?? '', $arguments['list_all'] ?? false),
                'fetch_erp_guidance_from_web' => $this->erpService->fetchErpGuidanceFromWeb($arguments['url'] ?? ''),

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
