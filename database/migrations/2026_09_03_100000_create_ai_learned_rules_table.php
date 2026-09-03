<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_learned_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->default('finance')->index();
            $table->string('trigger_keywords', 255)->index();
            $table->text('rule_description');
            $table->text('sql_hint')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('learned_from', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_learned_rules');
    }
};
