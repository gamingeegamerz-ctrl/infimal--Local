<?php

namespace App\Services;

class BackoffCalculator
{
    public static function delay(int $attempt): int
    {
        return match ($attempt) {
            1 => 60,     // 1 min
            2 => 300,    // 5 min
            3 => 900,    // 15 min
            default => 0 // stop retry
        };
    }
}
