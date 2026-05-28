<?php

namespace App\Services\Mcp;

use App\Services\Core\QueryService;
use App\Services\Core\SchemaService;
use App\Services\ERP\ERPService;
use App\Services\ToolCallExecutor;
use Illuminate\Support\Facades\Log;

/**
 * McpDirectDispatcher
 *
 * Dipanggil oleh McpClientService saat mode=direct.
 * Mendelegasikan tool call langsung ke service yang sama dengan ToolCallExecutor,
 * tapi dengan dukungan RBAC (allowed_databases) yang diteruskan dari chatbot.
 *
 * Ini adalah "in-process MCP client" — tidak ada HTTP, tidak ada serialisasi jaringan.
 * Hasilnya setara dengan MCP tool call tapi dengan latensi ~0ms overhead.
 */
class McpDirectDispatcher
{
    private QueryService $queryService;
    private SchemaService $schemaService;
    private ERPService $erpService;
    private \App\Services\Web\WebSearchService $webSearchService;

    public function __construct(array $allowedDbs = [])
    {
        $this->queryService  = new QueryService();
        $this->schemaService = new SchemaService($this->queryService);
        $this->erpService    = new ERPService();
        $this->webSearchService = new \App\Services\Web\WebSearchService();

        if (!empty($allowedDbs)) {
            $this->queryService->setAllowedTables($allowedDbs);
        }
    }

    /**
     * Update allowed databases (RBAC) setelah konstruksi.
     */
    public function setAllowedDbs(array $allowedDbs): void
    {
        $this->queryService->setAllowedTables($allowedDbs);
    }

    /**
     * Dispatch satu tool call dan kembalikan hasilnya sebagai string (JSON atau teks).
     *
     * @throws \InvalidArgumentException jika tool tidak dikenal
     */
    public function dispatch(string $toolName, array $arguments): string
    {
        Log::info("[McpDirectDispatcher] Dispatching tool: {$toolName}");

        return match ($toolName) {
            // ── Schema & Structure Tools ──────────────────────────────────────
            'get_database_schema_info' => $this->schemaService->getSchemaInfo(false),

            'describe_table' => $this->schemaService->describeTable(
                $arguments['database_code'] ?? '',
                $arguments['schema_name']   ?? '',
                $arguments['table_name']    ?? ''
            ),

            'search_schema' => $this->schemaService->searchSchema(
                $arguments['keyword'] ?? ''
            ),

            'get_table_preview' => $this->schemaService->getTablePreview(
                $arguments['database_code'] ?? '',
                $arguments['schema_name']   ?? '',
                $arguments['table_name']    ?? ''
            ),

            'get_column_values' => $this->schemaService->getColumnValues(
                $arguments['database_code'] ?? '',
                $arguments['schema_name']   ?? '',
                $arguments['table_name']    ?? '',
                $arguments['column_name']   ?? ''
            ),

            'get_view_definition' => $this->schemaService->getViewDefinition(
                $arguments['database_code'] ?? '',
                $arguments['schema_name']   ?? '',
                $arguments['view_name']     ?? ''
            ),

            // ── Query Execution ───────────────────────────────────────────────
            'execute_query' => $this->queryService->executeQuery(
                $arguments['database_code']   ?? '',
                $arguments['sql']             ?? '',
                $arguments['label']           ?? '',
                $this->ensureArray($arguments['currency_columns'] ?? [])
            ),

            // ── ERP Tools ─────────────────────────────────────────────────────
            'get_erp_menu_navigation' => $this->erpService->getErpMenuNavigation(
                $arguments['module']       ?? '',
                $arguments['menu_keyword'] ?? ''
            ),

            'get_erp_guidance' => $this->erpService->getErpGuidance(
                $arguments['keyword']  ?? '',
                $arguments['category'] ?? '',
                $arguments['list_all'] ?? false
            ),

            'fetch_erp_guidance_from_web' => $this->erpService->fetchErpGuidanceFromWeb(
                $arguments['url'] ?? ''
            ),

            'web_search' => $this->webSearchService->search(
                $arguments['query'] ?? ''
            ),

            default => throw new \InvalidArgumentException("Unknown MCP tool: {$toolName}"),
        };
    }

    /**
     * Kembalikan definisi tool yang sama dengan ToolCallExecutor.
     * Digunakan oleh McpClientService::listToolsDirect().
     */
    public static function getToolDefinitions(): array
    {
        return ToolCallExecutor::getToolDefinitions();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensureArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
