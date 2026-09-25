<?php

namespace App\Domain\Platform\PrototypeImport;

use RuntimeException;

/**
 * Raised when fictional prototype/demo data would be written to an
 * environment that must never contain it (production in particular).
 */
final class DemoDataRefused extends RuntimeException
{
    public static function inEnvironment(string $environment): self
    {
        return new self("Demo data is refused in the [{$environment}] environment. It may only be seeded in: ".implode(', ', DemoEnvironment::ALLOWED).'.');
    }
}
