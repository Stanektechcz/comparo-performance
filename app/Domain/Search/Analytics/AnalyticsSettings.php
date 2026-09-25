<?php

namespace App\Domain\Search\Analytics;

use InvalidArgumentException;

/**
 * Search-analytics retention and attribution settings (A-24, D-09 defaults).
 * Read from `comparo.search.analytics.*`; the defaults below apply until
 * those keys exist.
 */
final readonly class AnalyticsSettings
{
    public const int SESSION_HASH_DAYS = 90;

    public const int RAW_RETENTION_MONTHS = 13;

    public const int CLICK_WINDOW_MINUTES = 30;

    public const int MIN_DEMAND_SESSIONS = 3;

    public function __construct(
        public int $sessionHashDays = self::SESSION_HASH_DAYS,
        public int $rawRetentionMonths = self::RAW_RETENTION_MONTHS,
        public int $clickWindowMinutes = self::CLICK_WINDOW_MINUTES,
        public int $minDemandSessions = self::MIN_DEMAND_SESSIONS,
    ) {
        if ($sessionHashDays < 1 || $rawRetentionMonths < 1 || $clickWindowMinutes < 1 || $minDemandSessions < 1) {
            throw new InvalidArgumentException('Search analytics periods and thresholds must be positive.');
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            sessionHashDays: (int) config('comparo.search.analytics.session_hash_days', self::SESSION_HASH_DAYS),
            rawRetentionMonths: (int) config('comparo.search.analytics.raw_retention_months', self::RAW_RETENTION_MONTHS),
            clickWindowMinutes: (int) config('comparo.search.analytics.click_window_minutes', self::CLICK_WINDOW_MINUTES),
            minDemandSessions: (int) config('comparo.search.analytics.min_demand_sessions', self::MIN_DEMAND_SESSIONS),
        );
    }
}
