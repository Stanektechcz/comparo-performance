<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\FeedUrlMask;
use App\Models\Country;
use App\Models\Currency;
use InvalidArgumentException;

/**
 * Maps validated {@see FeedSourceData} onto `feed_sources` columns and builds
 * the audit view of those columns (URL masked, never credentials).
 *
 * @internal shared by CreateFeedSource and UpdateFeedSource
 */
final class FeedSourceAttributes
{
    /**
     * @return array<string, mixed>
     */
    public static function fromData(FeedSourceData $data): array
    {
        if (! Currency::query()->where('code', $data->currency)->exists()) {
            throw new InvalidArgumentException('The feed currency is not a supported currency.');
        }

        $countryId = null;

        if ($data->marketCountryCode !== null) {
            $countryId = Country::query()->where('code', $data->marketCountryCode)->value('id')
                ?? throw new InvalidArgumentException('The feed market is not a known market.');
        }

        return [
            'name' => $data->name,
            'format' => $data->format,
            'transport' => $data->transport,
            'url' => $data->url,
            'country_id' => $countryId,
            'currency' => $data->currency,
            'encoding' => $data->encoding,
            'delimiter' => $data->delimiter,
            'record_element' => $data->recordElement,
            'interval_minutes' => $data->intervalMinutes,
            'availability_map' => $data->availabilityMap === [] ? null : $data->availabilityMap,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function auditable(array $attributes): array
    {
        if (array_key_exists('url', $attributes)) {
            $attributes['url'] = FeedUrlMask::mask(is_string($attributes['url']) ? $attributes['url'] : null);
        }

        unset($attributes['credentials']);

        return array_map(
            static fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value,
            $attributes,
        );
    }
}
