<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Subscriber;
use Illuminate\Database\Seeder;

class SubscriberSeeder extends Seeder
{
    public function run()
    {
        $user = User::first();
        
        Subscriber::factory()->count(50)->create([
            'user_id' => $user->id,
            'status' => 'active',
            'source' => 'import'
        ]);
    }
}
