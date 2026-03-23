<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = auth()->id();

        $activeLicense = DB::table('licenses')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (!$activeLicense) {
            return redirect()->route('payment')
                ->with('error', 'Please complete payment to activate your license.');
        }

        return $next($request);
    }
}
