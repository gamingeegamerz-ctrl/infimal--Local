<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGoogleOAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // Check if user has Google OAuth connected
        if (!$user->google_id || !$user->google_token) {
            return redirect()->route('profile.settings')
                ->with('error', 'Please connect your Google account first.');
        }
        
        return $next($request);
    }
}
