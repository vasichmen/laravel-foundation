<?php


namespace Laravel\Foundation\Middleware;

/**
 * Установка app.timezone по заголовку X-Data-Timezone.
 */
class SetTimezone
{
    public function handle($request, $next)
    {
        if ($request->headers->has('x-data-timezone')) {
            config(['app.timezone' => $request->headers->get('x-data-timezone')]);
        }
        return $next($request);
    }
}
