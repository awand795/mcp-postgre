<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_admin');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete()->after('id');
        });

        Schema::table('database_connections', function (Blueprint $table) {
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete()->after('id');
        });

        Schema::table('ai_api_keys', function (Blueprint $table) {
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete()->after('id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropColumn('added_by');
        });

        Schema::table('ai_api_keys', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropColumn('added_by');
        });

        Schema::table('database_connections', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropColumn('added_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropColumn('added_by');
            $table->dropColumn('is_super_admin');
        });
    }
};
