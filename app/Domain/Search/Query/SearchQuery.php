<?php

namespace App\Domain\Search\Query;

use App\Domain\Search\Local\SearchableType;
use InvalidArgumentException;

/**
 * One search request: text, the visitor's market, typed filters, ordering,
 * optional type tab and page.
 */
final readonly class SearchQuery
{
    public const int MAX_TEXT_LENGTH = 200;

    public const int MAX_PER_PAGE = 100;

    public const int DEFAULT_PER_PAGE = 20;

    /**
     * @param  string  $market  ISO-3166 alpha-2, upper case
     * @param  ?SearchableType  $type  the selected type tab; null = all types
     */
    public function __construct(
        public string $text,
        public string $market,
        public SearchFilters $filters = new SearchFilters,
        public SortOption $sort = SortOption::Relevance,
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
        public ?SearchableType $type = null,
    ) {
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException('The search text is limited to '.self::MAX_TEXT_LENGTH.' characters.');
        }

        if (preg_match('/^[A-Z]{2}$/', $market) !== 1) {
            throw new InvalidArgumentException("Invalid market code [{$market}].");
        }

        if ($page < 1) {
            throw new InvalidArgumentException('Pages start at 1.');
        }

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException('Between 1 and '.self::MAX_PER_PAGE.' results per page.');
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
