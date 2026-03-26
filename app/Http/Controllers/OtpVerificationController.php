<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OtpVerificationController extends Controller
{
    public function notice()
    {
        return view('auth.verify-otp');
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if (!$user->otp_code || !$user->otp_expires_at || now()->greaterThan($user->otp_expires_at)) {
            return back()->withErrors(['otp' => 'OTP expired. Please complete payment again.']);
        }

        if (!Hash::check($data['otp'], $user->otp_code)) {
            return back()->withErrors(['otp' => 'Invalid OTP.']);
        }

        $user->update([
            'otp_verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        return redirect()->route('dashboard')->with('success', 'OTP verified. Dashboard unlocked.');
    }
}
