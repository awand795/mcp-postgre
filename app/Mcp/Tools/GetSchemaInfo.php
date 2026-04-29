<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesRbac;
use App\Services\Core\SchemaService;
use App\Services\Core\QueryService;
use PhpMcp\Server\Attributes\McpTool;

/**
 * Tool: get_schema_info
 *
 * Mengembalikan daftar lengkap database, schema, dan tabel
 * yang boleh diakses oleh user saat ini (RBAC-aware).
 *
 * Ini adalah tool PERTAMA yang wajib dipanggil AI sebelum menulis query apapun.
 */
class GetSchemaInfo
{
    use ResolvesRbac;

    #[McpTool(
        name: 'get_schema_info',
        description: 'Mendapatkan daftar lengkap database, schema, dan tabel yang diizinkan untuk user ini. WAJIB dipanggil pertama sebelum menulis query SQL.'
    )]
    public function handle(string $justification = ''): array
    {
        $allowedDbs = $this->resolveAllowedDatabases();

        $overview = [];
        foreach ($allowedDbs as $dbCode => $schemas) {
            $overview[$dbCode] = [];
            foreach ($schemas as $schema => $tables) {
                $tableNames = array_map(function ($t) {
                    return is_array($t) ? ($t['name'] ?? '') : (string) $t;
                }, $tables);
                $overview[$dbCode][$schema] = array_filter($tableNames);
            }
        }

        return [
            'total_databases' => count($overview),
            'databases'       => $overview,
            'instruction'     => 'Gunakan database_code dan schema_name yang EKSAK saat memanggil tool lain. Jangan gunakan "*" sebagai schema_name.',
        ];
    }
}
