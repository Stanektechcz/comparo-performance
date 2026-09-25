<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\CandidateStatus;
use App\Models\ProductCandidate;
use DateTimeImmutable;

/**
 * One new-product proposal in the staff queue, with its live source count.
 */
final readonly class CandidateEntry
{
    /**
     * @param  array<string, mixed>|null  $evidence
     */
    public function __construct(
        public int $id,
        public CandidateStatus $status,
        public string $proposedName,
        public ?int $brandId,
        public ?string $brandRaw,
        public ?string $ean,
        public ?string $packLabel,
        public int $sourceCount,
        public ?array $evidence,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * Expects `withCount('sources')`.
     */
    public static function fromModel(ProductCandidate $candidate): self
    {
        $sourcesCount = $candidate->getAttribute('sources_count');

        return new self(
            id: $candidate->id,
            status: $candidate->status,
            proposedName: $candidate->proposed_name,
            brandId: $candidate->brand_id,
            brandRaw: $candidate->brand_raw,
            ean: $candidate->ean,
            packLabel: $candidate->pack_label,
            sourceCount: is_numeric($sourcesCount) ? (int) $sourcesCount : $candidate->source_count,
            evidence: $candidate->evidence,
            createdAt: $candidate->created_at->toDateTimeImmutable(),
        );
    }
}
