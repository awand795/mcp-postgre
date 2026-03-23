<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "--- DATABASE DIAGNOSTICS ---\n";
    echo "User: " . config('database.connections.pgsql.username') . "\n";
    echo "Database: " . config('database.connections.pgsql.database') . "\n";
    echo "Configured search_path: " . config('database.connections.pgsql.search_path') . "\n";
    
    $searchPath = DB::select("SHOW search_path");
    echo "Effective search_path in session: " . $searchPath[0]->search_path . "\n";

    $schemas = DB::select("SELECT nspname, pg_catalog.pg_get_userbyid(nspowner) as owner FROM pg_namespace WHERE nspname NOT LIKE 'pg_%' AND nspname != 'information_schema'");
    echo "\nAvailable Schemas and Owners:\n";
    foreach ($schemas as $s) {
        echo "- {$s->nspname} (Owner: {$s->owner})\n";
    }

    $perms = DB::select("SELECT has_schema_privilege(current_user, 'sch_ai', 'CREATE') as can_create_in_sch_ai,
                               has_schema_privilege(current_user, 'sch_ai', 'USAGE') as can_use_sch_ai,
                               has_schema_privilege(current_user, 'public', 'CREATE') as can_create_in_public,
                               has_schema_privilege(current_user, 'public', 'USAGE') as can_use_public");
    echo "\nPermissions for " . DB::select("SELECT current_user")[0]->current_user . ":\n";
    echo "- Create in sch_ai: " . ($perms[0]->can_create_in_sch_ai ? 'YES' : 'NO') . "\n";
    echo "- Use sch_ai: " . ($perms[0]->can_use_sch_ai ? 'YES' : 'NO') . "\n";
    echo "- Create in public: " . ($perms[0]->can_create_in_public ? 'YES' : 'NO') . "\n";
    echo "- Use public: " . ($perms[0]->can_use_public ? 'YES' : 'NO') . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
