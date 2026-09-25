<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation id.
 *
 * The id is stored in Laravel's Context, so it is attached to every log
 * record and automatically propagated into queued jobs dispatched while
 * handling the request (and further down the job chain).
 */
class AssignCorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->incomingId($request) ?? (string) Str::uuid7();

        Context::add('correlation_id', $correlationId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }

    /**
     * Accept a caller-supplied id only when it is a well-formed UUID, so the
     * header cannot be used to inject arbitrary content into logs.
     */
    private function incomingId(Request $request): ?string
    {
        $incoming = $request->headers->get(self::HEADER);

        return is_string($incoming) && Str::isUuid($incoming) ? strtolower($incoming) : null;
    }
}
