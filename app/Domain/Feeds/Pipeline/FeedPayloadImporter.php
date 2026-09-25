<?php

namespace App\Domain\Feeds\Pipeline;

use App\Domain\Feeds\Exceptions\FeedRunFailure;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Mapping\FieldMapping;
use App\Domain\Feeds\Normalisation\FeedRowMapper;
use App\Domain\Feeds\Normalisation\NormalisedFeedItem;
use App\Domain\Feeds\Parsing\FeedParseException;
use App\Domain\Feeds\Parsing\FeedParserFactory;
use App\Domain\Feeds\Validation\DuplicateSkuTracker;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedRun;
use App\Models\FeedSource;

/**
 * Parse + normalise + validate stage of a run: streams the stored payload,
 * maps every record onto the feed contract and stages it (§5).
 *
 * Re-runnable: the run's staging rows are deleted first. Run-fatal problems
 * throw {@see FeedRunFailure} / {@see FeedParseException} (EMPTY_FEED,
 * ROW_LIMIT_EXCEEDED, PARSER_ERROR, MISSING_REQUIRED_FIELD for an unusable
 * mapping, …); the reject threshold is judged by the caller from the metrics.
 */
final class FeedPayloadImporter
{
    public function __construct(
        private readonly FeedStorage $storage,
        private readonly FeedSourceSettings $settings,
        private readonly MappingResolver $mappings,
        private readonly FeedRowMapper $mapper = new FeedRowMapper,
    ) {}

    /**
     * @return array{rows_read: int, rows_valid: int, rows_invalid: int, warnings: int, errors: int}
     *
     * @throws FeedRunFailure
     * @throws FeedParseException
     */
    public function import(FeedRun $run, FeedSource $source): array
    {
        if ($run->payload_path === null || ! $this->storage->exists($run->payload_path)) {
            throw new FeedRunFailure(FeedErrorCode::InternalError);
        }

        FeedError::query()->where('feed_run_id', $run->id)->delete();
        FeedItem::query()->where('feed_run_id', $run->id)->delete();

        $options = $this->settings->parseOptions($source);
        $context = $this->settings->normalisationContext($source);
        $pinned = $this->mappings->load($run->feed_mapping_id);
        $writer = new FeedItemWriter($run->id, $run->merchant_id, max(1, (int) config('comparo.feeds.max_errors_per_code', 1000)));
        $duplicates = new DuplicateSkuTracker;
        $mapping = null;
        $ordinal = 0;

        foreach (FeedParserFactory::for($source->format)->rows($this->storage->absolutePath($run->payload_path), $options) as $row) {
            $mapping ??= $this->usableMapping($this->mappings->resolve($pinned, array_map(strval(...), array_keys($row->fields))));
            $result = $this->mapper->map($row, $mapping, $context);

            if ($result instanceof NormalisedFeedItem) {
                $result = $duplicates->track($result);
            }

            $writer->add(++$ordinal, $row, $result);
        }

        $writer->flush();

        return $writer->metrics();
    }

    /**
     * Whether the rejected share of the rows exceeds `comparo.feeds.max_rejected_ratio` (A-09).
     *
     * @param  array{rows_read: int, rows_invalid: int}  $metrics
     */
    public static function exceedsRejectThreshold(array $metrics, float $maxRejectedRatio): bool
    {
        return $metrics['rows_read'] > 0 && $maxRejectedRatio < $metrics['rows_invalid'] / $metrics['rows_read'];
    }

    /**
     * @throws FeedRunFailure MISSING_REQUIRED_FIELD when a required field has no source
     */
    private function usableMapping(FieldMapping $mapping): FieldMapping
    {
        // The source always has a default currency (feed_sources.currency is required).
        $missing = $mapping->missingRequired(hasDefaultCurrency: true);

        if ($missing !== []) {
            throw new FeedRunFailure(FeedErrorCode::MissingRequiredField, ['field' => $missing[0]->value]);
        }

        return $mapping;
    }
}
