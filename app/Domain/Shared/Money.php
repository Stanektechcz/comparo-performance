<?php

namespace App\Domain\Shared;

use InvalidArgumentException;
use JsonSerializable;

/**
 * An immutable amount of money in integer minor units (e.g. cents).
 *
 * Floating point is never used for stored or computed money. Formatting is
 * a presentation concern and deliberately not part of this class
 * (see docs/adr/0009-money-minor-units.md).
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency code [{$currency}].");
        }
    }

    public static function of(int $minor, string $currency): self
    {
        return new self($minor, $currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /**
     * Clamp at zero: a landed total can never become negative.
     */
    public function nonNegative(): self
    {
        return $this->minor < 0 ? self::zero($this->currency) : $this;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor >= $other->minor;
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor < $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    /**
     * @return array{minor: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch [{$this->currency}] vs [{$other->currency}]; convert explicitly first."
            );
        }
    }
}
