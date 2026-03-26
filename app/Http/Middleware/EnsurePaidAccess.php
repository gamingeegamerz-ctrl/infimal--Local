<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePaidAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($request->routeIs('payment*') || $request->routeIs('paypal.*') || $request->routeIs('otp.*') || $request->is('payment*') || $request->is('paypal/*') || $request->is('verify-otp*')) {
            return $next($request);
        }

        if (!$user->hasPaid()) {
            return redirect()->route('payment')->with('error', 'Please complete payment first.');
        }

        if (empty($user->license_key) || $user->license_status !== 'active') {
            return redirect()->route('payment')->with('error', 'Active license is required.');
        }

        if (is_null($user->otp_verified_at)) {
            return redirect()->route('otp.notice')->with('error', 'Please verify OTP to continue.');
        }

        return $next($request);
    }
}
