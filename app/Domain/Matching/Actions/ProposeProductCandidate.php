<?php

namespace App\Domain\Matching\Actions;

use App\Domain\Matching\CandidateStatus;
use App\Domain\Matching\Exceptions\ListingOutsideMerchantScope;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\ListingMatchStatus;
use App\Domain\Matching\Queries\CandidateProducts;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditLogger;
use App\Domain\Shared\Text\TextFold;
use App\Models\MerchantProduct;
use App\Models\ProductCandidate;
use App\Models\ProductCandidateSource;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * A merchant proposes a new canonical product from an unmatched or suggested
 * listing. Proposals with the same identity are merged: one OPEN candidate per
 * fingerprint sha256(fold(brand) | canonical(title) | fold(pack)), one source
 * row per listing. The listing itself is unchanged; creating the canonical
 * product stays a staff catalogue action (A-15). Audited as
 * `product_candidate.proposed`.
 */
final class ProposeProductCandidate
{
    private const int NAME_MAX = 255;

    private const int BRAND_MAX = 128;

    private const int PACK_MAX = 32;

    private const int EAN_MAX = 14;

    private const int EVIDENCE_TITLES_MAX = 10;

    public function __construct(
        private readonly CandidateProducts $candidates,
        private readonly AuditLogger $audit,
    ) {}

    public static function fingerprint(?string $brandRaw, string $title, ?string $packRaw): string
    {
        return hash('sha256', TextFold::fold($brandRaw ?? '').'|'.TextFold::canonical($title).'|'.TextFold::fold($packRaw ?? ''));
    }

    /**
     * @throws ListingOutsideMerchantScope
     * @throws MatchDecisionNotAllowed
     */
    public function handle(MerchantProduct $listing, MatchingActor $actor, DateTimeImmutable $proposedAt): ProductCandidate
    {
        return DB::transaction(function () use ($listing, $actor, $proposedAt): ProductCandidate {
            $locked = MerchantProduct::query()->lockForUpdate()->findOrFail($listing->id);
            $actor->assertCanActOn($locked);
            $this->guardProposable($locked);

            $title = (string) $locked->title;
            $fingerprint = self::fingerprint($locked->brand_raw, $title, $locked->pack_raw);
            $at = $proposedAt->setTimezone(new DateTimeZone('UTC'));

            $candidate = ProductCandidate::query()
                ->where('fingerprint', $fingerprint)
                ->where('status', CandidateStatus::Proposed)
                ->lockForUpdate()
                ->first()
                ?? ProductCandidate::query()->createOrFirst(
                    ['fingerprint' => $fingerprint, 'status' => CandidateStatus::Proposed],
                    $this->newCandidateAttributes($locked, $title),
                );

            ProductCandidateSource::query()->firstOrCreate(
                ['product_candidate_id' => $candidate->id, 'merchant_product_id' => $locked->id],
                ['merchant_id' => $locked->merchant_id, 'first_seen_at' => $at],
            );

            $candidate->fill([
                'source_count' => $candidate->sources()->count(),
                'evidence' => ['titles' => $this->evidenceTitles($candidate, $title)],
            ])->save();

            $this->audit->record(
                AuditAction::ProductCandidateProposed,
                AuditActor::user($actor->user),
                $candidate,
                [],
                ['merchant_product_id' => $locked->id, 'source_count' => $candidate->source_count],
            );

            return $candidate;
        });
    }

    private function guardProposable(MerchantProduct $listing): void
    {
        if (! in_array($listing->match_status, [ListingMatchStatus::Unmatched, ListingMatchStatus::Suggested], true)) {
            throw MatchDecisionNotAllowed::listingNotProposable($listing->id, $listing->match_status->value);
        }

        if (trim((string) $listing->title) === '') {
            throw MatchDecisionNotAllowed::listingWithoutTitle($listing->id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function newCandidateAttributes(MerchantProduct $listing, string $title): array
    {
        $ean = $listing->ean;

        return [
            'proposed_name' => mb_substr(trim($title), 0, self::NAME_MAX),
            'brand_id' => $this->candidates->brandIdFor($listing->brand_raw),
            'brand_raw' => $listing->brand_raw === null ? null : mb_substr($listing->brand_raw, 0, self::BRAND_MAX),
            'ean' => $ean !== null && $ean !== '' && strlen($ean) <= self::EAN_MAX ? $ean : null,
            'pack_label' => $listing->pack_raw === null ? null : mb_substr($listing->pack_raw, 0, self::PACK_MAX),
            'evidence' => ['titles' => []],
            'source_count' => 0,
        ];
    }

    /**
     * @return list<string>
     */
    private function evidenceTitles(ProductCandidate $candidate, string $title): array
    {
        $titles = $candidate->evidence['titles'] ?? [];
        $titles = is_array($titles) ? array_values(array_filter($titles, is_string(...))) : [];

        if (! in_array($title, $titles, true) && count($titles) < self::EVIDENCE_TITLES_MAX) {
            $titles[] = $title;
        }

        return $titles;
    }
}
