<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureInfimalAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasPaid()) {
            return redirect()->route('payment')->with('error', 'Payment is required before dashboard access.');
        }

        if (is_null($user->otp_verified_at)) {
            return redirect()->route('otp.notice')->with('error', 'Please verify your email OTP first.');
        }

        if (!$user->hasPaidAccess()) {
            return redirect()->route('payment')->with('error', 'Your license is missing or inactive.');
        }

        return $next($request);
    }
}
