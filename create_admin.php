<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Hash;
use App\Models\User;

// Admin user create karo
$admin = User::create([
    'name' => 'Kanishk Admin',
    'email' => 'kanishghongade@gmail.com',
    'password' => Hash::make('KANashu@257896346123k'),
    'role' => 'admin',
    'email_verified_at' => now(),
]);

echo "✅ Admin user created successfully!\n";
echo "Email: kanishghongade@gmail.com\n";
echo "Password: KANashu@257896346123k\n";
echo "Role: admin\n";
