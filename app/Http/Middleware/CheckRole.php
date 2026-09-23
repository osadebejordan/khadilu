<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (empty($roles) || in_array($user->role, $roles)) {
            return $next($request);
        }

        return redirect()->route('dashboard')->with('error', 'Access denied. Administrator privileges required.');
    }
}
