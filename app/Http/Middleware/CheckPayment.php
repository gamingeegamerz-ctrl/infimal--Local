<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPayment
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($request->routeIs('billing', 'payment', 'payment.*') || $request->is('paypal/*')) {
            return $next($request);
        }

        if (!$user->hasPaid()) {
            return redirect()->route('billing')
                ->with('error', 'Access denied. Payment required.');
        }

        return $next($request);
    }
}
