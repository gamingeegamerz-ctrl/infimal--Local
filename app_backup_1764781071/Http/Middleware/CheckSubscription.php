<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        // Super admin ko hamesha access do
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // Lifetime subscription check
        if ($user->subscription === 'lifetime') {
            return $next($request);
        }

        // Premium subscription check (expiry date)
        if ($user->subscription === 'premium' && $user->subscription_expires_at) {
            if (Carbon::now()->lt($user->subscription_expires_at)) {
                return $next($request);
            }
        }

        // Free user ya expired subscription - payment page redirect
        if ($user->subscription === 'free' || 
            ($user->subscription === 'premium' && Carbon::now()->gt($user->subscription_expires_at))) {
            return redirect('/payment')->with('error', 'Please subscribe to access the dashboard!');
        }

        return $next($request);
    }
}
