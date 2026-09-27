<?php

namespace App\Domain\Platform\PrototypeImport;

/**
 * The single gate for anything "demo": prototype data and demo personas are
 * only ever written to local, testing, dedicated demo or staging environments
 * (A-39: staging is production-like but may carry the labelled demo dataset,
 * opt-in via COMPARO_DEMO_ACCOUNTS). Production always refuses.
 */
final class DemoEnvironment
{
    /**
     * @var list<string>
     */
    public const array ALLOWED = ['local', 'testing', 'demo', 'staging'];

    public static function isAllowed(): bool
    {
        return app()->environment(self::ALLOWED);
    }

    /**
     * @throws DemoDataRefused
     */
    public static function assertAllowed(): void
    {
        if (! self::isAllowed()) {
            throw DemoDataRefused::inEnvironment((string) app()->environment());
        }
    }
}
