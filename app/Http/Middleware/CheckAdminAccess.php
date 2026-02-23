<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $adminEmails = [
            'admin@infimal.site',
            'contact@infimal.site',
            'sainikhilsaini143@gmail.com',
            'khileshrathod1729@gmail.com',
            'admin@infimal.com'
        ];

        if (in_array($user->email, $adminEmails) || 
            (isset($user->is_admin) && $user->is_admin) ||
            (isset($user->role) && in_array($user->role, ['admin', 'super_admin']))) {
            return $next($request);
        }

        abort(403, 'Admin access required.');
    }
}
