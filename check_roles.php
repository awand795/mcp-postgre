<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$roles = DB::table('roles')->get();
echo "Roles in database:\n";
foreach ($roles as $role) {
    echo "ID: {$role->id}, Name: {$role->name}\n";
}
