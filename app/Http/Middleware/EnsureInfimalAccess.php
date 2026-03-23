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
            return redirect()->route('payment');
        }

        if (empty($user->license_key)) {
            return redirect()->route('billing')
                ->with('error', 'License key missing.');
        }

        return $next($request);
    }
}
