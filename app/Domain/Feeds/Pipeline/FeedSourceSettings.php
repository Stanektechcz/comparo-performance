<?php

namespace App\Domain\Feeds\Pipeline;

use App\Domain\Feeds\Exceptions\FeedRunFailure;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Normalisation\NormalisationContext;
use App\Domain\Feeds\Parsing\FeedParseException;
use App\Domain\Feeds\Parsing\ParseOptions;
use App\Models\Currency;
use App\Models\FeedSource;
use App\Models\Merchant;
use InvalidArgumentException;

/**
 * Builds the pure parser/normaliser inputs from a feed source, its merchant
 * and the `currencies` table.
 */
final class FeedSourceSettings
{
    /**
     * @throws FeedParseException UNSUPPORTED_ENCODING
     */
    public function parseOptions(FeedSource $source, ?int $maxRows = null): ParseOptions
    {
        return new ParseOptions(
            format: $source->format,
            encoding: $source->encoding,
            delimiter: $source->delimiter,
            recordElement: $source->record_element,
            maxRows: $maxRows ?? max(1, (int) config('comparo.feeds.max_rows', ParseOptions::DEFAULT_MAX_ROWS)),
        );
    }

    /**
     * @throws FeedRunFailure INVALID_CURRENCY when the source currency is not a known currency
     */
    public function normalisationContext(FeedSource $source): NormalisationContext
    {
        $minorUnits = Currency::query()
            ->pluck('minor_unit', 'code')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
        $website = Merchant::query()->whereKey($source->merchant_id)->value('website');

        try {
            return new NormalisationContext(
                minorUnits: $minorUnits,
                defaultCurrency: $source->currency,
                availabilityMap: $source->availability_map ?? [],
                merchantDomain: is_string($website) ? $website : null,
            );
        } catch (InvalidArgumentException) {
            throw new FeedRunFailure(FeedErrorCode::InvalidCurrency, ['value' => $source->currency]);
        }
    }
}
