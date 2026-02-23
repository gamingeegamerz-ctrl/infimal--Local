<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->scopes(['email', 'profile'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // Debug info
            \Log::info('Google User Data:', [
                'id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar()
            ]);
            
            // Find user by email
            $user = User::where('email', $googleUser->getEmail())->first();
            
            if ($user) {
                // Update existing user
                $user->google_id = $googleUser->getId();
                $user->avatar = (string) $googleUser->getAvatar(); // Ensure string
                $user->save();
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName() ?: $googleUser->getEmail(),
                    'email' => $googleUser->getEmail(),
                    'password' => Hash::make(uniqid()),
                    'google_id' => $googleUser->getId(),
                    'avatar' => (string) $googleUser->getAvatar(), // Ensure string
                    'email_verified_at' => now(),
                ]);
            }
            
            Auth::login($user, true);
            
            return redirect()->intended('/dashboard');
            
        } catch (\Exception $e) {
            \Log::error('Google OAuth Full Error: ' . $e->getMessage());
            \Log::error('Stack Trace: ' . $e->getTraceAsString());
            
            // Check for specific SQL errors
            if (str_contains($e->getMessage(), 'Unknown column')) {
                return redirect('/login')->with('error', 
                    'Database configuration issue. Please contact administrator.');
            }
            
            return redirect('/login')->with('error', 
                'Google login failed. Please try email login.');
        }
    }
}
