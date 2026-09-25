<?php

namespace App\Domain\Search\Analytics;

/**
 * Conservative user-agent classification for search analytics: a request is
 * flagged `is_bot` when it has no user agent or the agent names a crawler,
 * an HTTP library, a headless browser, a link-preview fetcher or a
 * monitoring probe. Flagged rows are still stored (for volume monitoring)
 * but never count as demand. Pure.
 */
final class BotDetector
{
    /**
     * Case-insensitive substrings; each is specific enough not to match the
     * user agent of a mainstream browser.
     */
    public const array PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'scrap', 'fetcher', 'archiver',
        'facebookexternalhit', 'embedly', 'preview', 'mediapartners', 'lighthouse',
        'pagespeed', 'headlesschrome', 'phantomjs', 'puppeteer', 'playwright',
        'selenium', 'curl/', 'wget/', 'python-requests', 'python-urllib',
        'aiohttp', 'httpx', 'go-http-client', 'java/', 'okhttp', 'libwww',
        'guzzlehttp', 'axios/', 'node-fetch', 'postmanruntime', 'insomnia',
        'httpie', 'uptime', 'pingdom', 'statuscake', 'monitor', 'nagios',
        'check_http', 'zgrab', 'masscan', 'nmap',
    ];

    public function isBot(?string $userAgent): bool
    {
        $agent = strtolower(trim((string) $userAgent));

        if ($agent === '') {
            return true;
        }

        foreach (self::PATTERNS as $pattern) {
            if (str_contains($agent, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
