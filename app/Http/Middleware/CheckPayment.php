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
        
        // Allow access to billing/payment pages
        if ($request->is('billing*') || $request->is('logout')) {
            return $next($request);
        }
        
        // Check if user has paid
        if (!$user || !$user->hasPaid()) {
            return redirect()->route('billing.index')
                ->with('error', 'Please complete your payment to access the dashboard.');
        }
        
        return $next($request);
    }
}
