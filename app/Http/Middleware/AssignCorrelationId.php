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
 *
 * The client IP and user agent are stored as *hidden* Context values: they
 * reach the audit log (AuditLogger) but never the log records. Note: this
 * middleware is prepended to the global stack, so if trusted proxies are
 * configured later it must run after TrustProxies to see the real client IP.
 */
class AssignCorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    /** Column sizes of audit_logs.ip_address / audit_logs.user_agent. */
    private const IP_ADDRESS_MAX = 45;

    private const USER_AGENT_MAX = 512;

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->incomingId($request) ?? (string) Str::uuid7();

        Context::add('correlation_id', $correlationId);
        Context::addHidden('ip_address', $this->truncate($request->ip(), self::IP_ADDRESS_MAX));
        Context::addHidden('user_agent', $this->truncate($request->userAgent(), self::USER_AGENT_MAX));

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

    /**
     * Client-supplied text: scrub invalid UTF-8 and NUL bytes (PostgreSQL rejects both,
     * which would roll back the audited action) and cut to the column size.
     */
    private function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr(str_replace("\0", '', mb_scrub($value, 'UTF-8')), 0, $maxLength);
    }
}
