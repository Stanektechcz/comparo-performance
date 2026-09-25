<?php

namespace App\Domain\Platform\Markets;

/**
 * The destination market of the current request: decides shipping,
 * compliance and display currency. Not the UI language.
 */
final readonly class MarketContext
{
    public function __construct(
        public ?int $countryId,
        public string $code,
        public string $name,
        public string $currency,
        public string $locale,
    ) {}

    /**
     * @return array{code: string, name: string, currency: string, locale: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'currency' => $this->currency, 'locale' => $this->locale];
    }
}
