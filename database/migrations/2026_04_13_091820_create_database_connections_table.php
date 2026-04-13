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
        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Display name, e.g., "MBI Production"
            $table->string('code')->unique(); // Unique code for config, e.g., "mbi_prod"
            $table->string('driver')->default('pgsql');
            $table->string('host');
            $table->integer('port')->default(5432);
            $table->string('database');
            $table->string('username');
            $table->string('password');
            $table->string('schema')->default('public');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('test_status')->nullable(); // success, failed, null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_connections');
    }
};
