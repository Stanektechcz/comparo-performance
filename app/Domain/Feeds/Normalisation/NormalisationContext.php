<?php

namespace App\Domain\Feeds\Normalisation;

use App\Domain\Offers\Availability;
use InvalidArgumentException;

/**
 * Everything the pure row mapper needs besides the row itself. Built by the
 * pipeline from the feed source, the merchant and the `currencies` table.
 */
final readonly class NormalisationContext
{
    public const float DEFAULT_MAX_DISCOUNT_RATIO = 0.9;

    /** @var array<string, int> ISO-4217 code => minor unit digits */
    public array $minorUnits;

    /** @var array<string, Availability> normalised source value => availability */
    public array $availabilityMap;

    public ?string $defaultCurrency;

    public ?string $merchantDomain;

    /** Discount threshold in basis points (0.9 => 9000), so comparisons stay integer. */
    public int $maxDiscountBasisPoints;

    /**
     * @param  array<string, int>  $minorUnits  known currencies => minor unit digits (0–4)
     * @param  array<string, Availability|string>  $availabilityMap  merchant overrides (source value => availability)
     */
    public function __construct(
        array $minorUnits,
        ?string $defaultCurrency = null,
        array $availabilityMap = [],
        ?string $merchantDomain = null,
        public float $maxDiscountRatio = self::DEFAULT_MAX_DISCOUNT_RATIO,
    ) {
        $units = [];

        foreach ($minorUnits as $code => $digits) {
            if (preg_match('/^[A-Z]{3}$/', $code) !== 1 || $digits < 0 || $digits > 4) {
                throw new InvalidArgumentException("Invalid currency minor unit [{$code}].");
            }

            $units[$code] = $digits;
        }

        $default = $defaultCurrency !== null ? strtoupper(trim($defaultCurrency)) : null;

        if ($default !== null && ! isset($units[$default])) {
            throw new InvalidArgumentException("The default currency [{$default}] is not a known currency.");
        }

        if ($maxDiscountRatio <= 0.0 || $maxDiscountRatio >= 1.0) {
            throw new InvalidArgumentException('The maximum discount ratio must be between 0 and 1.');
        }

        $this->minorUnits = $units;
        $this->defaultCurrency = $default === '' ? null : $default;
        $this->availabilityMap = AvailabilityNormaliser::normaliseMap($availabilityMap);
        $this->merchantDomain = self::normaliseDomain($merchantDomain);
        $this->maxDiscountBasisPoints = (int) round($maxDiscountRatio * 10_000);
    }

    public function isKnownCurrency(string $code): bool
    {
        return isset($this->minorUnits[$code]);
    }

    public function minorUnitsFor(string $code): int
    {
        return $this->minorUnits[$code] ?? throw new InvalidArgumentException("Unknown currency [{$code}].");
    }

    private static function normaliseDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $host = strtolower(trim($domain));
        $host = (string) preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $host);
        $host = explode('/', $host, 2)[0];
        $host = rtrim((string) preg_replace('/^www\./', '', $host), '.');

        return $host === '' ? null : $host;
    }
}
