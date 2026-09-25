<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\ConflictKind;
use App\Domain\Matching\ConflictStatus;
use App\Models\MatchingConflict;
use App\Models\MatchingConflictValue;
use DateTimeImmutable;

/**
 * One item of the staff conflict queue with its competing values.
 */
final readonly class ConflictEntry
{
    /**
     * @param  list<array{merchant_id: ?int, merchant_product_id: ?int, source_type: string, source_priority: int, value: string, observed_count: int}>  $values
     */
    public function __construct(
        public int $id,
        public ConflictKind $kind,
        public ?string $field,
        public ConflictStatus $status,
        public ?ProductSummary $product,
        public DateTimeImmutable $createdAt,
        public array $values,
    ) {}

    /**
     * Expects `product.brand` and `values` to be eager-loaded.
     */
    public static function fromModel(MatchingConflict $conflict): self
    {
        return new self(
            id: $conflict->id,
            kind: $conflict->kind,
            field: $conflict->field,
            status: $conflict->status,
            product: ProductSummary::fromModel($conflict->product),
            createdAt: $conflict->created_at->toDateTimeImmutable(),
            values: array_values($conflict->values->map(static fn (MatchingConflictValue $value): array => [
                'merchant_id' => $value->merchant_id,
                'merchant_product_id' => $value->merchant_product_id,
                'source_type' => $value->source_type,
                'source_priority' => $value->source_priority,
                'value' => $value->value,
                'observed_count' => $value->observed_count,
            ])->all()),
        );
    }
}
