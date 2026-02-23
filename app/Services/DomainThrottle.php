<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DomainThrottle
{
    protected static array $rules = [
        'gmail.com'    => 2,
        'yahoo.com'    => 4,
        'outlook.com'  => 3,
        'hotmail.com' => 3,
        'live.com'     => 3,
        'zoho.com'     => 3,
        'icloud.com'  => 5,
    ];

    /**
     * Get delay (in seconds) for a recipient email
     */
    public static function getDelay(string $email): int
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));

        $delay = self::$rules[$domain] ?? 1;

        $cacheKey = 'domain_last_sent_' . $domain;
        $lastSent = Cache::get($cacheKey);

        if (!$lastSent) {
            Cache::put($cacheKey, now()->timestamp, 3600);
            return 0;
        }

        $elapsed = now()->timestamp - $lastSent;

        if ($elapsed < $delay) {
            return $delay - $elapsed;
        }

        Cache::put($cacheKey, now()->timestamp, 3600);
        return 0;
    }
}
