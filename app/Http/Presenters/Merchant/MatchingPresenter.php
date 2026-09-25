<?php

namespace App\Http\Presenters\Merchant;

use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\DecisionHistoryEntry;
use App\Domain\Matching\Queries\PreviewCandidate;
use App\Domain\Matching\Queries\QueueListing;
use App\Domain\Platform\Markets\MarketContext;
use App\Http\Presenters\Admin\MatchingFormat;
use App\Models\MerchantProduct;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * The merchant's own matching review. Shapes reuse the staff console's
 * whitelisted product/evidence formats ({@see MatchingFormat}); who decided
 * is reduced to a team member's name, "Comparo team" or "Automatic
 * matching" — staff identities never reach merchants. Feed sources expose
 * id + name only.
 */
final class MatchingPresenter
{
    public const string DECIDED_BY_SYSTEM = 'Automatic matching';

    public const string DECIDED_BY_STAFF = 'Comparo team';

    public function __construct(private readonly MatchingFormat $format) {}

    /**
     * @param  LengthAwarePaginator<int, QueueListing>  $page
     * @return array<string, mixed>
     */
    public function queue(LengthAwarePaginator $page): array
    {
        return MerchantFormat::paginated($page, fn (QueueListing $item): array => [
            'id' => $item->listingId,
            'sku' => $item->merchantSku,
            'title' => $item->title,
            'ean' => $item->ean,
            'brandRaw' => $item->brandRaw,
            'packRaw' => $item->packRaw,
            'variantRaw' => $item->variantRaw,
            'status' => MatchingFormat::status($item->status),
            'score' => $item->score,
            'level' => $this->format->levelFor($item->score),
            'product' => MatchingFormat::product($item->product),
            'decidedAt' => MerchantFormat::date($item->decidedAt),
            'updatedAt' => MerchantFormat::date($item->updatedAt),
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, DecisionHistoryEntry>  $page
     * @return array<string, mixed>
     */
    public function history(LengthAwarePaginator $page, int $merchantId): array
    {
        /** @var list<DecisionHistoryEntry> $items */
        $items = array_values($page->items());
        $members = $this->memberNames($merchantId, $items);

        return MerchantFormat::paginated($page, fn (DecisionHistoryEntry $entry): array => $this->decision($entry, $members));
    }

    /**
     * @param  list<PreviewCandidate>  $candidates
     * @param  LengthAwarePaginator<int, DecisionHistoryEntry>  $history
     * @return array<string, mixed>
     */
    public function listing(
        MerchantProduct $listing,
        ?MarketContext $market,
        array $candidates,
        ?string $previewUnavailable,
        LengthAwarePaginator $history,
        User $viewer,
    ): array {
        /** @var list<DecisionHistoryEntry> $entries */
        $entries = array_values($history->items());
        $members = $this->memberNames($listing->merchant_id, $entries);
        $current = null;

        foreach ($entries as $entry) {
            if ($entry->id === $listing->current_matching_decision_id) {
                $current = $entry;
            }
        }

        $gate = Gate::forUser($viewer);

        return [
            'listing' => $this->listingFacts($listing, $market),
            'currentDecision' => $current === null ? null : $this->decision($current, $members),
            'candidates' => array_map(fn (PreviewCandidate $candidate): array => [
                'product' => MatchingFormat::product($candidate->product),
                'score' => $candidate->score,
                'level' => MatchingFormat::level($candidate->level),
                'bucket' => $candidate->bucket->value,
                'parts' => MatchingFormat::parts($candidate->parts),
            ], $candidates),
            'previewUnavailable' => $previewUnavailable,
            'history' => array_map(fn (DecisionHistoryEntry $entry): array => $this->decision($entry, $members), $entries),
            'historyTotal' => $history->total(),
            'can' => [
                'decide' => $gate->allows('decide', $listing),
                'propose' => $gate->allows('propose', $listing),
            ],
            'actions' => self::actions($listing, $current),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listingFacts(MerchantProduct $listing, ?MarketContext $market): array
    {
        $source = $listing->feedSource;

        return [
            'id' => $listing->id,
            'feedSource' => $source === null ? null : ['id' => $source->id, 'name' => $source->name],
            'market' => $market === null ? null : ['code' => $market->code, 'name' => $market->name],
            'sku' => $listing->merchant_sku,
            'externalId' => $listing->external_id,
            'title' => $listing->title,
            'ean' => $listing->ean,
            'brandRaw' => $listing->brand_raw,
            'packRaw' => $listing->pack_raw,
            'variantRaw' => $listing->variant_raw,
            'categoryRaw' => $listing->category_raw,
            'url' => self::webUrl($listing->url),
            'status' => MatchingFormat::status($listing->match_status),
            'score' => $listing->match_score,
            'level' => $this->format->levelFor($listing->match_score),
            'linkedProductId' => $listing->product_id,
            'firstSeenAt' => MerchantFormat::date($listing->first_seen_at),
            'lastSeenAt' => MerchantFormat::date($listing->last_seen_at),
        ];
    }

    /**
     * @param  array<int, string>  $members
     * @return array<string, mixed>
     */
    private function decision(DecisionHistoryEntry $entry, array $members): array
    {
        return [
            'id' => $entry->id,
            'listingId' => $entry->listingId,
            'sku' => $entry->merchantSku,
            'kind' => MatchingFormat::decisionKind($entry->kind),
            'product' => MatchingFormat::product($entry->product),
            'previousProduct' => MatchingFormat::product($entry->previousProduct),
            'score' => $entry->score,
            'note' => $entry->note,
            'decidedBy' => match (true) {
                $entry->decidedByUserId === null => self::DECIDED_BY_SYSTEM,
                isset($members[$entry->decidedByUserId]) => $members[$entry->decidedByUserId],
                default => self::DECIDED_BY_STAFF,
            },
            'decidedAt' => MerchantFormat::date($entry->decidedAt),
            'parts' => MatchingFormat::parts($entry->parts),
        ];
    }

    /**
     * Names of the deciders who are members of this merchant (one query).
     *
     * @param  list<DecisionHistoryEntry>  $entries
     * @return array<int, string>
     */
    private function memberNames(int $merchantId, array $entries): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (DecisionHistoryEntry $entry): ?int => $entry->decidedByUserId, $entries),
            is_int(...),
        )));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = User::query()
            ->whereIn('users.id', $ids)
            ->whereHas('merchants', static fn ($query) => $query->whereKey($merchantId))
            ->pluck('name', 'id')
            ->all();

        return $names;
    }

    /**
     * What the listing's state allows (mirrors DecideMatch and
     * ProposeProductCandidate; the actions re-check under a row lock).
     *
     * @return array{confirm: bool, choose: bool, reject: bool, propose: bool}
     */
    private static function actions(MerchantProduct $listing, ?DecisionHistoryEntry $current): array
    {
        $status = $listing->match_status;
        $rejectable = in_array($status, [
            ListingMatchStatus::Suggested, ListingMatchStatus::Auto, ListingMatchStatus::Manual, ListingMatchStatus::ComplianceHold,
        ], true);

        return [
            'confirm' => $status === ListingMatchStatus::Suggested
                && $current?->kind === MatchDecisionKind::Suggested
                && $current->product !== null,
            'choose' => ! $status->isLinked(),
            'reject' => $rejectable && ($listing->product_id ?? $current?->product?->id) !== null,
            'propose' => in_array($status, [ListingMatchStatus::Unmatched, ListingMatchStatus::Suggested], true)
                && trim((string) $listing->title) !== '',
        ];
    }

    /**
     * Merchant-supplied URLs become links only with an http(s) scheme.
     */
    private static function webUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
