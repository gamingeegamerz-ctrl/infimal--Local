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

        if (!$user->hasPaid()) {
            return redirect()->route('billing')
                ->with('error', 'Access denied. Payment required.');
        }

        return $next($request);
    }
}
