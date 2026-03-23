<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureInfimalAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->hasPaid()) {
            return redirect()->route('billing');
        }

        if (empty($user->license_key) && !$user->license?->is_active) {
            return redirect()->route('billing')->with('error', 'Active license required.');
        }

        return $next($request);
    }
}
