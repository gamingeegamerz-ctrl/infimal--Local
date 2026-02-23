<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔐 Creating Login User\n";
echo "=====================\n";

$email = 'kanishghongade@gmail.com';
$password = 'KANashu@257896346123k';
$name = 'Kanish';

try {
    // Check if user exists
    $user = \App\Models\User::where('email', $email)->first();
    
    if ($user) {
        echo "User already exists!\n";
        echo "Updating password...\n";
        
        // Update password
        $user->password = \Illuminate\Support\Facades\Hash::make($password);
        $user->save();
        
        echo "✅ Password updated!\n";
    } else {
        // Create new user
        $user = \App\Models\User::create([
            'name' => $name,
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'email_verified_at' => now(),
        ]);
        
        echo "✅ User created successfully!\n";
    }
    
    echo "\nLogin Details:\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "Name: $name\n";
    
    // Test login
    if (\Illuminate\Support\Facades\Auth::attempt(['email' => $email, 'password' => $password])) {
        echo "✅ Authentication test: PASSED\n";
    } else {
        echo "❌ Authentication test: FAILED\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
