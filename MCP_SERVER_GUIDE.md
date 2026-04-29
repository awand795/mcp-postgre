# MCP Server — Panduan Lengkap

## Arsitektur

```
┌─────────────────────────────────────────────────────────────┐
│                   AI CLIENT (Claude / GPT / dll)            │
│   - Claude Desktop          (via SSE)                       │
│   - Anthropic API           (via mcp_servers param)         │
│   - OpenAI / Groq / Mistral (via tool calling → MCP proxy) │
└───────────────────────┬─────────────────────────────────────┘
                        │ MCP Protocol (HTTP/SSE)
                        │ Authorization: Bearer <token>
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                  MCP SERVER (/mcp/*)                        │
│                                                             │
│   McpAuthMiddleware ──→ Bearer token validation             │
│          │                                                  │
│          ▼                                                  │
│   Auth::setUser($user)  ←── McpTokenGuard::resolveUser()   │
│          │                                                  │
│          ▼                                                  │
│   ┌──────────────────────────────────────────────────┐     │
│   │  TOOLS (app/Mcp/Tools/)                          │     │
│   │  1. GetSchemaInfo      → SchemaService           │     │
│   │  2. DescribeTableTool  → SchemaService           │     │
│   │  3. SearchSchema       → SchemaService           │     │
│   │  4. ExecuteQueryTool   → QueryService (6-layer)  │     │
│   │  5. GetColumnValues    → SchemaService           │     │
│   │  6. GetViewDefinition  → SchemaService           │     │
│   │  7. GetTablePreview    → SchemaService           │     │
│   └──────────────────────────────────────────────────┘     │
│          │                                                  │
│          ▼                                                  │
│   QueryService / SchemaService (RBAC-aware)                 │
│          │                                                  │
│          ▼                                                  │
│   Multi-Database (PostgreSQL, MySQL, MariaDB, SQLite, MSSQL)│
└─────────────────────────────────────────────────────────────┘
```

## Fitur Utama

- **Multi-Database**: PostgreSQL, MySQL, MariaDB, SQL Server, SQLite
- **Multi-Provider AI**: Claude, OpenAI, Groq, Mistral, OpenRouter, dll
- **RBAC**: Setiap user/role hanya bisa akses database & tabel yang diizinkan
- **6-Layer Security**: Strip comment → SELECT-only → forbidden keywords → single statement → RBAC → execute
- **Token-based Auth**: Bearer token per-user, hash SHA-256, cache 5 menit

---

## Setup

### 1. Jalankan Migration
```bash
php artisan migrate
```
Ini menambahkan kolom `mcp_api_token` ke tabel `users`.

### 2. Generate Token untuk User
Via Admin Dashboard:
```
POST /admin/users/{user}/mcp-token/generate
```
Response:
```json
{
  "success": true,
  "mcp_api_token": "abc123def456...",
  "mcp_server_url": "https://your-domain.com/mcp",
  "usage_example": "Authorization: Bearer abc123def456..."
}
```
⚠️ **Token plaintext hanya ditampilkan SEKALI — simpan segera!**

### 3. Clear Config Cache
```bash
php artisan config:clear
php artisan cache:clear
```

---

## Cara Penggunaan

### A. Claude Desktop
Tambahkan ke `claude_desktop_config.json`:
```json
{
  "mcpServers": {
    "erp-database": {
      "type": "sse",
      "url": "https://your-domain.com/mcp/sse",
      "headers": {
        "Authorization": "Bearer <mcp_api_token>"
      }
    }
  }
}
```

### B. Anthropic API (claude-sonnet-4-*)
```python
response = anthropic.messages.create(
    model="claude-sonnet-4-20250514",
    max_tokens=4096,
    messages=[{"role": "user", "content": "Tampilkan total penjualan bulan ini"}],
    mcp_servers=[
        {
            "type": "url",
            "url": "https://your-domain.com/mcp",
            "name": "erp-db",
            "authorization_token": "<mcp_api_token>"  # jika API mendukung
        }
    ]
)
```

### C. Provider Lain (OpenAI, Groq, Mistral) via Chatbot Existing
Provider selain Claude **tidak mendukung mcp_servers parameter** secara native.
Untuk ini, gunakan chatbot web yang sudah ada (`AgenticChatbotController`) 
yang menggunakan tool calling manual — ini sudah berjalan dengan baik.

---

## Tools yang Tersedia

| Tool | Deskripsi | Kapan Dipanggil |
|------|-----------|-----------------|
| `get_schema_info` | Daftar DB/schema/tabel yang diizinkan | **Pertama kali, selalu** |
| `describe_table` | Kolom, tipe, index, FK | Sebelum query |
| `search_schema` | Cari tabel/kolom by keyword | Jika nama tabel tidak diketahui |
| `execute_query` | Jalankan SQL SELECT | Query data |
| `get_column_values` | Nilai DISTINCT dari kolom | Tabel fisik, filter enum |
| `get_view_definition` | DDL dari VIEW | Jika perlu tahu asal kolom VIEW |
| `get_table_preview` | 5 baris sampel | Tabel fisik saja |

---

## RBAC

MCP Server menggunakan RBAC yang **sama persis** dengan chatbot web:
- Admin → akses semua database & tabel
- Role biasa → hanya database & tabel yang didaftarkan di permission role
- Token satu user = permission user tersebut

---

## Revoke Token
```
DELETE /admin/users/{user}/mcp-token
```

---

## Troubleshooting

| Error | Penyebab | Solusi |
|-------|----------|--------|
| 401 Unauthorized | Token tidak valid / user tidak aktif | Generate token baru |
| "Database not found" | `database_code` salah | Panggil `get_schema_info` dulu |
| "Access denied" | User tidak punya permission ke tabel | Cek role permission di admin |
| "Only SELECT allowed" | AI mengirim non-SELECT | Bug di AI prompt — laporkan |
