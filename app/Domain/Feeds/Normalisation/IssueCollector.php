<?php

namespace App\Domain\Feeds\Normalisation;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Validation\FeedIssue;
use App\Domain\Feeds\Validation\FeedIssueSeverity;

/**
 * Per-row accumulator used by {@see FeedRowMapper}; never shared between rows.
 *
 * @internal
 */
final class IssueCollector
{
    /** @var list<FeedIssue> */
    private array $issues = [];

    /**
     * @param  array<string, string|int>  $params
     */
    public function add(FeedErrorCode $code, ?FeedField $field = null, array $params = []): void
    {
        $this->issues[] = new FeedIssue($code, $field, $params);
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    /**
     * @return list<FeedIssue>
     */
    public function errors(): array
    {
        return $this->withSeverity(FeedIssueSeverity::Error);
    }

    /**
     * @return list<FeedIssue>
     */
    public function warnings(): array
    {
        return $this->withSeverity(FeedIssueSeverity::Warning);
    }

    /**
     * @return list<FeedIssue>
     */
    private function withSeverity(FeedIssueSeverity $severity): array
    {
        return array_values(array_filter($this->issues, static fn (FeedIssue $issue): bool => $issue->severity() === $severity));
    }
}
