<?php

namespace App\Http\Presenters\Admin;

use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\Engine\MatchingPolicy;
use App\Domain\Matching\Engine\MatchLevel;
use App\Domain\Matching\Exceptions\NoActiveMatchingPolicy;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\ActiveMatchingPolicy;
use App\Domain\Matching\Queries\DecisionHistoryEntry;
use App\Domain\Matching\Queries\ProductSummary;
use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared, whitelisted shapes of the staff matching console (camelCase for
 * Inertia). Labels are staff-facing English.
 */
final class MatchingFormat
{
    private ?MatchingPolicy $policy = null;

    private bool $policyResolved = false;

    public function __construct(private readonly ActiveMatchingPolicy $policies) {}

    /**
     * @return array{value: string, label: string}
     */
    public static function status(ListingMatchStatus $status): array
    {
        return ['value' => $status->value, 'label' => match ($status) {
            ListingMatchStatus::Unmatched => 'Unmatched',
            ListingMatchStatus::Suggested => 'Suggested',
            ListingMatchStatus::Auto => 'Auto-linked',
            ListingMatchStatus::Manual => 'Linked by a person',
            ListingMatchStatus::ComplianceHold => 'Compliance hold',
            ListingMatchStatus::Rejected => 'Rejected',
        }];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function statusOptions(): array
    {
        return array_map(self::status(...), ListingMatchStatus::cases());
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function decisionKind(MatchDecisionKind $kind): array
    {
        return ['value' => $kind->value, 'label' => match ($kind) {
            MatchDecisionKind::Auto => 'Auto-linked',
            MatchDecisionKind::Suggested => 'Suggested',
            MatchDecisionKind::Manual => 'Linked manually',
            MatchDecisionKind::Rejected => 'Rejected',
            MatchDecisionKind::Rematch => 'Relinked',
            MatchDecisionKind::Unlinked => 'Unlinked',
        }];
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function conflictKind(ConflictKind $kind): array
    {
        return ['value' => $kind->value, 'label' => match ($kind) {
            ConflictKind::FieldConflict => 'Field conflict',
            ConflictKind::ComplianceHold => 'Compliance hold',
            ConflictKind::MergeBlocked => 'Merge blocked',
        }];
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function level(MatchLevel $level): array
    {
        return ['value' => $level->value, 'label' => $level->label()];
    }

    /**
     * The confidence level of a stored score under the active policy (null
     * without a score or without an active policy).
     *
     * @return array{value: string, label: string}|null
     */
    public function levelFor(?int $score): ?array
    {
        $policy = $this->policy();

        return $score === null || $policy === null ? null : self::level($policy->levelFor($score));
    }

    /**
     * @return array{id: int, name: string, slug: string, brand: ?string, pack: string, ean: ?string, status: string}|null
     */
    public static function product(?ProductSummary $product): ?array
    {
        if ($product === null) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brandName,
            'pack' => $product->packLabel,
            'ean' => $product->ean,
            'status' => $product->status,
        ];
    }

    /**
     * Evidence parts, whitelisted to what the UI shows.
     *
     * @param  list<array<string, mixed>>  $parts
     * @return list<array{signal: string, label: string, points: int}>
     */
    public static function parts(array $parts): array
    {
        return array_map(static fn (array $part): array => [
            'signal' => is_string($part['signal'] ?? null) ? $part['signal'] : 'unknown',
            'label' => is_string($part['label'] ?? null) ? $part['label'] : 'Signal',
            'points' => is_numeric($part['points'] ?? null) ? (int) $part['points'] : 0,
        ], $parts);
    }

    /**
     * @param  array<int, string>  $merchantNames
     * @param  array<int, string>  $userNames
     * @return array<string, mixed>
     */
    public static function decision(DecisionHistoryEntry $entry, array $merchantNames, array $userNames): array
    {
        return [
            'id' => $entry->id,
            'listingId' => $entry->listingId,
            'merchant' => ['id' => $entry->merchantId, 'name' => $merchantNames[$entry->merchantId] ?? "Merchant #{$entry->merchantId}"],
            'sku' => $entry->merchantSku,
            'kind' => self::decisionKind($entry->kind),
            'product' => self::product($entry->product),
            'previousProduct' => self::product($entry->previousProduct),
            'score' => $entry->score,
            'reason' => $entry->reason,
            'note' => $entry->note,
            'decidedBy' => $entry->decidedByUserId === null
                ? null
                : ['id' => $entry->decidedByUserId, 'name' => $userNames[$entry->decidedByUserId] ?? "User #{$entry->decidedByUserId}"],
            'feedRunId' => $entry->feedRunId,
            'supersedesId' => $entry->supersedesId,
            'decidedAt' => self::date($entry->decidedAt),
            'parts' => self::parts($entry->parts),
        ];
    }

    public static function date(?DateTimeInterface $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }

    /**
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $paginator
     * @param  callable(TItem): array<string, mixed>  $map
     * @return array{data: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}, links: array{prev: ?string, next: ?string}}
     */
    public static function paginated(LengthAwarePaginator $paginator, callable $map): array
    {
        $paginator->withQueryString();

        /** @var list<TItem> $items */
        $items = $paginator->items();

        return [
            'data' => array_map($map, $items),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => ['prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
        ];
    }

    private function policy(): ?MatchingPolicy
    {
        if (! $this->policyResolved) {
            $this->policyResolved = true;

            try {
                $this->policy = $this->policies->current()->policy;
            } catch (NoActiveMatchingPolicy) {
                $this->policy = null;
            }
        }

        return $this->policy;
    }
}
