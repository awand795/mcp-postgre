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
                'type' => 'function',
                'name' => 'get_database_schema_info',
                'description' => 'Mendapatkan daftar lengkap database, schema, dan tabel yang diizinkan untuk diakses oleh pengguna saat ini. SELALU panggil tool ini pertama kali sebelum menulis query SQL agar Anda tahu database apa saja yang tersedia.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'justification' => [
                            'type' => 'string',
                            'description' => 'Alasan mengapa Anda memanggil tool ini (misal: "Memeriksa daftar tabel yang tersedia sebelum menulis query").',
                        ],
                    ],
                    'required' => ['justification'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'describe_table',
                'description' => 'Mendapatkan informasi semua kolom, tipe data, INDEX, dan relasi FOREIGN KEY untuk tabel tertentu. Gunakan ini untuk memahami struktur tabel dan kolom mana yang bisa di-JOIN atau difilter secara cepat.',
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
                            'description' => 'Nama tabel (tanpa prefix schema).',
                        ],
                    ],
                    'required' => ['database_code', 'schema_name', 'table_name'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'get_column_values',
                'description' => 'Mengambil nilai unik (DISTINCT) dari sebuah kolom tabel FISIK (maks 20 nilai). PERINGATAN KERAS: DILARANG MUTLAK menggunakan tool ini pada tabel/view yang namanya mengandung prefix "view_" atau apapun yang merupakan VIEW — tool ini PASTI ERROR pada VIEW karena PostgreSQL tidak support TABLESAMPLE pada VIEW. Sebagai gantinya, gunakan execute_query dengan SELECT DISTINCT kolom FROM schema.tabel LIMIT 20. Gunakan tool ini HANYA untuk tabel fisik kecil (bukan VIEW) saat Anda butuh nilai enum/kategori sebelum query utama.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'database_code' => ['type' => 'string'],
                        'schema_name' => ['type' => 'string'],
                        'table_name' => [
                            'type' => 'string',
                            'description' => 'Nama tabel FISIK saja — DILARANG memasukkan nama yang mengandung "view_" atau yang merupakan VIEW.',
                        ],
                        'column_name' => ['type' => 'string'],
                    ],
                    'required' => ['database_code', 'schema_name', 'table_name', 'column_name'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'get_view_definition',
                'description' => 'Mendapatkan DDL/logika query di balik sebuah View. Gunakan jika tabel yang Anda hadapi adalah VIEW dan Anda perlu tahu dari tabel mana saja kolom-kolomnya berasal.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'database_code' => ['type' => 'string'],
                        'schema_name' => ['type' => 'string'],
                        'view_name' => ['type' => 'string'],
                    ],
                    'required' => ['database_code', 'schema_name', 'view_name'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'search_schema',
                'description' => 'Mencari tabel atau kolom berdasarkan kata kunci. ATURAN PENGGUNAAN KETAT: (1) DILARANG MUTLAK memanggil search_schema jika nama tabel sudah diketahui dari describe_table atau get_database_schema_info — langsung lanjut ke execute_query. (2) Panggil HANYA SEKALI per topik pencarian — jika hasil pertama sudah mengandung tabel yang relevan, STOP dan langsung ke describe_table. (3) DILARANG memanggil search_schema lebih dari 1 kali untuk topik yang sama meskipun dengan sinonim berbeda. (4) Gunakan 1 kata pendek saja sebagai keyword ("jual" bukan "data penjualan cabang"). (5) DILARANG memanggil search_schema sebagai langkah "konfirmasi" atau "verifikasi" nama tabel yang sudah diketahui — ini pemborosan loop yang memperlambat jawaban untuk pengguna. INGAT: Setiap pemanggilan search_schema yang tidak perlu menambah minimal 2–3 detik latensi dan satu agentic loop yang sia-sia.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'SATU kata pendek saja (contoh: "jual", "cabang", "stok"). DILARANG lebih dari satu kata atau frasa panjang.',
                        ],
                    ],
                    'required' => ['keyword'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'get_table_preview',
                'description' => 'Mengambil 5 baris contoh data dari tabel FISIK tertentu. PENTING: JANGAN gunakan tool ini untuk VIEW (nama yang diawali view_) atau tabel dengan lebih dari 100.000 baris karena akan sangat lambat (30-60 detik). Gunakan HANYA untuk tabel fisik berukuran kecil-sedang. Untuk VIEW besar, gunakan execute_query dengan filter WHERE yang spesifik dan LIMIT 5 sebagai gantinya.',
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
                'name' => 'execute_query',
                'description' => 'Mengeksekusi SQL SELECT query untuk mengambil data dari database tertentu. Support multi-database: PostgreSQL (gunakan schema_name.table_name) dan MySQL (cukup table_name atau database_name.table_name).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'database_code' => [
                            'type' => 'string',
                            'description' => 'Nama database target (gunakan nilai dari get_database_schema_info).',
                        ],
                        'sql' => [
                            'type' => 'string',
                            'description' => 'Query SQL SELECT yang valid. Untuk PostgreSQL gunakan format schema_name.table_name (contoh: sch_mbi.view_penjualan). Untuk MySQL cukup table_name atau gunakan database_name.table_name.',
                        ],
                        'label' => [
                            'type' => 'string',
                            'description' => 'Deskripsi singkat tentang data yang diambil.',
                        ],
                        'currency_columns' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'MANDATORY: Identify all columns that represent monetary values (e.g. price, netto, total, amount) so they can be properly formatted with "Rp" in reports and exports. If you don\'t identify them, they will be displayed as raw numbers.',
                        ],
                    ],
                    'required' => ['database_code', 'sql', 'label'],
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

                default => json_encode(['error' => "Unknown tool: {$toolName}"]),
            };
        } catch (\Throwable $e) {
            $this->logToolFailure($toolName, $e);
            return json_encode(['error' => 'Permintaan tidak dapat diproses saat ini. Silakan coba lagi.']);
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