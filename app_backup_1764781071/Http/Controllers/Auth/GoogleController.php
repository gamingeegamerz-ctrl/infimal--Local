<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Payment;

class GoogleController extends Controller
{
    public function redirect()
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (\Exception $e) {
            Log::error('Google redirect failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')
                ->with('error', 'Google login is currently unavailable. Please try email login.');
        }
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            Log::info('Google callback received', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName()
            ]);
            
            // Check if user already exists by email
            $user = User::where('email', $googleUser->getEmail())->first();
            
            if ($user) {
                Log::info('Existing user found', ['user_id' => $user->id]);
                
                // Update Google ID if not set
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->getId()]);
                }
                
                // Log the user in
                Auth::login($user);
                
                Log::info('Google login successful - existing user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'license_status' => $user->license_status
                ]);
                
            } else {
                Log::info('Creating new user from Google');
                
                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => Hash::make(Str::random(24)),
                    'email_verified_at' => now(), // Google emails are verified
                    'license_status' => 'pending', // No license yet
                ]);
                
                Log::info('Google login successful - new user created', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                
                Auth::login($user);
            }
            
            // 🚀 CRITICAL: After login, redirect based on license status
            return $this->handlePostLoginRedirect($user);
            
        } catch (\Exception $e) {
            Log::error('Google callback failed', ['error' => $e->getMessage()]);
            return redirect()->route('login')
                ->with('error', 'Google login failed. Please try again or use email login.');
        }
    }
    
    private function handlePostLoginRedirect($user)
    {
        Log::info('Handling post-login redirect', [
            'user_id' => $user->id,
            'license_status' => $user->license_status,
            'has_license_key' => !empty($user->license_key)
        ]);
        
        // 1. Check if user has active license
        if ($user->hasActiveLicense()) {
            Log::info('User has active license, redirecting to dashboard');
            return redirect()->route('dashboard')
                ->with('success', 'Welcome back to InfiMal!');
        }
        
        // 2. Check if user has license key but not verified (pending)
        if ($user->license_key && $user->license_status === 'pending') {
            Log::info('User has pending license, redirecting to verification');
            return redirect()->route('verify.license')
                ->with('info', 'Please verify your license key to access dashboard.')
                ->with('license_key', $user->license_key);
        }
        
        // 3. If no license at all, redirect to payment
        Log::info('User has no license, redirecting to payment');
        return redirect()->route('payment.page')
            ->with('info', 'Welcome to InfiMal! Please complete payment to access dashboard.')
            ->with('new_user', true);
    }
}
