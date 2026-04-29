<?php

namespace App\Http\Controllers;

use App\Mcp\Tools\GetSchemaInfo;
use App\Mcp\Tools\DescribeTableTool;
use App\Mcp\Tools\SearchSchema;
use App\Mcp\Tools\ExecuteQueryTool;
use App\Mcp\Tools\GetColumnValues;
use App\Mcp\Tools\GetViewDefinition;
use App\Mcp\Tools\GetTablePreview;
use PhpMcp\Laravel\Server;

/**
 * MCPServer
 *
 * Entry point untuk MCP Server project ini.
 *
 * Fitur:
 *   - Multi-database (PostgreSQL, MySQL, MariaDB, SQL Server, SQLite)
 *   - Multi-provider AI (Claude, OpenAI, Groq, Mistral, OpenRouter, dll)
 *   - RBAC per-user / per-role (tabel & schema yang boleh diakses)
 *   - Autentikasi via Bearer token (mcp_api_token)
 *   - Semua tools mendelegasikan logika ke Service layer yang sudah ada
 *     (QueryService, SchemaService) — zero duplicate code
 *
 * Tools yang tersedia:
 *   1. get_schema_info       — Daftar semua DB/schema/tabel yang boleh diakses
 *   2. describe_table        — Kolom, tipe data, index, FK untuk satu tabel
 *   3. search_schema         — Cari tabel/kolom berdasarkan keyword
 *   4. execute_query         — Jalankan SQL SELECT (6-layer security)
 *   5. get_column_values     — Nilai DISTINCT dari kolom tabel fisik
 *   6. get_view_definition   — DDL/query di balik sebuah VIEW
 *   7. get_table_preview     — 5 baris sampel dari tabel fisik
 *
 * Autentikasi:
 *   Header: Authorization: Bearer <mcp_api_token>
 *
 * Cara generate token (via Admin Dashboard → Users → Generate MCP Token).
 *
 * Untuk Claude Desktop, tambahkan di claude_desktop_config.json:
 * {
 *   "mcpServers": {
 *     "my-erp-db": {
 *       "type": "sse",
 *       "url": "https://your-domain.com/mcp/sse",
 *       "headers": { "Authorization": "Bearer <token>" }
 *     }
 *   }
 * }
 *
 * Untuk Anthropic API (claude-sonnet-4-*), tambahkan di API payload:
 * "mcp_servers": [{ "type": "url", "url": "https://your-domain.com/mcp", "name": "erp-db" }]
 * (gunakan header Authorization via Custom HTTP Headers jika API mendukung)
 */
class MCPServer extends Server
{
    protected string $name    = 'ERP Database MCP Server';
    protected string $version = '2.0.0';

    /**
     * Semua tools yang di-expose ke AI client melalui MCP protocol.
     * Urutan mencerminkan urutan natural pemanggilan oleh AI.
     */
    protected array $tools = [
        GetSchemaInfo::class,       // 1. Selalu panggil ini dulu
        DescribeTableTool::class,   // 2. Detail kolom sebelum query
        SearchSchema::class,        // 3. Jika tabel tidak diketahui
        ExecuteQueryTool::class,    // 4. Jalankan SELECT query
        GetColumnValues::class,     // 5. Nilai DISTINCT untuk filter
        GetViewDefinition::class,   // 6. DDL dari sebuah VIEW
        GetTablePreview::class,     // 7. 5 baris sampel tabel fisik
    ];
}
