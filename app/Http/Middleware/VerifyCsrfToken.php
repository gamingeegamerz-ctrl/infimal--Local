<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Webhook endpoints
        'webhook/*',
        'stripe/*',
        'paddle/*',
        
        // API endpoints
        'api/*',
        
        // Admin test endpoints (temporary)
        'admin/test-csrf',
        
        // Payment callbacks
        'payment/*/callback',
        'payment/webhook/*',
        
        // Email tracking (public URLs)
        'track/*',
        'unsubscribe/*',
    ];
}
