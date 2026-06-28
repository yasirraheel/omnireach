<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class RedirectIfNotAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        $prefix = explode('/', $request->path())[0];

        if ($prefix === 'admin' && !Auth::guard('admin')->check()) {
            return redirect()->guest(route('admin.login'));
        }

        if ($prefix === 'user' && !Auth::guard('web')->check()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}