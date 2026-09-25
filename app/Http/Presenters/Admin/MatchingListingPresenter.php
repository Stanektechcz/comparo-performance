<?php

namespace App\Http\Presenters\Admin;

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Catalog\Queries\ProductFlavours;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\MatchDecisionKind;
use App\Domain\Matching\Queries\DecisionHistoryEntry;
use App\Domain\Matching\Queries\PreviewCandidate;
use App\Domain\Platform\Markets\MarketContext;
use App\Models\MerchantProduct;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Evidence page of one listing: its source facts (raw payload capped), the
 * live top candidates with per-signal points, the decision history and what
 * the viewer may do. Feed sources contribute id + name only — never their
 * URL or credentials.
 */
final class MatchingListingPresenter
{
    /** Raw payload fields shown to staff. */
    public const int RAW_FIELDS_MAX = 60;

    /** Characters per raw payload value. */
    public const int RAW_VALUE_MAX = 500;

    private const int RAW_KEY_MAX = 100;

    public function __construct(
        private readonly MatchingFormat $format,
        private readonly ReferenceNames $names,
        private readonly ProductFlavours $flavours,
    ) {}

    /**
     * @param  list<PreviewCandidate>  $candidates
     * @param  LengthAwarePaginator<int, DecisionHistoryEntry>  $history
     * @return array<string, mixed>
     */
    public function present(
        MerchantProduct $listing,
        ?MarketContext $market,
        array $candidates,
        ?string $previewUnavailable,
        LengthAwarePaginator $history,
        User $viewer,
    ): array {
        /** @var list<DecisionHistoryEntry> $entries */
        $entries = $history->items();
        $merchants = $this->names->merchants([$listing->merchant_id, ...array_map(static fn (DecisionHistoryEntry $entry): int => $entry->merchantId, $entries)]);
        $users = $this->names->users(array_values(array_filter(array_map(
            static fn (DecisionHistoryEntry $entry): ?int => $entry->decidedByUserId,
            $entries,
        ), is_int(...))));
        $decisions = array_map(static fn (DecisionHistoryEntry $entry): array => MatchingFormat::decision($entry, $merchants, $users), $entries);
        $current = self::currentEntry($listing, $entries);
        $canRematch = $viewer->can(Permission::ManageOffers->value);

        return [
            'listing' => $this->listing($listing, $market, $merchants),
            'currentDecision' => $current === null ? null : MatchingFormat::decision($current, $merchants, $users),
            'candidates' => $this->candidates($candidates),
            'previewUnavailable' => $previewUnavailable,
            'history' => $decisions,
            'historyTotal' => $history->total(),
            'can' => [
                'decide' => $viewer->can(Permission::ReviewMatching->value),
                'rematch' => $canRematch,
            ],
            'actions' => self::actions($listing, $current),
        ];
    }

    /**
     * @param  array<int, string>  $merchants
     * @return array<string, mixed>
     */
    private function listing(MerchantProduct $listing, ?MarketContext $market, array $merchants): array
    {
        $source = $listing->feedSource;

        return [
            'id' => $listing->id,
            'merchant' => ['id' => $listing->merchant_id, 'name' => $merchants[$listing->merchant_id] ?? "Merchant #{$listing->merchant_id}"],
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
            'listingStatus' => $listing->status->value,
            'status' => MatchingFormat::status($listing->match_status),
            'score' => $listing->match_score,
            'level' => $this->format->levelFor($listing->match_score),
            'linkedProductId' => $listing->product_id,
            'firstSeenAt' => MatchingFormat::date($listing->first_seen_at),
            'lastSeenAt' => MatchingFormat::date($listing->last_seen_at),
            'missingRunCount' => $listing->missing_run_count,
            'rawPayload' => self::rawPayload($listing->raw_payload),
        ];
    }

    /**
     * Merchant-supplied URLs become links only with an http(s) scheme
     * (never `javascript:` or `data:`).
     */
    private static function webUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /**
     * @param  list<PreviewCandidate>  $candidates
     * @return list<array<string, mixed>>
     */
    private function candidates(array $candidates): array
    {
        $variants = $this->flavours->of(array_map(static fn (PreviewCandidate $candidate): int => $candidate->product->id, $candidates));

        return array_map(fn (PreviewCandidate $candidate): array => [
            'product' => [
                ...(array) MatchingFormat::product($candidate->product),
                'variants' => $variants[$candidate->product->id] ?? [],
            ],
            'score' => $candidate->score,
            'level' => MatchingFormat::level($candidate->level),
            'bucket' => $candidate->bucket->value,
            'parts' => MatchingFormat::parts($candidate->parts),
        ], $candidates);
    }

    /**
     * @param  list<DecisionHistoryEntry>  $entries
     */
    private static function currentEntry(MerchantProduct $listing, array $entries): ?DecisionHistoryEntry
    {
        foreach ($entries as $entry) {
            if ($entry->id === $listing->current_matching_decision_id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * What the listing's state allows ({@see ListingMatchStatus::allowedManualActions()},
     * the rule DecideMatch applies, plus Rematch's; the actions re-check
     * under a row lock).
     *
     * @return array{confirm: bool, choose: bool, reject: bool, rematch: bool}
     */
    private static function actions(MerchantProduct $listing, ?DecisionHistoryEntry $current): array
    {
        $status = $listing->match_status;

        return [
            ...$status->allowedManualActions(
                hasPendingSuggestion: $current?->kind === MatchDecisionKind::Suggested && $current->product !== null,
                hasAssociatedProduct: ($listing->product_id ?? $current?->product?->id) !== null,
            ),
            'rematch' => $status->isLinked() && $listing->product_id !== null,
        ];
    }

    /**
     * Top-level raw source fields, capped in count and length. Nested values
     * are shown as compact JSON.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{fields: list<array{key: string, value: string}>, truncated: bool}|null
     */
    private static function rawPayload(?array $payload): ?array
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        $fields = [];
        $truncated = count($payload) > self::RAW_FIELDS_MAX;

        foreach (array_slice($payload, 0, self::RAW_FIELDS_MAX, true) as $key => $value) {
            $text = is_scalar($value) || $value === null
                ? (string) ($value ?? '')
                : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

            if (mb_strlen($text) > self::RAW_VALUE_MAX) {
                $text = mb_substr($text, 0, self::RAW_VALUE_MAX).'…';
                $truncated = true;
            }

            $fields[] = ['key' => mb_substr((string) $key, 0, self::RAW_KEY_MAX), 'value' => $text];
        }

        return ['fields' => $fields, 'truncated' => $truncated];
    }
}
