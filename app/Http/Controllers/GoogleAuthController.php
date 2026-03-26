<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('email', $googleUser->getEmail())->first();
            if (!$user) {
                $rawPassword = Str::random(64);
                $user = User::create([
                    'name' => $googleUser->getName() ?: 'Google User',
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make($rawPassword),
                    'payment_status' => 'unpaid',
                    'is_paid' => false,
                    'license_status' => 'pending',
                ]);
            } elseif (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }

            Auth::login($user, true);

            if (!$user->hasPaid()) {
                return redirect()->route('payment');
            }

            if (!$user->hasPaidAccess()) {
                return redirect()->route('otp.notice');
            }

            return redirect()->route('dashboard');
        } catch (\Throwable) {
            return redirect()->route('login')->with('error', 'Google login failed.');
        }
    }
}
