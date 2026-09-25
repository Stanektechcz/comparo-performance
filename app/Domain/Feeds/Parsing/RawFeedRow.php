<?php

namespace App\Domain\Feeds\Parsing;

/**
 * One source record exactly as parsed, converted to UTF-8.
 *
 * `lineNumber` is the physical line where the record starts (CSV, XML) or the
 * 1-based position of the item in the list (JSON).
 */
final readonly class RawFeedRow
{
    /**
     * @param  array<string, string>  $fields  source key => raw value
     */
    public function __construct(
        public int $lineNumber,
        public array $fields,
    ) {}

    public function get(string $key): ?string
    {
        return $this->fields[$key] ?? null;
    }
}
