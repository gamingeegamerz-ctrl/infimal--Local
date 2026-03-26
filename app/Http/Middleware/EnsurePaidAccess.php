<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('payment*') || $request->is('paypal/*') || $request->routeIs('otp.verify.*')) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->hasPaidAccess()) {
            return $next($request);
        }

        if (!$user->is_paid) {
            return redirect()->route('payment')->with('error', 'Payment is required to continue.');
        }

        if ((string) $user->license_status !== 'active' || empty($user->license_key)) {
            return redirect()->route('payment')->with('error', 'License activation is pending.');
        }

        return redirect()->route('otp.verify.form')->with('error', 'Please verify OTP to continue.');
    }
}
