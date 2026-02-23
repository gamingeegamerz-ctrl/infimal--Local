<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login first.');
        }

        $user = Auth::user();
        
        // List of admin emails
        $adminEmails = [
            'kanishghongade@gmail.com',
            'gamingeegamerz@gmail.com'
        ];
        
        // Check if user is admin by email
        if (in_array($user->email, $adminEmails)) {
            return $next($request);
        }
        
        // Check if user has is_admin field set to true
        if (isset($user->is_admin) && $user->is_admin === true) {
            return $next($request);
        }
        
        // Check if user has role field set to admin
        if (isset($user->role) && ($user->role === 'admin' || $user->role === 'super_admin')) {
            return $next($request);
        }

        // If none of the above, redirect with error
        return redirect()->route('dashboard')->with('error', 'Unauthorized access. Admin only.');
    }
}
