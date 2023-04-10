<?php


namespace Laravel\Foundation\Abstracts;

use Closure;
use Illuminate\Support\Str;

abstract class AbstractBasicAuthMiddleware
{
    protected string $key;

    protected string $header = 'authorization';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure                  $next
     * @return mixed
     */
    public final function handle($request, Closure $next)
    {
        $header = $request->header($this->header);
        if (!Str::startsWith($header, 'Basic ')) {
            return $this->generateError();
        }
        $encoded = Str::replaceFirst('Basic ', '', $header);
        $decoded = base64_decode($encoded, true);
        if (!$decoded) {
            return $this->generateError();
        }

        $decoded = explode(':', $decoded);
        if (count($decoded) !== 2 || empty($decoded[0]) || empty($decoded[1])) {
            return $this->generateError();
        }

        if ($this->getUserName() !== $decoded[0] || $this->getUserPassword() !== $decoded[1]) {
            return $this->generateError();
        }

        return $next($request);
    }

    protected function generateError(): mixed
    {
        return response('Unauthorized', 401);
    }

    protected function getUserName(): string
    {
        return config('auth.basic.' . $this->key . '.user');
    }

    protected function getUserPassword(): string
    {
        return config('auth.basic.' . $this->key . '.password');
    }
}