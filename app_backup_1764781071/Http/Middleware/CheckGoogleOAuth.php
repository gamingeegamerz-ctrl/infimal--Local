<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGoogleOAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user came from Google OAuth
        if (!session('google_user_data')) {
            return redirect()->route('login')->with('error', 'Please login with Google first.');
        }
        
        return $next($request);
    }
}
