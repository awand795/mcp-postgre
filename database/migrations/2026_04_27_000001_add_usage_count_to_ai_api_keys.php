<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_api_keys', function (Blueprint $table) {
            $table->unsignedBigInteger('usage_count')->default(0)->after('limit_reached');
            $table->unsignedBigInteger('token_count')->default(0)->after('usage_count');
        });
    }

    public function down(): void
    {
        Schema::table('ai_api_keys', function (Blueprint $table) {
            $table->dropColumn(['usage_count', 'token_count']);
        });
    }
};
