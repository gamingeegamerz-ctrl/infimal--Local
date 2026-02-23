<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LicenseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user if not exists
        if (!User::where('email', 'admin@infimal.com')->exists()) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@infimal.com',
                'password' => Hash::make('password123'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);
        }
        
        // Create some test users with licenses
        for ($i = 1; $i <= 10; $i++) {
            $user = User::create([
                'name' => 'Test User ' . $i,
                'email' => 'test' . $i . '@infimal.com',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_admin' => false,
            ]);
            
            // Randomly assign licenses
            if ($i <= 7) {
                $user->update([
                    'license_status' => 'active',
                    'license_key' => 'INF-' . Str::upper(Str::random(16)),
                    'license_expires_at' => now()->addDays(rand(1, 365)),
                    'license_plan' => ['basic', 'pro', 'enterprise'][rand(0, 2)]
                ]);
            } elseif ($i <= 9) {
                $user->update([
                    'license_status' => 'expired',
                    'license_key' => 'INF-' . Str::upper(Str::random(16)),
                    'license_expires_at' => now()->subDays(rand(1, 30)),
                    'license_plan' => 'basic'
                ]);
            }
            // Last user gets no license
        }
        
        $this->command->info('✅ Test data seeded successfully!');
        $this->command->info('👑 Admin Login: admin@infimal.com / password123');
    }
}
