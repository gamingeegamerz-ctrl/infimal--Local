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

        if ($request->routeIs('billing') || $request->routeIs('payment') || $request->routeIs('payment.*') || $request->is('logout')) {
            return $next($request);
        }

        if (!$user->hasPaid()) {
            return redirect()->route('billing')
                ->with('error', 'Please complete your payment to access the dashboard.');
        }

        return $next($request);
    }
}
