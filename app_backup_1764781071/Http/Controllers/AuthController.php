<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\LicenseKeyMail;

class AuthController extends Controller
{
    // ====================
    // SHOW LOGIN PAGE
    // ====================
    public function showLogin()
    {
        // If already logged in, redirect based on license status
        if (Auth::check()) {
            $user = Auth::user();
            return $this->checkAndRedirect($user);
        }
        
        return view('auth.login');
    }

    // ====================
    // LOGIN USER
    // ====================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->remember)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        $user = Auth::user();
        Log::info('User logged in via email', ['user_id' => $user->id]);
        
        return $this->checkAndRedirect($user);
    }

    // ====================
    // SHOW REGISTER PAGE
    // ====================
    public function showRegister()
    {
        if (Auth::check()) {
            $user = Auth::user();
            return $this->checkAndRedirect($user);
        }
        
        return view('auth.register');
    }

    // ====================
    // REGISTER USER
    // ====================
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'license_status' => 'pending',
        ]);

        Log::info('New user registered', ['user_id' => $user->id]);
        Auth::login($user);

        return redirect()->route('payment.page')
            ->with('success', 'Registration successful! Please complete payment.')
            ->with('new_user', true);
    }

    // ====================
    // CHECK AND REDIRECT BASED ON LICENSE
    // ====================
    private function checkAndRedirect($user)
    {
        Log::info('Checking license status', [
            'user_id' => $user->id,
            'license_status' => $user->license_status,
            'has_license_key' => !empty($user->license_key)
        ]);
        
        // 1. Active license
        if ($user->hasActiveLicense()) {
            return redirect()->route('dashboard')
                ->with('success', 'Welcome back!');
        }
        
        // 2. Pending license (has key but not verified)
        if ($user->license_key && $user->license_status === 'pending') {
            return redirect()->route('verify.license')
                ->with('info', 'Please verify your license key.')
                ->with('license_key', $user->license_key);
        }
        
        // 3. No license
        return redirect()->route('payment.page')
            ->with('info', 'Please complete payment to access dashboard.');
    }

    // ====================
    // SHOW PAYMENT PAGE
    // ====================
    public function showPaymentPage()
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $user = Auth::user();
        
        if ($user->hasActiveLicense()) {
            return redirect()->route('dashboard')->with('info', 'You already have active license.');
        }

        if ($user->license_key && $user->license_status === 'pending') {
            return redirect()->route('verify.license')->with('info', 'Please verify license key.');
        }

        return view('payment.checkout');
    }

    // ====================
    // PROCESS PAYMENT
    // ====================
    public function processPayment(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $request->validate([
            'plan' => 'required|in:lifetime',
            'payment_method' => 'required|in:card,paypal,apple_pay'
        ]);

        $user = Auth::user();
        
        // Generate license key
        $licenseKey = 'INFIMAL-' . strtoupper(Str::random(4)) . '-' . 
                      strtoupper(Str::random(4)) . '-' . 
                      strtoupper(Str::random(4));
        
        $expiryDate = now()->addYears(100);

        $user->update([
            'license_key' => $licenseKey,
            'license_status' => 'pending',
            'license_expires_at' => $expiryDate,
            'plan' => 'lifetime',
            'payment_id' => 'PAY-' . strtoupper(Str::random(10))
        ]);

        Payment::create([
            'user_id' => $user->id,
            'payment_id' => $user->payment_id,
            'plan' => 'lifetime',
            'amount' => 299.00,
            'currency' => 'USD',
            'status' => 'completed',
            'payment_method' => $request->payment_method,
            'metadata' => [
                'license_key' => $licenseKey,
                'user_email' => $user->email,
                'timestamp' => now()->toDateTimeString()
            ]
        ]);

        Log::info('Payment processed', [
            'user_id' => $user->id,
            'license_key' => $licenseKey
        ]);

        // Send email
        try {
            Mail::to($user->email)->send(new LicenseKeyMail($licenseKey, $user->name));
            Log::info('License email sent', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send email', ['error' => $e->getMessage()]);
        }

        return redirect()->route('verify.license')
            ->with('success', 'Payment successful! Check email for license key.')
            ->with('license_key', $licenseKey);
    }

    // ====================
    // SHOW LICENSE VERIFICATION
    // ====================
    public function showVerifyLicense()
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $user = Auth::user();
        
        if ($user->hasActiveLicense()) {
            return redirect()->route('dashboard')->with('info', 'License already active.');
        }

        if (!$user->license_key) {
            return redirect()->route('payment.page')->with('warning', 'Complete payment first.');
        }

        return view('auth.verify-license');
    }

    // ====================
    // VERIFY LICENSE KEY
    // ====================
    public function verifyLicense(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $request->validate([
            'license_key' => 'required|string|min:10'
        ]);

        $user = Auth::user();
        
        if ($user->license_key === $request->license_key) {
            $user->update([
                'license_status' => 'active',
                'license_verified_at' => now()
            ]);

            Log::info('License verified', ['user_id' => $user->id]);
            
            return redirect()->route('dashboard')
                ->with('success', 'License verified! Welcome to dashboard.');
        }

        Log::warning('Invalid license key attempt', [
            'user_id' => $user->id,
            'attempted_key' => $request->license_key
        ]);
        
        return back()->withErrors([
            'license_key' => 'Invalid license key. Check and try again.'
        ])->withInput();
    }

    // ====================
    // PAYMENT SUCCESS PAGE
    // ====================
    public function paymentSuccess()
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $user = Auth::user();
        $licenseKey = session('license_key') ?? $user->license_key;

        return view('payment.success', compact('licenseKey'));
    }

    // ====================
    // LOGOUT
    // ====================
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login')->with('success', 'Logged out successfully.');
    }

    // ====================
    // PASSWORD RESET
    // ====================
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        // Add password reset logic
        return back()->with('success', 'Password reset link sent.');
    }

    public function showResetPassword($token)
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function resetPassword(Request $request)
    {
        // Add password reset logic
        return redirect()->route('login')->with('success', 'Password reset successful!');
    }
}
