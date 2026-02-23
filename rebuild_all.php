<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔥 COMPLETE REBUILD SCRIPT\n";
echo "=========================\n";

try {
    // 1. Drop all tables
    echo "Dropping all tables...\n";
    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
    
    $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        \Illuminate\Support\Facades\DB::statement("DROP TABLE IF EXISTS $tableName");
        echo "Dropped: $tableName\n";
    }
    
    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "✓ All tables dropped\n";
    
    // 2. Clear migration table
    echo "Clearing migrations...\n";
    \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS migrations');
    echo "✓ Migration table cleared\n";
    
    // 3. Create fresh migrations table
    echo "Creating fresh migrations...\n";
    \Illuminate\Support\Facades\Artisan::call('migrate:install');
    
    // 4. Run only essential migrations
    $essentialMigrations = [
        '2024_01_01_000001_create_users_table.php',
        '2025_12_02_175142_create_subscribers_table.php',
        '2025_12_02_175142_create_campaigns_table.php',
        '2025_12_02_175142_create_payments_table.php',
    ];
    
    foreach ($essentialMigrations as $migration) {
        $path = database_path('migrations/' . $migration);
        if (file_exists($path)) {
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--path' => 'database/migrations/' . $migration,
                '--force' => true
            ]);
            echo "✓ Ran: $migration\n";
        }
    }
    
    // 5. Create admin user
    echo "Creating admin user...\n";
    $user = \App\Models\User::create([
        'name' => 'Admin',
        'email' => 'admin@infimal.com',
        'password' => \Illuminate\Support\Facades\Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    echo "✓ Admin user created\n";
    
    // 6. Create demo data
    echo "Creating demo subscribers...\n";
    for ($i = 1; $i <= 10; $i++) {
        \App\Models\Subscriber::create([
            'user_id' => $user->id,
            'email' => "user{$i}@example.com",
            'name' => "User {$i}",
            'status' => 'active',
            'subscribed_at' => now(),
        ]);
    }
    echo "✓ Demo subscribers created\n";
    
    echo "\n✅ REBUILD COMPLETED SUCCESSFULLY!\n";
    echo "==================================\n";
    echo "Database: " . env('DB_DATABASE') . "\n";
    echo "Login: admin@infimal.com\n";
    echo "Password: password123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
