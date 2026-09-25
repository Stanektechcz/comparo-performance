<?php

namespace App\Domain\Platform\Geography\Queries;

use App\Models\Country;
use App\Models\Currency;

/**
 * Public geography reference data (currencies, active markets) for forms.
 */
final class GeographyReference
{
    /**
     * Every currency, by ISO code.
     *
     * @return list<array{code: string, name: string}>
     */
    public function currencies(): array
    {
        return array_values(Currency::query()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(static fn (Currency $currency): array => ['code' => $currency->code, 'name' => $currency->name])
            ->all());
    }

    /**
     * Active markets (countries), by name then code.
     *
     * @return list<array{code: string, name: string}>
     */
    public function activeCountries(): array
    {
        return array_values(Country::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(static fn (Country $country): array => ['code' => $country->code, 'name' => $country->name])
            ->all());
    }
}
