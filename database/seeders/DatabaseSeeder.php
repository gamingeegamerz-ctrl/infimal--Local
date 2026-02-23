<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Campaign;
use App\Models\Subscriber;
use App\Models\MailingList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'kanishghongade@gmail.com',
            'password' => Hash::make('KANashu@257896346123k'),
            'email_verified_at' => now(),
        ]);

        // Create workspace (only using columns that exist)
        $workspace = Workspace::create([
            'name' => 'My Email Marketing',
            'slug' => 'my-email-marketing',
            'user_id' => $admin->id,
            'description' => 'Primary workspace for email campaigns',
            // Removed: member_count, campaign_count, subscriber_count (columns don't exist)
        ]);

        // Create mailing lists (use MailingList, not EmailList)
        $newsletterList = MailingList::create([
            'name' => 'Newsletter Subscribers',
            'description' => 'People subscribed to weekly newsletter',
        ]);

        $premiumList = MailingList::create([
            'name' => 'Premium Customers',
            'description' => 'Paid customers list',
        ]);

        // Create campaigns (only using columns that exist in your table)
        Campaign::create([
            'name' => 'Welcome Email Series',
            'subject' => 'Welcome to Our Platform!',
            'content' => '<h1>Welcome!</h1><p>Thank you for joining us...</p>',
            'list_id' => $newsletterList->id,
            'status' => 'sent',
            'scheduled_at' => now()->subDays(5),
        ]);

        Campaign::create([
            'name' => 'Product Launch Announcement',
            'subject' => 'Exciting New Features!',
            'content' => '<h1>New Features Launched</h1><p>We are excited to announce...</p>',
            'list_id' => $newsletterList->id,
            'status' => 'sent',
            'scheduled_at' => now()->subDays(3),
        ]);

        Campaign::create([
            'name' => 'Weekly Newsletter',
            'subject' => 'This Week\'s Updates',
            'content' => '<h1>Weekly Newsletter</h1><p>Here are this week\'s updates...</p>',
            'list_id' => $newsletterList->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays(1),
        ]);

        // Create subscribers (only using columns that exist)
        $faker = \Faker\Factory::create();
        for ($i = 1; $i <= 50; $i++) {
            Subscriber::create([
                'email' => $faker->unique()->safeEmail,
                'list_id' => rand(0, 1) ? $newsletterList->id : $premiumList->id,
                'status' => 'active',
            ]);
        }

        echo "\n✅ Database seeded successfully!\n";
        echo "📧 Admin Email: kanishghongade@gmail.com\n";
        echo "🔑 Password: KANashu@257896346123k\n";
        echo "🚀 You can now login!\n\n";
    }
}