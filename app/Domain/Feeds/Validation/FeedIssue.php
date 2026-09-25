<?php

namespace App\Domain\Feeds\Validation;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Mapping\FeedField;

/**
 * One row-level problem: a code, the canonical field it concerns and the
 * message parameters ({@see FeedErrorCode::messageParams()}).
 */
final readonly class FeedIssue
{
    /**
     * @param  array<string, string|int>  $params
     */
    public function __construct(
        public FeedErrorCode $code,
        public ?FeedField $field = null,
        public array $params = [],
    ) {}

    public function severity(): FeedIssueSeverity
    {
        return $this->code->severity();
    }
}
