<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

DB::table('users')->updateOrInsert(
    ['email' => 'admin@jsonapi.com'],
    [
        'password' => Hash::make('secret'),
        'user_role_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]
);

echo "User created/updated successfully\n";
