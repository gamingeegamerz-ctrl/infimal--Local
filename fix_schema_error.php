<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔧 Fixing Schema Error\n";
echo "=====================\n";

// Fix SubscriberController
$controllerPath = __DIR__ . '/app/Http/Controllers/SubscriberController.php';
$content = file_get_contents($controllerPath);

// Replace incorrect use statement
$content = str_replace(
    "use App\Http\Controllers\Illuminate\Support\Facades\Schema;",
    "use Illuminate\Support\Facades\Schema;",
    $content
);

// Also make sure other use statements are correct
$useStatements = [
    "use Illuminate\Support\Facades\Schema;",
    "use Illuminate\Support\Facades\Auth;",
    "use Illuminate\Support\Facades\DB;",
    "use Carbon\Carbon;",
    "use App\Models\Subscriber;",
    "use Illuminate\Http\Request;",
];

// Remove duplicate use statements
$lines = explode("\n", $content);
$uniqueUses = [];
$otherLines = [];

foreach ($lines as $line) {
    if (str_starts_with(trim($line), 'use ') && str_contains($line, ';')) {
        $uniqueUses[trim($line)] = trim($line);
    } else {
        $otherLines[] = $line;
    }
}

// Rebuild content with unique use statements
$fixedContent = "<?php\n\nnamespace App\Http\Controllers;\n\n";
foreach ($uniqueUses as $use) {
    $fixedContent .= $use . "\n";
}
$fixedContent .= "\n";
$fixedContent .= implode("\n", $otherLines);

file_put_contents($controllerPath, $fixedContent);

echo "✅ SubscriberController fixed\n";

// Now run migration to fix campaigns table
echo "Running migrations...\n";

try {
    // Check if campaigns table exists
    if (!\Illuminate\Support\Facades\Schema::hasTable('campaigns')) {
        echo "Campaigns table doesn't exist. Creating...\n";
        
        \Illuminate\Support\Facades\Schema::create('campaigns', function ($table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('subject');
            $table->text('content');
            $table->enum('status', ['draft', 'scheduled', 'sent', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('sent_count')->default(0);
            $table->integer('open_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->timestamps();
        });
        
        echo "✅ Campaigns table created\n";
    } else {
        // Check if user_id column exists
        if (!\Illuminate\Support\Facades\Schema::hasColumn('campaigns', 'user_id')) {
            echo "Adding user_id column to campaigns table...\n";
            
            \Illuminate\Support\Facades\Schema::table('campaigns', function ($table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            });
            
            // Update existing records with user_id = 1
            \Illuminate\Support\Facades\DB::table('campaigns')->update(['user_id' => 1]);
            
            echo "✅ user_id column added to campaigns table\n";
        } else {
            echo "✅ Campaigns table already has user_id column\n";
        }
    }
    
    // Also check subscribers table
    if (!\Illuminate\Support\Facades\Schema::hasTable('subscribers')) {
        echo "Subscribers table doesn't exist. Creating...\n";
        
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
        
        echo "✅ Subscribers table created\n";
    }
    
    // Insert demo data if empty
    $userCount = \App\Models\User::count();
    if ($userCount === 0) {
        echo "Creating demo user...\n";
        \App\Models\User::create([
            'name' => 'Demo User',
            'email' => 'demo@infimal.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
        echo "✅ Demo user created\n";
    }
    
    $subscriberCount = \App\Models\Subscriber::count();
    if ($subscriberCount === 0) {
        echo "Creating demo subscribers...\n";
        $users = \App\Models\User::all();
        
        foreach ($users as $user) {
            for ($i = 1; $i <= 50; $i++) {
                \App\Models\Subscriber::create([
                    'user_id' => $user->id,
                    'email' => "user{$i}@example.com",
                    'name' => "User {$i}",
                    'status' => $i % 10 === 0 ? 'unsubscribed' : 'active',
                    'subscribed_at' => now()->subDays(rand(1, 365)),
                    'opens_count' => rand(0, 100),
                    'clicks_count' => rand(0, 50),
                    'location' => ['New York', 'London', 'Tokyo', 'Sydney', 'Berlin'][rand(0, 4)],
                    'tags' => json_encode([['Newsletter', 'Customer', 'VIP'][rand(0, 2)]]),
                ]);
            }
        }
        echo "✅ Demo subscribers created\n";
    }
    
    echo "\n✅ FIX COMPLETED SUCCESSFULLY!\n";
    echo "You can now access: https://infimal.site/subscribers\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
