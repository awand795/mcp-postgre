<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            // Base URL untuk custom/third-party providers (Groq, OpenRouter, DeepSeek, dll)
            // Null = pakai URL hardcode di controller (openai, gemini, claude, mistral)
            $table->string('base_url')->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn('base_url');
        });
    }
};
