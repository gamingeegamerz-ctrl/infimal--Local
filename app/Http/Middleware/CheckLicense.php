<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class CheckLicense
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = auth()->id();
        
        // Check if user has active license
        $activeLicense = DB::table('licenses')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$activeLicense) {
            // Redirect to payment page if no active license
            return redirect()->route('payment')
                ->with('error', 'Please purchase a license to access this feature.');
        }
        
        return $next($request);
    }
}
