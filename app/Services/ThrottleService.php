<?php

namespace App\Services;

use App\Models\ThrottleSetting;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * ============================================
 * THROTTLE SERVICE
 * ============================================
 * Controls email sending rate and time windows
 * 
 * Features:
 * - emails_per_minute limit
 * - sending_time_window (start_time, end_time)
 * - Per-user or global settings
 */
class ThrottleService
{
    /**
     * Get throttle settings for user (or global if null)
     */
    public function getSettings($userId = null): array
    {
        $setting = ThrottleSetting::where('user_id', $userId)->first();
        
        if (!$setting && $userId) {
            // Try to get global settings
            $setting = ThrottleSetting::whereNull('user_id')->first();
        }

        if (!$setting) {
            // Default settings
            return [
                'emails_per_minute' => 60,
                'sending_start_time' => '09:00:00',
                'sending_end_time' => '17:00:00',
            ];
        }

        return [
            'emails_per_minute' => $setting->emails_per_minute,
            'sending_start_time' => $setting->sending_start_time,
            'sending_end_time' => $setting->sending_end_time,
        ];
    }

    /**
     * Check if we can send email now (throttle + time window)
     * 
     * @param int|null $userId
     * @return array ['can_send' => bool, 'delay_seconds' => int, 'reason' => string]
     */
    public function canSendNow($userId = null): array
    {
        $settings = $this->getSettings($userId);
        
        // Check time window
        $now = now();
        $startTime = Carbon::createFromTimeString($settings['sending_start_time']);
        $endTime = Carbon::createFromTimeString($settings['sending_end_time']);
        $currentTime = $now->setTime($now->hour, $now->minute, $now->second);

        // Check if within sending window
        if ($currentTime->lt($startTime) || $currentTime->gt($endTime)) {
            // Calculate delay until next window
            $delay = 0;
            if ($currentTime->lt($startTime)) {
                // Before start time, wait until start time
                $delay = $now->diffInSeconds($startTime);
            } else {
                // After end time, wait until next day start time
                $nextStart = $startTime->copy()->addDay();
                $delay = $now->diffInSeconds($nextStart);
            }

            return [
                'can_send' => false,
                'delay_seconds' => $delay,
                'reason' => 'Outside sending time window'
            ];
        }

        // Check rate limit (emails per minute)
        $cacheKey = "throttle:{$userId}:minute:" . $now->format('Y-m-d-H-i');
        $sentThisMinute = Cache::get($cacheKey, 0);

        if ($sentThisMinute >= $settings['emails_per_minute']) {
            // Wait until next minute
            $nextMinute = $now->copy()->addMinute()->startOfMinute();
            $delay = $now->diffInSeconds($nextMinute);

            return [
                'can_send' => false,
                'delay_seconds' => $delay,
                'reason' => 'Rate limit reached'
            ];
        }

        return [
            'can_send' => true,
            'delay_seconds' => 0,
            'reason' => null
        ];
    }

    /**
     * Record that an email was sent (for throttle counting)
     */
    public function recordSent($userId = null): void
    {
        $now = now();
        $cacheKey = "throttle:{$userId}:minute:" . $now->format('Y-m-d-H-i');
        
        // Increment counter, expire after 2 minutes
        Cache::increment($cacheKey);
        Cache::put($cacheKey, Cache::get($cacheKey, 0), now()->addMinutes(2));
    }

    /**
     * Get current sending rate for this minute
     */
    public function getCurrentRate($userId = null): int
    {
        $now = now();
        $cacheKey = "throttle:{$userId}:minute:" . $now->format('Y-m-d-H-i');
        return Cache::get($cacheKey, 0);
    }

    /**
     * Reset throttle counters (for testing or manual reset)
     */
    public function resetCounters($userId = null): void
    {
        $pattern = "throttle:{$userId}:minute:*";
        Cache::flush(); // In production, use a more targeted approach
    }
}
