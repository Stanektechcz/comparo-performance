<?php

namespace App\Domain\Feeds;

/**
 * Feed URLs may carry access tokens in the query string or user info, so they
 * are only ever shown or audited masked: scheme, host, port and path, with any
 * query replaced by `?…`.
 */
final class FeedUrlMask
{
    public static function mask(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return '…';
        }

        return strtolower($parts['scheme']).'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '')
            .(isset($parts['query']) ? '?…' : '');
    }
}
