<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasPaid()) {
            return redirect()->route('payment')->with('error', 'Payment is required to continue.');
        }

        $hasLicenseRecord = $user->activeLicense()->exists();
        $hasLegacyLicense = !empty($user->license_key) && (string) $user->license_status === 'active';

        if (!$hasLicenseRecord && !$hasLegacyLicense) {
            return redirect()->route('payment')->with('error', 'Active license is required.');
        }

        $otpRequired = !empty($user->otp_code) || !is_null($user->otp_expires_at);
        if ($otpRequired && !$user->otp_verified_at) {
            return redirect()->route('otp.verify.form')->with('error', 'Please verify OTP first.');
        }

        return $next($request);
    }
}
