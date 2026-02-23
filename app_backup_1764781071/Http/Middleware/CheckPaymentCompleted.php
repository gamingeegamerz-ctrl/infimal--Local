<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckPaymentCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            // Check if payment is completed
            if ($user->payment_status !== 'completed') {
                return redirect()->route('payment.page')->with('error', 'Please complete your payment to access dashboard.');
            }
        }
        
        return $next($request);
    }
}
