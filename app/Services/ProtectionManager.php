<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ProtectionManager
{
    protected int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    /**
     * Register bounce silently
     */
    public function registerBounce(): void
    {
        $key = "bounce_count_{$this->userId}";
        Cache::increment($key);
        Cache::put($key, Cache::get($key), now()->addHours(24));
    }

    /**
     * Get bounce rate percentage
     */
    public function getBounceRate(int $sentToday): float
    {
        if ($sentToday === 0) {
            return 0.0;
        }

        $bounces = Cache::get("bounce_count_{$this->userId}", 0);
        return ($bounces / $sentToday) * 100;
    }

    /**
     * Check if bounce rate is dangerous
     */
    public function isHighBounce(int $sentToday): bool
    {
        return $this->getBounceRate($sentToday) > 5;
    }
}
