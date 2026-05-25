<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom erp_user_id ke tabel users.
     * Digunakan untuk traceability SSO: menyimpan ID internal user di sistem ERP.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('erp_user_id')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('erp_user_id');
        });
    }
};
