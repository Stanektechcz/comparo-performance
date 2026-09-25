<?php

namespace App\Domain\Feeds\Queries;

use App\Domain\Feeds\Exceptions\FeedRunFailure;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\Mapping\FieldMapping;
use App\Domain\Feeds\Normalisation\FeedRowMapper;
use App\Domain\Feeds\Normalisation\NormalisedFeedItem;
use App\Domain\Feeds\Parsing\FeedParseException;
use App\Domain\Feeds\Parsing\FeedParserFactory;
use App\Domain\Feeds\Parsing\RawFeedRow;
use App\Domain\Feeds\Pipeline\FeedSourceSettings;
use App\Domain\Feeds\Pipeline\FeedStorage;
use App\Domain\Feeds\Pipeline\MappingResolver;
use App\Domain\Feeds\Validation\DuplicateSkuTracker;
use App\Domain\Feeds\Validation\FeedIssue;
use App\Domain\Feeds\Validation\RowRejection;
use App\Models\FeedRun;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Parses the first rows of a stored payload for the mapping screen: headers,
 * the current (or suggested) mapping, required fields left unmapped and the
 * normalised preview rows with their issues. Reads either an uploaded sample
 * in the merchant's directory or the latest unpurged payload of the source;
 * it never fetches a URL synchronously.
 */
final class FeedSamplePreview
{
    public function __construct(
        private readonly MerchantFeedSources $sources,
        private readonly FeedStorage $storage,
        private readonly FeedSourceSettings $settings,
        private readonly MappingResolver $mappings,
        private readonly FeedRowMapper $mapper = new FeedRowMapper,
    ) {}

    /**
     * @param  FieldMapping|null  $mapping  a draft mapping to try; defaults to the current one, else a suggestion
     *
     * @throws ModelNotFoundException for a missing or foreign source id
     * @throws InvalidArgumentException for a sample path outside the merchant's directory
     */
    public function preview(int $merchantId, int $sourceId, ?string $samplePath = null, ?FieldMapping $mapping = null, ?int $limit = null): FeedPreview
    {
        $source = $this->sources->find($merchantId, $sourceId);
        $path = $this->payloadPath($source, $samplePath);

        if ($path === null) {
            return FeedPreview::unavailable();
        }

        $limit = max(1, $limit ?? (int) config('comparo.feeds.preview_rows', 20));

        try {
            $rows = $this->firstRows($source, $path, $limit);
            $context = $this->settings->normalisationContext($source);
        } catch (FeedParseException|FeedRunFailure $exception) {
            $failure = FeedRunFailure::from($exception);

            return new FeedPreview(true, errorCode: $failure->errorCode->value, errorParams: $failure->params, errorLine: $failure->lineNumber);
        }

        $headers = $this->headers($rows);
        $current = $mapping === null ? $this->mappings->current($source->id) : null;
        $suggested = $mapping === null && $current === null;
        $mapping ??= $this->mappings->resolve($current, $headers);
        $duplicates = new DuplicateSkuTracker;
        $previewRows = [];

        foreach ($rows as $row) {
            $result = $this->mapper->map($row, $mapping, $context);
            $previewRows[] = $this->previewRow($row, $result instanceof NormalisedFeedItem ? $duplicates->track($result) : $result);
        }

        return new FeedPreview(
            available: true,
            headers: $headers,
            mapping: $mapping->toArray(),
            mappingSuggested: $suggested,
            missingRequired: array_map(static fn ($field): string => $field->value, $mapping->missingRequired(hasDefaultCurrency: true)),
            rows: $previewRows,
        );
    }

    private function payloadPath(FeedSource $source, ?string $samplePath): ?string
    {
        if ($samplePath !== null) {
            if (! $this->storage->belongsToMerchant($samplePath, $source->merchant_id) || ! $this->storage->exists($samplePath)) {
                throw new InvalidArgumentException('The sample file is not available.');
            }

            return $samplePath;
        }

        $latest = FeedRun::query()
            ->where('feed_source_id', $source->id)
            ->whereNotNull('payload_path')
            ->whereNull('payload_purged_at')
            ->orderByDesc('id')
            ->value('payload_path');

        return is_string($latest) && $this->storage->exists($latest) ? $latest : null;
    }

    /**
     * @return list<RawFeedRow>
     */
    private function firstRows(FeedSource $source, string $path, int $limit): array
    {
        $rows = [];

        foreach (FeedParserFactory::for($source->format)->rows($this->storage->absolutePath($path), $this->settings->parseOptions($source)) as $row) {
            $rows[] = $row;

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  list<RawFeedRow>  $rows
     * @return list<string>
     */
    private function headers(array $rows): array
    {
        $headers = [];

        foreach ($rows as $row) {
            foreach (array_keys($row->fields) as $key) {
                $headers[(string) $key] = true;
            }
        }

        return array_map(strval(...), array_keys($headers));
    }

    private function previewRow(RawFeedRow $row, NormalisedFeedItem|RowRejection $result): FeedPreviewRow
    {
        if ($result instanceof RowRejection) {
            return new FeedPreviewRow($row->lineNumber, FeedItemValidationStatus::Invalid, $result->merchantSku, [], $this->issues($result->issues));
        }

        return new FeedPreviewRow(
            lineNumber: $row->lineNumber,
            status: $result->hasWarnings() ? FeedItemValidationStatus::Warning : FeedItemValidationStatus::Valid,
            merchantSku: $result->merchantSku,
            values: [
                ...$result->canonical(),
                'source_updated_at' => $result->sourceUpdatedAtIso(),
            ],
            issues: $this->issues($result->warnings),
        );
    }

    /**
     * @param  list<FeedIssue>  $issues
     * @return list<array{code: string, severity: string, field: string|null, params: array<string, string|int>}>
     */
    private function issues(array $issues): array
    {
        return array_map(static fn (FeedIssue $issue): array => [
            'code' => $issue->code->value,
            'severity' => $issue->severity()->value,
            'field' => $issue->field?->value,
            'params' => $issue->params,
        ], $issues);
    }
}
