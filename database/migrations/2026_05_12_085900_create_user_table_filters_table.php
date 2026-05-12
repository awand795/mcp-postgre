<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_table_filters', function (Blueprint $userTableFilter) {
            $userTableFilter->id();
            $userTableFilter->foreignId('user_id')->constrained()->onDelete('cascade');
            $userTableFilter->foreignId('database_connection_id')->constrained('database_connections')->onDelete('cascade');
            $userTableFilter->string('table_name');
            $userTableFilter->text('filter_condition')->nullable()->comment('Custom SQL WHERE clause, e.g. kode_cabang = "B282"');
            $userTableFilter->timestamps();

            $userTableFilter->index(['user_id', 'database_connection_id', 'table_name'], 'user_table_db_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_table_filters');
    }
};
