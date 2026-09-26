<?php

namespace App\Domain\Search\Analytics;

use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\QueryNormalizer;
use App\Domain\Search\Query\SortOption;
use App\Models\SearchQuery as SearchQueryRecord;
use DateTimeZone;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records one search for analytics (docs/architecture/phase-3-search.md §6).
 * The row is prepared here (redaction, session hash, bot flag — cheap and
 * pure) and written with ONE synchronous insert, so a result click that
 * arrives right after the page can always be attributed, independent of
 * queue lag; only the events (PublishSearchRecorded) go to the `analytics`
 * queue. The row carries no IP address and no user id. Returns the search
 * id so the page can attribute result clicks, or null when nothing is
 * recorded (prefetch requests, texts below the searchable minimum, or a
 * failed write — reported, never allowed to break the search itself).
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
        $resultCount = max(0, $input->resultCount);
        $isBot = $this->bots->isBot($input->userAgent);

        $record = new SearchQueryRecord;
        $record->forceFill([
            'search_id' => $searchId,
            'occurred_at' => $input->occurredAt->setTimezone(new DateTimeZone('UTC')),
            'market' => $input->market,
            'locale' => mb_substr($input->locale, 0, 12),
            'source' => $input->source,
            'query_normalized' => $query->text,
            'query_hash' => $query->hash,
            'filters' => $filters === [] ? null : $filters,
            'result_count' => $resultCount,
            'result_refs' => array_slice(self::refs($input->resultRefs), 0, self::MAX_REFS),
            'session_hash' => $this->sessions->hash($input->sessionId, $input->occurredAt),
            'is_bot' => $isBot,
        ]);

        try {
            $record->save();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        PublishSearchRecorded::dispatch($searchId, $input->market, $input->source->value, $query->hash, $resultCount, $isBot)
            ->onQueue(self::QUEUE);

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
