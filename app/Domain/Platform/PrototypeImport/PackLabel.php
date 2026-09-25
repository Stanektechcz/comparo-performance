<?php

namespace App\Domain\Platform\PrototypeImport;

/**
 * Parses a prototype pack label: "360 g" → 360 / g, "180 caps" → 180 / caps, "1.2 kg" → 1.2 / kg.
 */
final readonly class PackLabel
{
    private function __construct(
        public ?float $quantity,
        public ?string $unit,
    ) {}

    public static function parse(?string $label): self
    {
        if ($label === null || preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*([\p{L}]+)\s*$/u', $label, $matches) !== 1) {
            return new self(null, null);
        }

        return new self((float) str_replace(',', '.', $matches[1]), mb_strtolower($matches[2]));
    }
}
