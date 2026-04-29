<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MCP Client Mode
    |--------------------------------------------------------------------------
    |
    | Menentukan bagaimana chatbot web memanggil MCP tools:
    |
    |  "direct" (default) — tool dipanggil in-process tanpa HTTP.
    |                        Paling cepat, tidak butuh MCP server berjalan terpisah.
    |                        RBAC (allowed_databases) otomatis diteruskan.
    |
    |  "http"             — tool dipanggil via HTTP ke MCP server lokal (JSON-RPC 2.0).
    |                        Berguna jika MCP server berjalan di proses terpisah
    |                        atau di host lain. Butuh MCP_SERVER_INTERNAL_URL &
    |                        MCP_SERVER_INTERNAL_TOKEN di .env.
    |
    */
    'mode' => env('MCP_CLIENT_MODE', 'direct'),

    /*
    |--------------------------------------------------------------------------
    | MCP Server Internal URL (hanya untuk mode=http)
    |--------------------------------------------------------------------------
    |
    | URL endpoint MCP server yang akan dipanggil chatbot web.
    | Biasanya http://127.0.0.1:8000/mcp (server ini sendiri).
    |
    */
    'server_url' => env('MCP_SERVER_INTERNAL_URL', env('APP_URL', 'http://localhost') . '/mcp'),

    /*
    |--------------------------------------------------------------------------
    | MCP Server Internal Token (hanya untuk mode=http)
    |--------------------------------------------------------------------------
    |
    | Bearer token yang dikirimkan chatbot ke MCP server saat mode=http.
    | Harus sama dengan token admin di MCP_TOKENS di .env.
    |
    */
    'internal_token' => env('MCP_SERVER_INTERNAL_TOKEN', ''),

];
