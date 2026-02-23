<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureInfimalAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user->hasPaid()) {
            return redirect()->route('payment.checkout');
        }

        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (empty($user->license_key)) {
            abort(403, 'License key missing');
        }

        return $next($request);
    }
}
