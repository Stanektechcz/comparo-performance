<?php

namespace App\Domain\Platform\Markets;

use App\Models\Country;

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
     * The market of a country (its currency relation is read; eager-load
     * `currency` when mapping several countries).
     */
    public static function fromCountry(Country $country): self
    {
        return new self(
            countryId: $country->id,
            code: $country->code,
            name: $country->name,
            currency: $country->currency->code,
            locale: $country->default_locale.'-'.$country->code,
        );
    }

    /**
     * @return array{code: string, name: string, currency: string, locale: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'currency' => $this->currency, 'locale' => $this->locale];
    }
}
