<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InactivityTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = session('last_activity');
            $timeoutDuration = 1800; // 30 minutes

            if ($lastActivity && (time() - $lastActivity > $timeoutDuration)) {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();

                return redirect()->route('login')->with('error', 'Session expired due to inactivity. Please log in again.');
            }

            session(['last_activity' => time()]);
        }

        return $next($request);
    }
}
