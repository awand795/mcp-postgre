<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            // Add database and schema columns
            $table->string('database_code', 50)->nullable()->after('role_id');
            $table->string('schema_name', 100)->nullable()->after('database_code');
        });

        // Migrate existing data: set default values from config
        $defaultDbCode = config('database.default', 'pgsql_mbi');
        $defaultSchema = config("database.connections.{$defaultDbCode}.search_path.0", 'public');

        DB::table('role_permissions')
            ->whereNull('database_code')
            ->update([
                'database_code' => $defaultDbCode,
                'schema_name' => $defaultSchema,
            ]);

        // Update unique constraint to include database_code and schema_name
        Schema::table('role_permissions', function (Blueprint $table) {
            // Drop old unique constraint
            try {
                $table->dropUnique(['role_id', 'table_name']);
            } catch (\Exception $e) {
                // Constraint may not exist
            }
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            // Add new composite unique constraint
            $table->unique(['role_id', 'database_code', 'schema_name', 'table_name'], 'role_db_schema_table_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropUnique('role_db_schema_table_unique');
            $table->unique(['role_id', 'table_name']);
            $table->dropColumn(['database_code', 'schema_name']);
        });
    }
};
