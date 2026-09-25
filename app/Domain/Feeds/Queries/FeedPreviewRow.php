<?php

namespace App\Domain\Feeds\Queries;

use App\Domain\Feeds\FeedItemValidationStatus;

/**
 * One previewed row: its validation verdict, the normalised values (empty for
 * a rejected row) and every issue found in it.
 */
final readonly class FeedPreviewRow
{
    /**
     * @param  array<string, string|int|bool|null>  $values
     * @param  list<array{code: string, severity: string, field: string|null, params: array<string, string|int>}>  $issues
     */
    public function __construct(
        public int $lineNumber,
        public FeedItemValidationStatus $status,
        public ?string $merchantSku,
        public array $values,
        public array $issues,
    ) {}
}
