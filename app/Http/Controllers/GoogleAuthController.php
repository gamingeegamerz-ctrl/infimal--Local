<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Redirect user to Google
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google callback
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // ?? Find existing user by email
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                // ?? Create new user
                $user = User::create([
                    'name'       => $googleUser->getName(),
                    'email'      => $googleUser->getEmail(),
                    'google_id'  => $googleUser->getId(),
                    'password'   => Hash::make(Str::random(64)),
                    // IMPORTANT DEFAULTS
                    'payment_status' => 'unpaid',
                    'license_key'    => null,
                ]);
            } else {
                // ?? Update Google ID if missing
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->getId()
                    ]);
                }
            }

            Auth::login($user, true);

            if (!$user->hasPaid()) {
                return redirect()->route('payment')
                    ->with('info', 'Please complete payment to access dashboard.');
            }

            if (is_null($user->otp_verified_at)) {
                return redirect()->route('otp.verify.form')
                    ->with('info', 'Please verify OTP to activate access.');
            }

            return redirect()->route('dashboard')
                ->with('success', 'Welcome ' . $user->name . '!');

        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('error', 'Google login failed. Please try again.');
        }
    }
}
