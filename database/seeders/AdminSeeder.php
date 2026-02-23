<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AdminSeeder extends Seeder
{
    public function run()
    {
        // Permanent Super Admin Create Karo
        DB::table('users')->insert([
            'name' => 'Kanishk Super Admin',
            'email' => 'kanishghongade@gmail.com',
            'password' => Hash::make('KANashu@257896346123k'),
            'role' => 'super_admin',
            'subscription' => 'lifetime',
            'subscription_expires_at' => null, // Never expires
            'email_verified_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Additional Permanent Admin
        DB::table('users')->insert([
            'name' => 'Infimal Admin',
            'email' => 'admin@infimal.com',
            'password' => Hash::make('Admin@123456'),
            'role' => 'super_admin', 
            'subscription' => 'lifetime',
            'subscription_expires_at' => null,
            'email_verified_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        echo "✅ Permanent Super Admins created successfully!\n";
        echo "📧 kanishghongade@gmail.com | Password: KANashu@257896346123k\n";
        echo "📧 admin@infimal.com | Password: Admin@123456\n";
    }
}
