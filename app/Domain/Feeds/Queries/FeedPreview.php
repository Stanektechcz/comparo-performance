<?php

namespace App\Domain\Feeds\Queries;

/**
 * The first rows of a feed payload as the mapping screen shows them.
 * `available` is false when there is no stored payload to preview yet;
 * `errorCode` is set when the sample could not be read (the translated
 * message is `feeds.errors.{errorCode}` with `errorParams`).
 */
final readonly class FeedPreview
{
    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $mapping  canonical field => source key
     * @param  list<string>  $missingRequired  canonical fields
     * @param  list<FeedPreviewRow>  $rows
     * @param  array<string, string|int>  $errorParams
     */
    public function __construct(
        public bool $available,
        public array $headers = [],
        public array $mapping = [],
        public bool $mappingSuggested = false,
        public array $missingRequired = [],
        public array $rows = [],
        public ?string $errorCode = null,
        public array $errorParams = [],
        public ?int $errorLine = null,
    ) {}

    public static function unavailable(): self
    {
        return new self(available: false);
    }
}
