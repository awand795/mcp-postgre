<?php

namespace App\Http\Controllers;

use App\Mcp\Tools\DescribeTable;
use App\Mcp\Tools\ExecuteQuery;
use App\Mcp\Tools\ListTables;
use Laravel\Mcp\Server;

class MCPServer extends Server
{
    protected string $name = 'PostgreSQL MCP Server';
    protected string $version = '1.0.0';

    protected array $tools = [
        ListTables::class,
        DescribeTable::class,
        ExecuteQuery::class,
    ];
}
