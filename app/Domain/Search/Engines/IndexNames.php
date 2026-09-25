<?php

namespace App\Domain\Search\Engines;

use App\Domain\Search\Contracts\SearchIndex;
use InvalidArgumentException;

/**
 * Logical → physical index names: `scout.prefix` + a validated logical
 * name. Nothing else ever becomes part of an index name.
 */
final readonly class IndexNames
{
    private const string PREFIX_PATTERN = '/^[A-Za-z0-9_-]{0,32}$/';

    public function __construct(public string $prefix = '')
    {
        if (preg_match(self::PREFIX_PATTERN, $prefix) !== 1) {
            throw new InvalidArgumentException('The search index prefix may only contain up to 32 of [A-Za-z0-9_-].');
        }
    }

    public static function fromConfig(): self
    {
        return new self((string) config('scout.prefix', ''));
    }

    public function physical(string $logical): string
    {
        SearchIndex::fromName($logical);

        return $this->prefix.$logical;
    }

    public function live(SearchIndex $index): string
    {
        return $this->prefix.$index->value;
    }

    /**
     * The logical live index of a physical live index name, or null.
     */
    public function liveIndexOf(string $physical): ?SearchIndex
    {
        foreach (SearchIndex::cases() as $index) {
            if ($this->live($index) === $physical) {
                return $index;
            }
        }

        return null;
    }
}
