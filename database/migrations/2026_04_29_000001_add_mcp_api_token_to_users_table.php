<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: add_mcp_api_token_to_users_table
 *
 * Menambahkan kolom mcp_api_token ke tabel users untuk autentikasi MCP Server.
 *
 * Token disimpan sebagai hash SHA-256 (bukan plaintext).
 * Plaintext hanya ditampilkan sekali saat generate — tidak pernah disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mcp_api_token', 64)
                ->nullable()
                ->unique()
                ->after('remember_token')
                ->comment('SHA-256 hash dari MCP API token. Plaintext hanya ditampilkan sekali saat generate.');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mcp_api_token');
        });
    }
};
