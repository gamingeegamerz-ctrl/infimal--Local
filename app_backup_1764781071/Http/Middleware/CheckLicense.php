<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        $allowedWithoutLicense = [
            'logout','payment.page','payment.process','payment.success',
            'payment.cancel','verify.license','verify.license.post',
            'login','register','password.request','password.email',
            'password.reset','password.update','google.login','google.callback'
        ];
        
        $currentRoute = $request->route()->getName();
        
        if (in_array($currentRoute, $allowedWithoutLicense)) {
            return $next($request);
        }
        
        if (!$user->license_key || $user->license_status !== 'active') {
            if ($user->license_key && $user->license_status === 'pending') {
                return redirect()->route('verify.license');
            }
            return redirect()->route('payment.page');
        }
        
        return $next($request);
    }
}
