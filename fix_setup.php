<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

echo "🚀 Starting Emergency Fix...\n";

// Switch to SQLite in .env
file_put_contents(__DIR__.'/.env', str_replace(
    'DB_CONNECTION=mysql',
    'DB_CONNECTION=sqlite',
    file_get_contents(__DIR__.'/.env')
));

// Create SQLite database
touch(__DIR__.'/database/database.sqlite');

// Create tables manually
DB::statement('DROP TABLE IF EXISTS contacts');
DB::statement('DROP TABLE IF EXISTS users');

DB::statement('
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT "super_admin",
        subscription VARCHAR(20) DEFAULT "lifetime",
        email_verified_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
');

DB::statement('
    CREATE TABLE contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name VARCHAR(255) NULL,
        last_name VARCHAR(255) NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(255) NULL,
        company VARCHAR(255) NULL,
        status VARCHAR(20) DEFAULT "active",
        user_id INTEGER NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
');

// Create permanent admin
DB::table('users')->insert([
    'name' => 'Kanishk Super Admin',
    'email' => 'kanishghongade@gmail.com',
    'password' => Hash::make('KANashu@257896346123k'),
    'role' => 'super_admin',
    'subscription' => 'lifetime',
    'email_verified_at' => now(),
]);

echo "✅ EMERGENCY FIX COMPLETED!\n";
echo "📧 Login: kanishghongade@gmail.com\n";
echo "🔑 Password: KANashu@257896346123k\n";
