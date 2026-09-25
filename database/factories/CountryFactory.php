<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Countries cycle through real ISO 3166-1 codes so consecutive rows get unique
 * codes; the country's currency row is created on first use.
 *
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * code => [name, currency, locale, region, is_eu, standard VAT %]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: int|float}>
     */
    public const array MARKETS = [
        'DE' => ['Germany', 'EUR', 'de', 'DACH', true, 19],
        'AT' => ['Austria', 'EUR', 'de', 'DACH', true, 20],
        'FR' => ['France', 'EUR', 'fr', 'Western Europe', true, 20],
        'NL' => ['Netherlands', 'EUR', 'nl', 'Western Europe', true, 21],
        'BE' => ['Belgium', 'EUR', 'nl', 'Western Europe', true, 21],
        'IT' => ['Italy', 'EUR', 'it', 'Southern Europe', true, 22],
        'ES' => ['Spain', 'EUR', 'es', 'Southern Europe', true, 21],
        'PT' => ['Portugal', 'EUR', 'pt', 'Southern Europe', true, 23],
        'IE' => ['Ireland', 'EUR', 'en', 'Western Europe', true, 23],
        'FI' => ['Finland', 'EUR', 'fi', 'Nordics', true, 25.5],
        'SK' => ['Slovakia', 'EUR', 'sk', 'Central Europe', true, 20],
        'SI' => ['Slovenia', 'EUR', 'sl', 'Central Europe', true, 22],
        'GR' => ['Greece', 'EUR', 'el', 'Southern Europe', true, 24],
        'HR' => ['Croatia', 'EUR', 'hr', 'Southern Europe', true, 25],
        'LT' => ['Lithuania', 'EUR', 'lt', 'Baltics', true, 21],
        'LV' => ['Latvia', 'EUR', 'lv', 'Baltics', true, 21],
        'EE' => ['Estonia', 'EUR', 'et', 'Baltics', true, 22],
        'LU' => ['Luxembourg', 'EUR', 'fr', 'Western Europe', true, 17],
        'MT' => ['Malta', 'EUR', 'en', 'Southern Europe', true, 18],
        'CY' => ['Cyprus', 'EUR', 'el', 'Southern Europe', true, 19],
        'CZ' => ['Czechia', 'CZK', 'cs', 'Central Europe', true, 21],
        'PL' => ['Poland', 'PLN', 'pl', 'Central Europe', true, 23],
        'HU' => ['Hungary', 'HUF', 'hu', 'Central Europe', true, 27],
        'RO' => ['Romania', 'RON', 'ro', 'South-East Europe', true, 19],
        'BG' => ['Bulgaria', 'BGN', 'bg', 'South-East Europe', true, 20],
        'SE' => ['Sweden', 'SEK', 'sv', 'Nordics', true, 25],
        'DK' => ['Denmark', 'DKK', 'da', 'Nordics', true, 25],
        'NO' => ['Norway', 'NOK', 'nb', 'Nordics', false, 25],
        'CH' => ['Switzerland', 'CHF', 'de', 'DACH', false, 8.1],
        'GB' => ['United Kingdom', 'GBP', 'en', 'Western Europe', false, 20],
        'US' => ['United States', 'USD', 'en', 'North America', false, 0],
    ];

    private static int $nextIndex = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $codes = array_keys(self::MARKETS);
        $code = $codes[self::$nextIndex++ % count($codes)];

        return [
            'code' => $code,
            'currency_id' => fn (array $attributes): int => CurrencyFactory::resolveId(self::MARKETS[$attributes['code']][1] ?? 'EUR'),
            ...self::marketAttributes($code),
            'minimum_age' => 18,
            'customs_note' => null,
            'is_active' => true,
        ];
    }

    /**
     * A specific market by ISO code, with its real name, locale and currency.
     */
    public function code(string $code): static
    {
        $code = strtoupper($code);

        return $this->state(fn (array $attributes): array => ['code' => $code, ...self::marketAttributes($code)]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function marketAttributes(string $code): array
    {
        $market = self::MARKETS[$code] ?? [$code, 'EUR', 'en', null, false, null];

        return [
            'name' => $market[0],
            'default_locale' => $market[2],
            'region' => $market[3],
            'is_eu' => $market[4],
            'standard_vat_rate' => $market[5],
        ];
    }
}
