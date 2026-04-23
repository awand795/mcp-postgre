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
            // Mengubah ke TEXT agar bisa menampung password yang di-encrypt (sangat panjang)
            $table->text('password')->change();
            // Host cloud provider (seperti Aiven) bisa sangat panjang
            $table->text('host')->nullable()->change();
            // Nama koneksi juga bisa panjang
            $table->text('name')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            $table->string('password')->change();
            $table->string('host')->change();
            $table->string('name')->change();
        });
    }
};
