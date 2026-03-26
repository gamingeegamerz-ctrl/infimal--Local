<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AccessVerificationController extends Controller
{
    public function show()
    {
        return view('auth.otp-verify');
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if (!$user->otp_code || !$user->otp_expires_at || now()->greaterThan($user->otp_expires_at)) {
            return back()->withErrors(['otp_code' => 'OTP expired. Please request a new code.']);
        }

        if (!hash_equals((string) $user->otp_code, (string) $data['otp_code'])) {
            return back()->withErrors(['otp_code' => 'Invalid OTP code.']);
        }

        $user->forceFill([
            'otp_verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        return redirect()->route('dashboard')->with('success', 'OTP verified successfully.');
    }

    public function resend(Request $request)
    {
        $user = $request->user();
        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(15),
        ])->save();

        try {
            Mail::raw("Your InfiMal verification code is {$otp}. It expires in 15 minutes.", function ($m) use ($user) {
                $m->to($user->email)->subject('InfiMal OTP Verification');
            });
        } catch (\Throwable) {
            // no-op: app should never crash on email failure
        }

        return back()->with('success', 'OTP sent to your email.');
    }
}
