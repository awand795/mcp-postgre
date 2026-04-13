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
        Schema::table('database_connections', function (Blueprint $table) {
            $table->string('ssl_mode')->nullable()->after('schema');
            $table->integer('connection_timeout')->default(30)->after('ssl_mode');
            $table->text('options')->nullable()->after('connection_timeout');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            $table->dropColumn(['ssl_mode', 'connection_timeout', 'options']);
        });
    }
};
