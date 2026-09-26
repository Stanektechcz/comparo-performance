<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * M-3: Fortify throttles only login and two-factor. Its other credential
 * endpoints are registered by the package (their definitions cannot be
 * edited), so this web-group middleware applies a named rate limiter
 * (defined in FortifyServiceProvider) by route name.
 */
class ThrottleSensitiveAuthRoutes
{
    /**
     * Route name => named rate limiter.
     *
     * @var array<string, string>
     */
    public const array LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'forgot-password',
        'password.update' => 'reset-password',
        'password.confirm.store' => 'confirm-password',
    ];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $limiter = $route instanceof Route ? (self::LIMITERS[(string) $route->getName()] ?? null) : null;

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
