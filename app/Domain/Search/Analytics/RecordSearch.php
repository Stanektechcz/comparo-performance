<?php

namespace App\Domain\Search\Analytics;

use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\QueryNormalizer;
use App\Domain\Search\Query\SortOption;
use DateTimeZone;
use Illuminate\Support\Str;

/**
 * Records one search for analytics (docs/architecture/phase-3-search.md §6)
 * without slowing it down: the row is prepared here (redaction, session
 * hash, bot flag — cheap and pure) and written by StoreSearchQuery on the
 * `analytics` queue. Returns the pre-generated ULID so the page can
 * attribute result clicks, or null when nothing is recorded (prefetch
 * requests, texts below the searchable minimum).
 */
final readonly class RecordSearch
{
    public const string QUEUE = 'analytics';

    /** Criteria keys stored in `search_queries.filters`; everything else is dropped. */
    public const array ALLOWED_FILTERS = ['type', 'sort', 'page', 'brand', 'category', 'ingredient', 'price_min', 'price_max', 'in_stock', 'min_rating'];

    private const string SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const int MAX_SLUGS = 50;

    private const int MAX_REFS = 100;

    public function __construct(
        private QueryRedactor $redactor,
        private SessionHasher $sessions,
        private BotDetector $bots,
    ) {}

    public function record(SearchRecordInput $input): ?string
    {
        if ($input->isPrefetch || ! (new QueryNormalizer)->normalize($input->rawQuery)->searchable) {
            return null;
        }

        $searchId = strtolower((string) Str::ulid());
        $query = $this->redactor->redact($input->rawQuery);
        $filters = self::allowListed($input->filters);

        StoreSearchQuery::dispatch(
            searchId: $searchId,
            occurredAt: $input->occurredAt->setTimezone(new DateTimeZone('UTC'))->format(StoreSearchQuery::DATE_FORMAT),
            market: $input->market,
            locale: mb_substr($input->locale, 0, 12),
            source: $input->source->value,
            queryNormalized: $query->text,
            queryHash: $query->hash,
            filters: $filters === [] ? null : $filters,
            resultCount: max(0, $input->resultCount),
            resultRefs: array_slice(self::refs($input->resultRefs), 0, self::MAX_REFS),
            sessionHash: $this->sessions->hash($input->sessionId, $input->occurredAt),
            isBot: $this->bots->isBot($input->userAgent),
        )->onQueue(self::QUEUE);

        return $searchId;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function allowListed(array $filters): array
    {
        $kept = [];

        foreach (self::ALLOWED_FILTERS as $key) {
            $value = self::clean($key, $filters[$key] ?? null);

            if ($value !== null) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }

    private static function clean(string $key, mixed $value): mixed
    {
        return match ($key) {
            'type' => is_string($value) && SearchableType::tryFrom($value) !== null ? $value : null,
            'sort' => is_string($value) && SortOption::tryFrom($value) !== null && $value !== SortOption::Relevance->value ? $value : null,
            'page' => is_int($value) && $value > 1 ? $value : null,
            'brand', 'category', 'ingredient' => self::slugs($value),
            'price_min', 'price_max' => is_int($value) && $value >= 0 ? $value : null,
            'in_stock' => $value === true ? true : null,
            'min_rating' => is_int($value) && $value >= 1 && $value <= 5 ? $value : null,
            default => null,
        };
    }

    /**
     * @return ?list<string>
     */
    private static function slugs(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $slugs = array_values(array_filter($value, static fn (mixed $slug): bool => is_string($slug) && preg_match(self::SLUG, $slug) === 1));
        $slugs = array_slice(array_values(array_unique($slugs)), 0, self::MAX_SLUGS);

        return $slugs === [] ? null : $slugs;
    }

    /**
     * @param  list<array{type: string, id: int}>  $refs
     * @return list<array{type: string, id: int}>
     */
    private static function refs(array $refs): array
    {
        $clean = [];

        foreach ($refs as $ref) {
            if (SearchableType::tryFrom($ref['type']) !== null && $ref['id'] > 0) {
                $clean[] = ['type' => $ref['type'], 'id' => $ref['id']];
            }
        }

        return $clean;
    }
}
