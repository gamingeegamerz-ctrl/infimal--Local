<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateEmailStats extends Command
{
    protected $signature = 'email:update-stats';
    protected $description = 'Update email statistics and rates';

    public function handle()
    {
        $this->info('Updating email statistics...');
        
        // Update open rates
        DB::table('email_logs')
            ->where('opens_count', '>', 0)
            ->update([
                'open_rate' => DB::raw('ROUND((opens_count / 1) * 100, 2)'),
                'updated_at' => now()
            ]);
        
        // Update click rates
        DB::table('email_logs')
            ->where('clicks_count', '>', 0)
            ->update([
                'click_rate' => DB::raw('ROUND((clicks_count / GREATEST(opens_count, 1)) * 100, 2)'),
                'updated_at' => now()
            ]);
        
        $this->info('Email statistics updated successfully!');
        
        return 0;
    }
}
