<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Paid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasPaid()) {
            return redirect()->route('payment')
                ->with('error', 'Payment required to access this feature.');
        }

        return $next($request);
    }
}
