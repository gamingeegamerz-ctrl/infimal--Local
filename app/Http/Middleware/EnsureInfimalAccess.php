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
            return redirect()->route('payment')->with('error', 'Please complete payment to continue.');
        }

        if (is_null($user->otp_verified_at)) {
            return redirect()->route('otp.verify.form')->with('error', 'Please verify OTP to continue.');
        }

        $license = $user->license;
        if (!$license || !($license->status === 'active' || $license->is_active)) {
            return redirect()->route('payment')->with('error', 'Active license is required.');
        }

        return $next($request);
    }
}
