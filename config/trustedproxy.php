<?php

$trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Read by Illuminate\Http\Middleware\TrustProxies on every request. Only
    | requests whose REMOTE_ADDR matches one of these addresses may set the
    | client IP, scheme and host through X-Forwarded-* headers.
    |
    | TRUSTED_PROXIES: comma-separated IPs or CIDR ranges of the load
    | balancer / reverse proxy (e.g. "10.0.0.0/8,192.168.1.10"), or "*" to
    | trust the direct caller (only when the app is unreachable except
    | through the proxy). Unset means no proxy is trusted: an empty array,
    | not null, so the framework's host-based fallbacks (which trust every
    | proxy for *.on-forge.com / *.on-vapor.com hosts) never apply.
    |
    */

    'proxies' => $trustedProxies === '' ? [] : $trustedProxies,

];
