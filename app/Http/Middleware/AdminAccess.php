<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check authentication
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        
        // Check admin status
        $isAdmin = false;
        
        // Method 1: Check is_admin column
        if (property_exists($user, 'is_admin') && $user->is_admin) {
            $isAdmin = true;
        }
        
        // Method 2: Check by email (fallback)
        $adminEmails = [
            'admin@infimal.site',
            'contact@infimal.site',
            // Add your email
            'your@email.com'
        ];
        
        if (in_array($user->email, $adminEmails)) {
            $isAdmin = true;
            // Update is_admin if column exists
            if (property_exists($user, 'is_admin')) {
                $user->is_admin = true;
                $user->save();
            }
        }
        
        if (!$isAdmin) {
            abort(403, 'Admin access required');
        }

        return $next($request);
    }
}
