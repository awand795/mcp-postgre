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
        Schema::table('chat_messages', function (Blueprint $table) {
            // Composite index for session message retrieval with ordering
            $table->index(['chat_session_id', 'created_at'], 'idx_chat_messages_session_created');
            
            // Index for role-based filtering (user/assistant messages)
            $table->index('role', 'idx_chat_messages_role');
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            // Index for user session listing with ordering
            $table->index(['user_id', 'created_at'], 'idx_chat_sessions_user_created');
        });

        Schema::table('users', function (Blueprint $table) {
            // Index for RBAC role lookups
            $table->index('role', 'idx_users_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('idx_chat_messages_session_created');
            $table->dropIndex('idx_chat_messages_role');
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_chat_sessions_user_created');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role');
        });
    }
};
