<?php

namespace App\Domain\Orders\Delivery;

use InvalidArgumentException;

/**
 * The numbers of HTML `deliveryStats` (16873-16894): the minimum delivered
 * sample (the prototype's `orderMeta.minSample`, default 8) and the
 * percentile reported next to the median.
 */
final readonly class DeliveryPolicy
{
    public function __construct(
        public int $minSample = 8,
        public float $percentile = 0.9,
    ) {
        if ($minSample < 1 || $percentile <= 0.0 || $percentile > 1.0) {
            throw new InvalidArgumentException('The minimum sample is at least 1 and the percentile is in (0, 1].');
        }
    }

    public static function prototype(): self
    {
        return new self;
    }

    /**
     * @param  array<string, int|float>  $overrides  constructor parameter => value
     */
    public function with(array $overrides): self
    {
        $values = get_object_vars($this);

        foreach ($overrides as $key => $value) {
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException("Unknown delivery policy value [{$key}].");
            }

            $values[$key] = $value;
        }

        return new self(...$values);
    }
}
