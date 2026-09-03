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
    private \App\Services\Web\WebSearchService $webSearchService;

    public function __construct()
    {
        // Initialize services with dependencies
        $this->queryService = new QueryService();
        $this->schemaService = new SchemaService($this->queryService);
        $this->erpService = new ERPService();
        $this->webSearchService = new \App\Services\Web\WebSearchService();
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
                'type' => 'function',
                'name' => 'execute_query',
                'description' => 'Mengeksekusi SQL SELECT query untuk mengambil data dari database. Gunakan tool ini secara langsung (Single-Shot) untuk menjawab pertanyaan bisnis atau mengambil data master tanpa perlu describe jika tabelnya sudah diketahui dari daftar prompt. Untuk PostgreSQL gunakan format schema_name.table_name (contoh: sch_mbi.view_master_cabang_mbi). DILARANG menggunakan LIMIT kecuali user memintanya secara eksplisit.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'database_code' => [
                            'type' => 'string',
                            'description' => 'Nama database target (contoh: data_mbi).',
                        ],
                        'sql' => [
                            'type' => 'string',
                            'description' => 'Query SQL SELECT yang valid. Untuk PostgreSQL gunakan format schema_name.table_name (contoh: sch_mbi.view_master_cabang_mbi).',
                        ],
                        'label' => [
                            'type' => 'string',
                            'description' => 'Deskripsi singkat tentang data yang diambil.',
                        ],
                        'currency_columns' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Kolom yang mewakili nilai mata uang untuk diformat "Rp".',
                        ],
                    ],
                    'required' => ['database_code', 'sql', 'label'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'describe_table',
                'description' => 'Mendapatkan daftar kolom dan tipe data untuk sebuah tabel/view. Gunakan HANYA jika Anda ragu atau membutuhkan nama kolom spesifik yang belum diketahui sebelum menulis query.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'database_code' => [
                            'type' => 'string',
                            'description' => 'Kode database.',
                        ],
                        'schema_name' => [
                            'type' => 'string',
                            'description' => 'Nama schema.',
                        ],
                        'table_name' => [
                            'type' => 'string',
                            'description' => 'Nama tabel.',
                        ],
                    ],
                    'required' => ['database_code', 'schema_name', 'table_name'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'search_schema',
                'description' => 'Mencari nama tabel atau kolom yang tidak terdaftar di prompt berdasarkan 1 kata kunci (misal: mencari tabel log, audit, atau mutasi tertentu).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'SATU kata kunci pendek.',
                        ],
                    ],
                    'required' => ['keyword'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'get_erp_menu_navigation',
                'description' => 'Get ERP menu navigation path for a specific module or sub-menu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'module' => [
                            'type' => 'string',
                            'description' => 'Specific module name to get navigation for.',
                            'enum' => ['Finance', 'Account Payable', 'Account Receivable', 'Inventory', 'Warehouse', 'Report Center', 'Document'],
                        ],
                        'menu_keyword' => [
                            'type' => 'string',
                            'description' => 'Optional keyword to search for a specific sub-menu.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'get_erp_guidance',
                'description' => 'Cari panduan operasional ERP.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Kata kunci pencarian panduan ERP.',
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Filter kategori modul.',
                            'enum' => ['Report Center', 'Document', 'Finance', 'Account Payable', 'Account Receivable', 'Inventory', 'Warehouse'],
                        ],
                        'list_all' => [
                            'type' => 'boolean',
                            'description' => 'Tampilkan semua panduan.',
                            'default' => false,
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'fetch_erp_guidance_from_web',
                'description' => 'Ambil panduan ERP dari web jika perlu.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => [
                            'type' => 'string',
                            'description' => 'URL lengkap.',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'web_search',
                'description' => 'Mencari informasi eksternal terkini dari internet (Web Search) menggunakan SearXNG. Gunakan tool ini jika user menanyakan informasi umum, berita, artikel, regulasi terbaru (seperti tarif pajak PPN/PPH baru), perkembangan pasar, atau data eksternal lainnya yang tidak ada di dalam database lokal perusahaan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Kata kunci pencarian yang spesifik dan jelas (contoh: "tarif PPN terbaru 2026", "perkembangan industri retail Indonesia").',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'save_learned_rule',
                'description' => 'Menyimpan aturan bisnis, konvensi penamaan kolom, atau formula perhitungan baru ke memori permanen sistem ketika user memberikan instruksi/koreksi bisnis di percakapan (contoh: "Ingat ya kalau DPP itu...", "Koreksi: di sistem kita HPP itu..."). Gunakan tool ini agar sistem mengingat aturan tersebut selamanya.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'Kategori aturan (contoh: finance, tax, sales, product, branch).',
                        ],
                        'trigger_keywords' => [
                            'type' => 'string',
                            'description' => 'Kata kunci pemicu yang dipisahkan koma (contoh: "dpp, pajak, ppn").',
                        ],
                        'rule_description' => [
                            'type' => 'string',
                            'description' => 'Penjelasan aturan bisnis atau cara perhitungan secara jelas.',
                        ],
                        'sql_hint' => [
                            'type' => 'string',
                            'description' => 'Contoh sintaks/formula SQL singkat jika ada (contoh: "ROUND(SUM(total_netto / 1.11), 0)").',
                        ],
                    ],
                    'required' => ['category', 'trigger_keywords', 'rule_description'],
                ],
            ],
        ];
    }

    // ── Dispatch tool call dari AI ────────────────────────────────────────────
    public function execute(string $toolName, array $arguments, bool $isGroq = false): string
    {
        $this->logToolCall($toolName, $arguments);

        try {
            return match ($toolName) {
                // Core Tools
                'get_database_schema_info' => $this->schemaService->getSchemaInfo($isGroq),
                'describe_table' => $this->schemaService->describeTable($arguments['database_code'] ?? '', $arguments['schema_name'] ?? '', $arguments['table_name'] ?? ''),
                'search_schema' => $this->schemaService->searchSchema($arguments['keyword'] ?? ''),
                'get_table_preview' => $this->schemaService->getTablePreview($arguments['database_code'] ?? '', $arguments['schema_name'] ?? '', $arguments['table_name'] ?? ''),
                'get_column_values' => $this->schemaService->getColumnValues($arguments['database_code'] ?? '', $arguments['schema_name'] ?? '', $arguments['table_name'] ?? '', $arguments['column_name'] ?? ''),
                'get_view_definition' => $this->schemaService->getViewDefinition($arguments['database_code'] ?? '', $arguments['schema_name'] ?? '', $arguments['view_name'] ?? ''),
                'execute_query' => $this->queryService->executeQuery(
                    $arguments['database_code'] ?? '',
                    $arguments['sql'] ?? '',
                    $arguments['label'] ?? '',
                    $this->ensureArray($arguments['currency_columns'] ?? [])
                ),

                // ERP Tools
                'get_erp_menu_navigation' => $this->erpService->getErpMenuNavigation($arguments['module'] ?? '', $arguments['menu_keyword'] ?? ''),
                'get_erp_guidance' => $this->erpService->getErpGuidance($arguments['keyword'] ?? '', $arguments['category'] ?? '', $arguments['list_all'] ?? false),
                'fetch_erp_guidance_from_web' => $this->erpService->fetchErpGuidanceFromWeb($arguments['url'] ?? ''),

                // Web Search Tool
                'web_search' => $this->webSearchService->search($arguments['query'] ?? ''),

                // Self-Learning Tool (Cara 2)
                'save_learned_rule' => $this->saveLearnedRule(
                    $arguments['category'] ?? 'finance',
                    $arguments['trigger_keywords'] ?? '',
                    $arguments['rule_description'] ?? '',
                    $arguments['sql_hint'] ?? ''
                ),

                default => json_encode(['error' => "Unknown tool: {$toolName}"]),
            };
        } catch (\Throwable $e) {
            $this->logToolFailure($toolName, $e);
            return json_encode(['error' => __('Permintaan tidak dapat diproses saat ini. Silakan coba lagi.')]);
        }
    }

    /**
     * Ensure the given value is an array.
     * Some AI models mistakenly send JSON strings like "[]" for array parameters.
     */
    private function ensureArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Handle case like "[]" or empty string
            if (trim($value) === '[]' || trim($value) === '') {
                return [];
            }
        }

        return (array) $value;
    }

    /**
     * Menyimpan aturan bisnis baru ke tabel ai_learned_rules (Cara 2: Self-Learning).
     */
    private function saveLearnedRule(string $category, string $keywords, string $description, string $sqlHint = ''): string
    {
        try {
            $rule = \App\Models\AiLearnedRule::create([
                'category' => trim($category) ?: 'finance',
                'trigger_keywords' => trim($keywords),
                'rule_description' => trim($description),
                'sql_hint' => trim($sqlHint) ?: null,
                'is_active' => true,
                'learned_from' => 'user_chat',
            ]);

            return json_encode([
                'status' => 'success',
                'message' => 'Aturan bisnis berhasil disimpan ke memori permanen sistem.',
                'rule_id' => $rule->id,
                'rule' => $rule->rule_description,
                'sql_hint' => $rule->sql_hint,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Gagal menyimpan aturan: ' . $e->getMessage()]);
        }
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