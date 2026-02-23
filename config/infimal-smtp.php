<?php

return [
    'postal_url' => env('POSTAL_URL', 'http://127.0.0.1:5000'),
    'postal_api_key' => env('POSTAL_API_KEY'),
    
    'warmup_schedule' => [
        1 => 100,
        2 => 500,
        3 => 1000,
        5 => 5000,
        7 => 10000,
        10 => 20000,
        15 => 50000,
        20 => 75000,
        30 => 100000
    ],
    
    'max_daily_limit' => 100000,
    'spike_threshold' => 10000,
];
