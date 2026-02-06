<?php

namespace App\Http\Middleware;

use Closure;

class PowerQueryAuth
{
    public function handle($request, Closure $next)
    {
        if ($request->header('X-API-KEY') !== config('services.power_query.token')) {
            return response()->json(['Unauthorized'], 401);
        }

        return $next($request);
    }
}

