<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Fixing subscribers table...\n";

try {
    // Drop if exists
    \Illuminate\Support\Facades\Schema::dropIfExists('subscribers');
    echo "✓ Dropped existing table\n";
    
    // Create new table
    \Illuminate\Support\Facades\Schema::create('subscribers', function ($table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('email');
        $table->string('name')->nullable();
        $table->enum('status', ['active', 'unsubscribed', 'bounced'])->default('active');
        $table->timestamp('subscribed_at')->nullable();
        $table->timestamp('unsubscribed_at')->nullable();
        $table->timestamp('last_campaign_sent')->nullable();
        $table->integer('opens_count')->default(0);
        $table->integer('clicks_count')->default(0);
        $table->string('location')->nullable();
        $table->json('tags')->nullable();
        $table->timestamps();
        
        $table->unique(['user_id', 'email']);
        $table->index(['user_id', 'status']);
    });
    
    echo "✓ Created new subscribers table\n";
    
    // Insert demo data
    $demoData = [
        ['user_id' => 1, 'email' => 'john@example.com', 'name' => 'John Doe'],
        ['user_id' => 1, 'email' => 'jane@example.com', 'name' => 'Jane Smith'],
        ['user_id' => 1, 'email' => 'alice@example.com', 'name' => 'Alice Johnson'],
        ['user_id' => 1, 'email' => 'bob@example.com', 'name' => 'Bob Williams'],
        ['user_id' => 1, 'email' => 'charlie@example.com', 'name' => 'Charlie Brown'],
    ];
    
    foreach ($demoData as $data) {
        \Illuminate\Support\Facades\DB::table('subscribers')->insert([
            ...$data,
            'status' => 'active',
            'subscribed_at' => now(),
            'tags' => json_encode(['Customer']),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    echo "✓ Inserted demo data\n";
    echo "✅ Fix completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
