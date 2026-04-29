<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesRbac;
use App\Services\Core\SchemaService;
use App\Services\Core\QueryService;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Tool: search_schema
 *
 * Mencari tabel atau kolom berdasarkan kata kunci
 * di seluruh database yang diizinkan.
 */
class SearchSchema
{
    use ResolvesRbac;

    #[McpTool(
        name: 'search_schema',
        description: 'Mencari tabel atau kolom berdasarkan kata kunci di semua database yang diizinkan. Gunakan 1 kata pendek saja sebagai keyword.'
    )]
    public function handle(string $keyword): array
    {
        $qs      = new QueryService();
        $service = new SchemaService($qs);

        $result = $service->searchSchema($keyword);

        return json_decode($result, true) ?? ['error' => 'Failed to parse result'];
    }
}
