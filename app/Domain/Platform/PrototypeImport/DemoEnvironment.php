<?php

namespace App\Domain\Platform\PrototypeImport;

/**
 * The single gate for anything "demo": prototype data and demo personas are
 * only ever written to local, testing or dedicated demo environments.
 */
final class DemoEnvironment
{
    /**
     * @var list<string>
     */
    public const array ALLOWED = ['local', 'testing', 'demo'];

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
