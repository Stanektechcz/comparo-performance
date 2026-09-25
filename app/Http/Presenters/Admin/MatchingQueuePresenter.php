<?php

namespace App\Http\Presenters\Admin;

use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\Queries\CandidateEntry;
use App\Domain\Matching\Queries\ConflictEntry;
use App\Domain\Matching\Queries\DecisionHistoryEntry;
use App\Domain\Matching\Queries\QueueListing;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Rows of the four staff matching queues. Explicit whitelists only: no feed
 * source data, no raw payloads, no credentials.
 */
final class MatchingQueuePresenter
{
    /** Titles of a proposal shown in the queue row. */
    private const int EVIDENCE_TITLES_MAX = 5;

    public function __construct(
        private readonly MatchingFormat $format,
        private readonly ReferenceNames $names,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, QueueListing>  $page
     * @return array<string, mixed>
     */
    public function listings(LengthAwarePaginator $page): array
    {
        /** @var list<QueueListing> $items */
        $items = $page->items();
        $merchants = $this->names->merchants(array_map(static fn (QueueListing $item): int => $item->merchantId, $items));

        return MatchingFormat::paginated($page, fn (QueueListing $item): array => [
            'id' => $item->listingId,
            'merchant' => ['id' => $item->merchantId, 'name' => $merchants[$item->merchantId] ?? "Merchant #{$item->merchantId}"],
            'sku' => $item->merchantSku,
            'title' => $item->title,
            'ean' => $item->ean,
            'brandRaw' => $item->brandRaw,
            'packRaw' => $item->packRaw,
            'variantRaw' => $item->variantRaw,
            'status' => MatchingFormat::status($item->status),
            'score' => $item->score,
            'level' => $this->format->levelFor($item->score),
            'isLinked' => $item->linkedProductId !== null,
            'product' => MatchingFormat::product($item->product),
            'decision' => $item->decisionKind === null ? null : [
                'id' => $item->decisionId,
                'kind' => MatchingFormat::decisionKind($item->decisionKind),
                'reason' => $item->decisionReason,
                'decidedAt' => MatchingFormat::date($item->decidedAt),
            ],
            'updatedAt' => MatchingFormat::date($item->updatedAt),
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, ConflictEntry>  $page
     * @return array<string, mixed>
     */
    public function conflicts(LengthAwarePaginator $page, bool $canResolveComplianceHolds): array
    {
        /** @var list<ConflictEntry> $items */
        $items = $page->items();
        $merchantIds = [];

        foreach ($items as $item) {
            foreach ($item->values as $value) {
                if ($value['merchant_id'] !== null) {
                    $merchantIds[] = $value['merchant_id'];
                }
            }
        }

        $merchants = $this->names->merchants($merchantIds);

        return MatchingFormat::paginated($page, static fn (ConflictEntry $item): array => [
            'id' => $item->id,
            'kind' => MatchingFormat::conflictKind($item->kind),
            'field' => $item->field,
            'product' => MatchingFormat::product($item->product),
            'createdAt' => MatchingFormat::date($item->createdAt),
            'canResolve' => $item->kind !== ConflictKind::ComplianceHold || $canResolveComplianceHolds,
            'values' => array_map(static fn (array $value): array => [
                'merchant' => $value['merchant_id'] === null ? null : [
                    'id' => $value['merchant_id'],
                    'name' => $merchants[$value['merchant_id']] ?? "Merchant #{$value['merchant_id']}",
                ],
                'listingId' => $value['merchant_product_id'],
                'sourceType' => $value['source_type'],
                'value' => $value['value'],
                'observedCount' => $value['observed_count'],
            ], $item->values),
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, CandidateEntry>  $page
     * @return array<string, mixed>
     */
    public function candidates(LengthAwarePaginator $page): array
    {
        return MatchingFormat::paginated($page, static fn (CandidateEntry $item): array => [
            'id' => $item->id,
            'proposedName' => $item->proposedName,
            'brandRaw' => $item->brandRaw,
            'ean' => $item->ean,
            'pack' => $item->packLabel,
            'sourceCount' => $item->sourceCount,
            'titles' => self::evidenceTitles($item->evidence),
            'createdAt' => MatchingFormat::date($item->createdAt),
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, DecisionHistoryEntry>  $page
     * @return array<string, mixed>
     */
    public function history(LengthAwarePaginator $page): array
    {
        /** @var list<DecisionHistoryEntry> $items */
        $items = $page->items();
        $merchants = $this->names->merchants(array_map(static fn (DecisionHistoryEntry $item): int => $item->merchantId, $items));
        $users = $this->names->users(array_values(array_filter(array_map(
            static fn (DecisionHistoryEntry $item): ?int => $item->decidedByUserId,
            $items,
        ), is_int(...))));

        return MatchingFormat::paginated($page, static fn (DecisionHistoryEntry $item): array => MatchingFormat::decision($item, $merchants, $users));
    }

    /**
     * @param  array<string, mixed>|null  $evidence
     * @return list<string>
     */
    private static function evidenceTitles(?array $evidence): array
    {
        $titles = $evidence['titles'] ?? [];

        if (! is_array($titles)) {
            return [];
        }

        return array_slice(array_values(array_filter($titles, is_string(...))), 0, self::EVIDENCE_TITLES_MAX);
    }
}
