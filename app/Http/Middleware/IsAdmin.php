<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\AdminCheck;   // <-- use the trait

class IsAdmin
{
    use AdminCheck;

    public function handle(Request $request, Closure $next)
    {
        if (!$this->isAdmin(auth()->user())) {
            abort(403, 'Admin access required.');
        }
        return $next($request);
    }
}
