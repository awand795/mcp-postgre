<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\RolePermission;
use Illuminate\Support\Facades\Log;

$userId = 7;
$user = User::find($userId);

if (!$user) {
    echo "User ID $userId not found.\n";
    exit;
}

echo "User: {$user->name} (Email: {$user->email})\n";
echo "Role ID: {$user->role}\n";

$role = $user->roleModel;
if (!$role) {
    echo "Role not found for user.\n";
    exit;
}

echo "Role Name: {$role->name}\n";

$permissions = RolePermission::with('databaseConnection')->where('role_id', $user->role)->get();

echo "\nPermissions:\n";
foreach ($permissions as $p) {
    $db = $p->databaseConnection->database ?? 'N/A';
    echo "- DB: $db, Schema: {$p->schema_name}, Table: {$p->table_name}\n";
}

$allowedDatabases = [];
foreach ($permissions as $perm) {
    $conn = $perm->databaseConnection;
    if (!$conn || !$conn->is_active) continue;

    $db = $conn->database;
    $schema = $perm->schema_name;
    $tbl = $perm->table_name;

    if (!isset($allowedDatabases[$db])) $allowedDatabases[$db] = [];
    $schemaKey = ($schema && $schema !== '*') ? $schema : '*';

    if (!isset($allowedDatabases[$db][$schemaKey])) $allowedDatabases[$db][$schemaKey] = [];

    if ($tbl && $tbl !== '*') {
        $allowedDatabases[$db][$schemaKey][] = $tbl;
    } else {
        $allowedDatabases[$db][$schemaKey][] = '*';
    }
}

echo "\nFinal allowedDatabases structure:\n";
print_r($allowedDatabases);
