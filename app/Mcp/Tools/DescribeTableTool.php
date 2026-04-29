<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesRbac;
use App\Services\Core\SchemaService;
use App\Services\Core\QueryService;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Tool: describe_table
 *
 * Mengembalikan kolom, tipe data, index, dan foreign key
 * untuk tabel tertentu di database tertentu.
 */
class DescribeTableTool
{
    use ResolvesRbac;

    #[McpTool(
        name: 'describe_table',
        description: 'Mendapatkan informasi kolom, tipe data, INDEX, dan FOREIGN KEY untuk sebuah tabel. Panggil ini sebelum execute_query untuk mengetahui nama kolom yang tepat.'
    )]
    public function handle(
        string $database_code,
        string $schema_name,
        string $table_name
    ): array {
        $qs      = new QueryService();
        $service = new SchemaService($qs);

        $result = $service->describeTable($database_code, $schema_name, $table_name);

        return json_decode($result, true) ?? ['error' => 'Failed to parse result'];
    }
}
