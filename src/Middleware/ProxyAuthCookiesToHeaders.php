<?php


namespace Laravel\Foundation\Middleware;

use Closure;

class ProxyAuthCookiesToHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure                  $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->cookies->has('access_token') && !$request->headers->has('authorization')) {
            $request->headers->set('Authorization', 'Bearer ' . $request->cookie('access_token'));
        }
        if ($request->cookies->has('refresh_token') && !$request->headers->has('Authorization-Refresh')) {
            $request->headers->set('Authorization-Refresh', $request->cookie('refresh_token'));
        }

        return $next($request);
    }
}
