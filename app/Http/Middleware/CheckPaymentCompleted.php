<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPaymentCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->hasPaid()) {
            return redirect()->route('payment')
                ->with('error', 'Please complete your payment first.');
        }

        return $next($request);
    }
}
