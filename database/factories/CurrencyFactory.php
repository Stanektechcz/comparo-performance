<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * @var array<string, array{name: string, symbol: string, minor_unit: int}>
     */
    public const array KNOWN = [
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'minor_unit' => 2],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2],
        'GBP' => ['name' => 'Pound Sterling', 'symbol' => '£', 'minor_unit' => 2],
        'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč', 'minor_unit' => 2],
        'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł', 'minor_unit' => 2],
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr', 'minor_unit' => 2],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'minor_unit' => 2],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'minor_unit' => 2],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF', 'minor_unit' => 2],
        'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft', 'minor_unit' => 2],
        'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei', 'minor_unit' => 2],
        'BGN' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв', 'minor_unit' => 2],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'minor_unit' => 0],
    ];

    /**
     * Define the model's default state (EUR).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => 'EUR', ...self::KNOWN['EUR']];
    }

    public function code(string $code): static
    {
        $code = strtoupper($code);

        return $this->state(fn (array $attributes): array => ['code' => $code, ...self::attributesFor($code)]);
    }

    /**
     * The existing currency row for a code, created on first use (codes are unique).
     */
    public static function resolveId(string $code): int
    {
        $code = strtoupper($code);

        return Currency::query()->firstOrCreate(['code' => $code], self::attributesFor($code))->id;
    }

    /**
     * @return array{name: string, symbol: string, minor_unit: int}
     */
    private static function attributesFor(string $code): array
    {
        return self::KNOWN[$code] ?? ['name' => $code, 'symbol' => $code, 'minor_unit' => 2];
    }
}
