<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('payment') || $request->is('paypal/*') || $request->routeIs('otp.verify.*')) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasPaid()) {
            return redirect()->route('payment')->with('error', 'Payment is required to continue.');
        }

        $hasActiveLicense = $user->activeLicense()->exists()
            || ((string) $user->license_status === 'active' && !empty($user->license_key));

        if (!$hasActiveLicense) {
            return redirect()->route('payment')->with('error', 'Active license is required.');
        }

        $otpPending = !is_null($user->otp_code) || !is_null($user->otp_expires_at);
        if ($otpPending && !$user->otp_verified_at) {
            return redirect()->route('otp.verify.form')->with('error', 'Please verify OTP first.');
        }

        return $next($request);
    }
}
