<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckPaymentCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $recentPayment = DB::table('payments')
            ->where('user_id', auth()->id())
            ->where('status', 'completed')
            ->where('created_at', '>', now()->subHours(24))
            ->exists();

        if (!$recentPayment) {
            return redirect()->route('payment.success')
                ->with('error', 'Please complete your payment first.');
        }

        return $next($request);
    }
}
