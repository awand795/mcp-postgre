<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Roles
        $role1 = Role::updateOrCreate(['id' => 1], ['name' => 'Data Entry', 'description' => 'Akses transaksi & pembeli']);
        $role2 = Role::updateOrCreate(['id' => 2], ['name' => 'Produk Analyst', 'description' => 'Akses transaksi & produk']);
        $role3 = Role::updateOrCreate(['id' => 3], ['name' => 'Super Admin', 'description' => 'Akses seluruh tabel']);

        // 2. Create Default Permissions
        $defaultPermissions = [
            1 => ['transaksi', 'pembeli'],
            2 => ['transaksi', 'produk'],
            3 => cache()->remember('all_db_tables', 3600, function () {
                $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
                return array_column($tables, 'table_name');
            })
        ];

        foreach ($defaultPermissions as $roleId => $tables) {
            foreach ($tables as $table) {
                RolePermission::updateOrCreate(
                    ['role_id' => $roleId, 'table_name' => $table]
                );
            }
        }

        // 3. Create/Update Users
        // Role 1 User (Access: transaksi, pembeli)
        User::updateOrCreate(
            ['email' => 'role1@example.com'],
            [
                'name' => 'User Role 1',
                'password' => Hash::make('password'),
                'role' => 1,
                'is_admin' => false,
            ]
        );

        // Role 2 User (Access: transaksi, produk)
        User::updateOrCreate(
            ['email' => 'role2@example.com'],
            [
                'name' => 'User Role 2',
                'password' => Hash::make('password'),
                'role' => 2,
                'is_admin' => false,
            ]
        );

        // Role 3 User (Super Admin)
        User::updateOrCreate(
            ['email' => 'role3@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 3,
                'is_admin' => true,
            ]
        );
    }
}
