<?php

namespace App\Domain\Feeds\Pipeline;

use App\Domain\Feeds\FeedItemMatchStatus;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\Normalisation\NormalisedFeedItem;
use App\Domain\Feeds\Parsing\RawFeedRow;
use App\Domain\Feeds\Validation\FeedIssue;
use App\Domain\Feeds\Validation\RowRejection;
use App\Models\FeedError;
use App\Models\FeedItem;
use Illuminate\Support\Facades\Date;

/**
 * Buffers staged rows of one run and writes them in chunks: feed_items
 * (valid / warning / invalid, normalised columns, raw payload only for
 * invalid and warning rows) and their feed_errors, capped per code
 * (`comparo.feeds.max_errors_per_code`) while the metrics keep true totals.
 *
 * `feed_items.row_number` is the record's ordinal (unique per run even when
 * several XML records share a physical line); `feed_errors.row_number` is the
 * source line the merchant can look up.
 */
final class FeedItemWriter
{
    public const int CHUNK = 500;

    /** Staging column widths (2026_09_25_101100_create_feed_item_tables). */
    private const array WIDTHS = [
        'merchant_sku' => 128, 'external_id' => 191, 'title' => 512, 'brand_raw' => 128, 'pack_raw' => 64,
        'variant_raw' => 128, 'category_raw' => 255, 'url' => 2048, 'image_url' => 2048,
    ];

    private const int MAX_EAN_LENGTH = 14;

    /** @var list<array<string, mixed>> */
    private array $items = [];

    /** @var array<int, list<FeedIssue>> ordinal => issues */
    private array $issues = [];

    /** @var array<int, int> ordinal => source line */
    private array $lines = [];

    /** @var array<string, int> code => stored error rows */
    private array $storedPerCode = [];

    /** @var array{rows_read: int, rows_valid: int, rows_invalid: int, warnings: int, errors: int} */
    private array $metrics = ['rows_read' => 0, 'rows_valid' => 0, 'rows_invalid' => 0, 'warnings' => 0, 'errors' => 0];

    public function __construct(
        private readonly int $runId,
        private readonly int $merchantId,
        private readonly int $maxErrorsPerCode,
    ) {}

    public function add(int $ordinal, RawFeedRow $row, NormalisedFeedItem|RowRejection $result): void
    {
        $this->metrics['rows_read']++;
        $this->lines[$ordinal] = $row->lineNumber;

        if ($result instanceof RowRejection) {
            $this->metrics['rows_invalid']++;
            $this->metrics['errors'] += count($result->issues);
            $this->issues[$ordinal] = $result->issues;
            $this->items[] = $this->rejectedRow($ordinal, $row, $result);
        } else {
            $this->metrics['rows_valid']++;
            $this->metrics['warnings'] += count($result->warnings);
            $this->issues[$ordinal] = $result->warnings;
            $this->items[] = $this->validRow($ordinal, $row, $result);
        }

        if (count($this->items) >= self::CHUNK) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->items === []) {
            return;
        }

        $firstOrdinal = array_key_first($this->issues);
        $lastOrdinal = array_key_last($this->issues);

        if ($firstOrdinal === null || $lastOrdinal === null) {
            return;
        }

        FeedItem::query()->insert($this->items);
        $itemIds = FeedItem::query()
            ->where('feed_run_id', $this->runId)
            ->whereBetween('row_number', [$firstOrdinal, $lastOrdinal])
            ->pluck('id', 'row_number');

        $errors = [];
        $now = Date::now();

        foreach ($this->issues as $ordinal => $issues) {
            foreach ($issues as $issue) {
                if (! $this->withinCap($issue)) {
                    continue;
                }

                $errors[] = [
                    'feed_run_id' => $this->runId,
                    'merchant_id' => $this->merchantId,
                    'feed_item_id' => $itemIds[$ordinal] ?? null,
                    'row_number' => $this->lines[$ordinal],
                    'code' => $issue->code->value,
                    'severity' => $issue->severity()->value,
                    'field' => $issue->field?->value,
                    'message_params' => $issue->params === [] ? null : json_encode($issue->params, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ];
            }
        }

        foreach (array_chunk($errors, self::CHUNK) as $chunk) {
            FeedError::query()->insert($chunk);
        }

        $this->items = [];
        $this->issues = [];
        $this->lines = [];
    }

    /**
     * @return array{rows_read: int, rows_valid: int, rows_invalid: int, warnings: int, errors: int}
     */
    public function metrics(): array
    {
        return $this->metrics;
    }

    private function withinCap(FeedIssue $issue): bool
    {
        $code = $issue->code->value;
        $this->storedPerCode[$code] = ($this->storedPerCode[$code] ?? 0) + 1;

        return $this->storedPerCode[$code] <= $this->maxErrorsPerCode;
    }

    /**
     * @return array<string, mixed>
     */
    private function validRow(int $ordinal, RawFeedRow $row, NormalisedFeedItem $item): array
    {
        $status = $item->hasWarnings() ? FeedItemValidationStatus::Warning : FeedItemValidationStatus::Valid;

        return $this->base($ordinal, $status, FeedItemMatchStatus::Pending, [
            'merchant_sku' => $item->merchantSku,
            'external_id' => $item->externalId,
            'ean' => $item->gtin !== null && strlen($item->gtin) <= self::MAX_EAN_LENGTH ? $item->gtin : null,
            'title' => $item->title,
            'brand_raw' => $item->brandRaw,
            'pack_raw' => $item->packRaw,
            'variant_raw' => $item->variantRaw,
            'category_raw' => $item->categoryRaw,
            'price_minor' => $item->price->minor,
            'reference_price_minor' => $item->referencePrice?->minor,
            'currency' => $item->currency,
            'availability' => $item->availability->value,
            'stock_quantity' => $item->stockQuantity,
            'url' => $item->productUrl,
            'image_url' => $item->imageUrl,
            'content_hash' => $item->contentHash,
            'raw_payload' => $item->hasWarnings() ? $this->rawPayload($row) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rejectedRow(int $ordinal, RawFeedRow $row, RowRejection $rejection): array
    {
        return $this->base($ordinal, FeedItemValidationStatus::Invalid, FeedItemMatchStatus::Skipped, [
            'merchant_sku' => $rejection->merchantSku,
            'raw_payload' => $this->rawPayload($row),
        ]);
    }

    /**
     * @param  array<string, mixed>  $columns
     * @return array<string, mixed>
     */
    private function base(int $ordinal, FeedItemValidationStatus $validation, FeedItemMatchStatus $match, array $columns): array
    {
        $now = Date::now();

        foreach (self::WIDTHS as $column => $width) {
            if (isset($columns[$column]) && is_string($columns[$column])) {
                $columns[$column] = mb_substr($columns[$column], 0, $width);
            }
        }

        return [
            'feed_run_id' => $this->runId,
            'merchant_id' => $this->merchantId,
            'row_number' => $ordinal,
            'merchant_sku' => null,
            'external_id' => null,
            'ean' => null,
            'title' => null,
            'brand_raw' => null,
            'pack_raw' => null,
            'variant_raw' => null,
            'category_raw' => null,
            'price_minor' => null,
            'reference_price_minor' => null,
            'currency' => null,
            'availability' => null,
            'stock_quantity' => null,
            'url' => null,
            'image_url' => null,
            'content_hash' => null,
            'raw_payload' => null,
            ...$columns,
            'validation_status' => $validation->value,
            'match_status' => $match->value,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function rawPayload(RawFeedRow $row): string
    {
        return json_encode($row->fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
