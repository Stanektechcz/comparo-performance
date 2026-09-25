<?php

namespace App\Domain\Search\Analytics;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The pseudonymous session reference of search analytics (A-24):
 * HMAC-SHA256(session id, daily secret) where the daily secret is
 * HMAC-SHA256("comparo:search-session:" . UTC date, APP_KEY). The same
 * browser session yields the same hash for one UTC day only, so sessions
 * cannot be linked across days and the raw session id is never stored.
 * Pure: the application key and the instant are passed in.
 */
final readonly class SessionHasher
{
    private const string CONTEXT = 'comparo:search-session:';

    public function __construct(private string $applicationKey)
    {
        if ($applicationKey === '') {
            throw new InvalidArgumentException('Search session hashing needs the application key.');
        }
    }

    /**
     * Null when there is no session (API requests, cookie-less clients).
     */
    public function hash(?string $sessionId, DateTimeInterface $at): ?string
    {
        if ($sessionId === null || $sessionId === '') {
            return null;
        }

        return hash_hmac('sha256', $sessionId, $this->dailySecret($at));
    }

    private function dailySecret(DateTimeInterface $at): string
    {
        $day = (new DateTimeImmutable('@'.$at->getTimestamp()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');

        return hash_hmac('sha256', self::CONTEXT.$day, $this->applicationKey);
    }
}
