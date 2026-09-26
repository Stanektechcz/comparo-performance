<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * M-1: baseline security headers for the web and API groups.
 *
 * The CSP is a safe baseline without script-src (a nonce-based policy is a
 * later backlog item: the Vite, SSR and inline appearance scripts must keep
 * working). A header the response already set is never overwritten.
 */
class SecurityHeaders
{
    /**
     * @var array<string, string>
     */
    public const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Content-Security-Policy' => "frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
    ];

    public const string HSTS = 'max-age=31536000; includeSubDomains';

    public const string NO_INDEX = 'noindex, nofollow';

    public function __construct(private readonly Application $app) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headersFor($request) as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * HSTS only over HTTPS on the hosted environments; everything except
     * production (staging, demo, local) is kept out of search indexes.
     *
     * @return array<string, string>
     */
    private function headersFor(Request $request): array
    {
        $headers = self::HEADERS;

        if ($request->isSecure() && $this->app->environment('production', 'staging')) {
            $headers['Strict-Transport-Security'] = self::HSTS;
        }

        if (! $this->app->isProduction()) {
            $headers['X-Robots-Tag'] = self::NO_INDEX;
        }

        return $headers;
    }
}
