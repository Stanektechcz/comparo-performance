<?php

namespace App\Domain\Feeds\Validation;

/**
 * A feed row that failed validation and will not be published.
 */
final readonly class RowRejection
{
    /**
     * @param  list<FeedIssue>  $issues  at least one row-reject issue
     */
    public function __construct(
        public int $lineNumber,
        public ?string $merchantSku,
        public array $issues,
    ) {}

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(static fn (FeedIssue $issue): string => $issue->code->value, $this->issues);
    }
}
