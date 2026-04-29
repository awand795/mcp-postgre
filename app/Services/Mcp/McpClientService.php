<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * McpClientService
 *
 * Chatbot web bertindak sebagai MCP CLIENT yang berkomunikasi
 * dengan MCP Server lokal (project ini sendiri) via HTTP+JSON-RPC
 * atau secara in-process (direct mode).
 *
 * Konfigurasi di .env:
 *   MCP_CLIENT_MODE=direct     → panggil tool in-process (default, paling cepat)
 *   MCP_CLIENT_MODE=http       → panggil via HTTP ke MCP server
 *   MCP_SERVER_INTERNAL_URL=http://127.0.0.1:8000/mcp
 *   MCP_SERVER_INTERNAL_TOKEN=<token admin>
 *
 * CARA PAKAI dari controller:
 *   1. $this->mcpClient->setAllowedDbs($allowedDatabases);   ← set RBAC sekali
 *   2. $tools = $this->mcpClient->listTools();               ← ambil definisi tools
 *   3. $result = $this->mcpClient->callTool($name, $args);   ← panggil tool (pakai RBAC yg sudah diset)
 */
class McpClientService
{
    private string $mode;
    private string $serverUrl;
    private string $token;

    /** In-process tool dispatcher (untuk mode=direct) */
    private ?McpDirectDispatcher $directDispatcher = null;

    /** RBAC yang sudah diset via setAllowedDbs(), dipakai di mode=direct */
    private array $allowedDbs = [];

    public function __construct()
    {
        $this->mode      = config('mcp_client.mode', 'direct');
        $this->serverUrl = rtrim(config('mcp_client.server_url', url('/mcp')), '/');
        $this->token     = config('mcp_client.internal_token', '');
    }

    /**
     * Set allowed databases (RBAC) — WAJIB dipanggil dari controller
     * sebelum memanggil callTool(), agar tool execution tahu database mana
     * yang boleh diakses user yang sedang login.
     *
     * Hanya efektif untuk mode=direct. Untuk mode=http, RBAC dihandle di server.
     */
    public function setAllowedDbs(array $allowedDbs): void
    {
        $this->allowedDbs = $allowedDbs;

        if ($this->mode === 'direct') {
            // Update dispatcher yang sudah ada, atau buat baru jika belum ada
            if ($this->directDispatcher === null) {
                $this->directDispatcher = new McpDirectDispatcher($allowedDbs);
            } else {
                $this->directDispatcher->setAllowedDbs($allowedDbs);
            }
        }
    }

    /**
     * Panggil satu MCP tool dan kembalikan hasilnya sebagai string JSON.
     * RBAC diambil dari allowedDbs yang sudah diset via setAllowedDbs().
     *
     * @param  string $toolName   Nama tool (e.g. "execute_query")
     * @param  array  $arguments  Argumen tool
     * @return string             Hasil tool dalam format JSON string
     */
    public function callTool(string $toolName, array $arguments): string
    {
        if ($this->mode === 'http') {
            return $this->callToolViaHttp($toolName, $arguments);
        }

        return $this->callToolDirect($toolName, $arguments);
    }

    /**
     * Ambil daftar tools yang tersedia dari MCP server.
     * Mode direct: langsung dari McpDirectDispatcher (sama dengan ToolCallExecutor).
     * Mode http: fetch dari MCP server, di-cache 10 menit.
     */
    public function listTools(): array
    {
        if ($this->mode === 'http') {
            return $this->listToolsViaHttp();
        }

        return $this->listToolsDirect();
    }

    // ── MODE DIRECT (in-process, paling efisien) ─────────────────────────────

    /**
     * Panggil tool secara langsung in-process tanpa HTTP round-trip.
     * RBAC (allowed_databases) sudah diset sebelumnya via setAllowedDbs().
     */
    private function callToolDirect(string $toolName, array $arguments): string
    {
        // Pastikan dispatcher sudah ada (seharusnya sudah diinisialisasi via setAllowedDbs)
        if ($this->directDispatcher === null) {
            Log::warning("[McpClient] callToolDirect dipanggil sebelum setAllowedDbs(). RBAC akan kosong.");
            $this->directDispatcher = new McpDirectDispatcher($this->allowedDbs);
        }

        try {
            $result = $this->directDispatcher->dispatch($toolName, $arguments);
            return is_string($result) ? $result : json_encode($result);
        } catch (\InvalidArgumentException $e) {
            Log::error("[McpClient] Unknown tool '{$toolName}': " . $e->getMessage());
            return json_encode(['error' => "Tool tidak dikenal: {$toolName}"]);
        } catch (\Throwable $e) {
            Log::error("[McpClient] Direct dispatch failed for tool '{$toolName}': " . $e->getMessage());
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Daftar tools dari dispatcher langsung.
     * Format identik dengan ToolCallExecutor::getToolDefinitions().
     */
    private function listToolsDirect(): array
    {
        return McpDirectDispatcher::getToolDefinitions();
    }

    // ── MODE HTTP (via MCP Server endpoint) ───────────────────────────────────

    /**
     * Panggil tool via HTTP ke MCP Server menggunakan JSON-RPC 2.0.
     */
    private function callToolViaHttp(string $toolName, array $arguments): string
    {
        $requestId = uniqid('mcp_', true);

        $payload = [
            'jsonrpc' => '2.0',
            'id'      => $requestId,
            'method'  => 'tools/call',
            'params'  => [
                'name'      => $toolName,
                'arguments' => $arguments,
            ],
        ];

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ])
                ->post($this->serverUrl, $payload);

            if (!$response->successful()) {
                Log::error("[McpClient] HTTP tool call failed. Status: " . $response->status() . " Tool: {$toolName}");
                return json_encode(['error' => 'MCP server returned HTTP ' . $response->status()]);
            }

            $data = $response->json();

            // JSON-RPC response format: { "result": { "content": [...] } }
            $content = $data['result']['content'] ?? null;

            if (is_array($content)) {
                // MCP content blocks: [{"type":"text","text":"..."}]
                $texts = array_filter(array_map(fn($c) => $c['text'] ?? null, $content));
                return implode("\n", $texts);
            }

            if (isset($data['error'])) {
                return json_encode(['error' => $data['error']['message'] ?? 'MCP error']);
            }

            return json_encode($data['result'] ?? []);

        } catch (\Throwable $e) {
            Log::error("[McpClient] HTTP call exception for tool '{$toolName}': " . $e->getMessage());
            return json_encode(['error' => 'MCP client exception: ' . $e->getMessage()]);
        }
    }

    /**
     * Ambil daftar tools via HTTP dari MCP Server.
     * Di-cache 10 menit karena sangat jarang berubah.
     */
    private function listToolsViaHttp(): array
    {
        $cacheKey = 'mcp_tools_list_' . md5($this->serverUrl);

        return Cache::remember($cacheKey, 600, function () {
            try {
                $payload = [
                    'jsonrpc' => '2.0',
                    'id'      => 'list_tools',
                    'method'  => 'tools/list',
                    'params'  => [],
                ];

                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ])
                    ->post($this->serverUrl, $payload);

                $data  = $response->json();
                $tools = $data['result']['tools'] ?? [];

                Log::info("[McpClient] Fetched " . count($tools) . " tools from MCP server.");
                return $tools;

            } catch (\Throwable $e) {
                Log::error("[McpClient] listToolsViaHttp failed: " . $e->getMessage());
                return [];
            }
        });
    }
}
