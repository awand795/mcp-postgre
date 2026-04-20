<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('name'); // OpenAI, Google Gemini, Anthropic Claude
            $blueprint->string('code')->unique(); // openai, gemini, claude
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('provider_id')->constrained('ai_providers')->onDelete('cascade');
            $blueprint->string('model_name'); // gpt-4o, gemini-1.5-pro
            $blueprint->string('display_name'); // GPT-4o, Gemini 1.5 Pro
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
        });

        Schema::create('ai_api_keys', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('provider_id')->constrained('ai_providers')->onDelete('cascade');
            $blueprint->string('key_name'); // Key Utama, Key Backup
            $blueprint->text('api_key'); // Will be encrypted
            $blueprint->boolean('is_active')->default(true);
            $blueprint->boolean('limit_reached')->default(false);
            $blueprint->timestamp('last_used_at')->nullable();
            $blueprint->timestamps();
        });

        // User specific access
        Schema::create('user_ai_models', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('model_id')->constrained('ai_models')->onDelete('cascade');
            $blueprint->timestamps();
        });

        Schema::create('user_ai_keys', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('api_key_id')->constrained('ai_api_keys')->onDelete('cascade');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_keys');
        Schema::dropIfExists('user_ai_models');
        Schema::dropIfExists('ai_api_keys');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
