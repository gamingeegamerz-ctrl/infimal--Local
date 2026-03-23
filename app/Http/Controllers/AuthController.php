<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();
            if (!$user->hasPaid()) {
                return redirect()->route('billing')
                    ->with('info', 'Complete your $299 payment to unlock InfiMal Pro.');
            }

            return redirect()->route('dashboard')->with('success', 'Login successful!');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials. Please check your email and password.',
        ])->withInput($request->except('password'));
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'payment_status' => 'unpaid',
            'plan_name' => 'InfiMal Pro',
            'is_paid' => false,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('billing')->with('success', 'Account created. Complete payment to activate access.');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        return back()->with('status', 'Password reset link sent!');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        return redirect()->route('login')->with('status', 'Password reset successfully!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out successfully!');
    }
}
