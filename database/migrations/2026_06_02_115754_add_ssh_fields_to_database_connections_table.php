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
            $table->boolean('use_ssh')->default(false)->after('description');
            $table->string('ssh_host')->nullable()->after('use_ssh');
            $table->integer('ssh_port')->default(22)->after('ssh_host');
            $table->string('ssh_username')->nullable()->after('ssh_port');
            $table->string('ssh_auth_type')->default('password')->after('ssh_username');
            $table->text('ssh_password')->nullable()->after('ssh_auth_type');
            $table->text('ssh_private_key')->nullable()->after('ssh_password');
            $table->integer('ssh_pid')->nullable()->after('ssh_private_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            $table->dropColumn([
                'use_ssh',
                'ssh_host',
                'ssh_port',
                'ssh_username',
                'ssh_auth_type',
                'ssh_password',
                'ssh_private_key',
                'ssh_pid'
            ]);
        });
    }
};
