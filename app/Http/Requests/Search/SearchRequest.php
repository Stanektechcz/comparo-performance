<?php

namespace App\Http\Requests\Search;

use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\Query\QueryNormalizer;
use App\Domain\Search\Query\SearchFilters;
use App\Domain\Search\Query\SearchQuery;
use App\Domain\Search\Query\SortOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of GET /search. Invalid parameters redirect to a clean
 * /search with errors. A text shorter than 2 characters after trimming is
 * valid but not searchable (empty state, the engine is not called).
 *
 * Prices are whole units of the market currency (every Comparo currency has
 * two minor digits); they are converted to minor units for the engine.
 */
class SearchRequest extends FormRequest
{
    public const int PER_PAGE = 20;

    public const int MAX_PAGE = 50;

    public const int MAX_PRICE = 100_000;

    public const int MINOR_PER_MAJOR = 100;

    public const string ALL_TYPES = 'all';

    /** Stricter than `[a-z0-9-]+`: the slug shape SearchFilters accepts. */
    public const string SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    protected $redirectRoute = 'search';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $slugs = ['nullable', 'array', 'max:'.SearchFilters::MAX_VALUES];
        $slug = ['string', 'max:100', 'regex:'.self::SLUG_PATTERN, 'distinct'];

        return [
            'q' => ['nullable', 'string', 'max:'.SearchQuery::MAX_TEXT_LENGTH],
            'type' => ['nullable', 'string', Rule::in(self::typeTabs())],
            'brand' => $slugs,
            'brand.*' => $slug,
            'category' => $slugs,
            'category.*' => $slug,
            'ingredient' => $slugs,
            'ingredient.*' => $slug,
            'price_min' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'price_max' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_PRICE, Rule::when($this->filled('price_min'), 'gte:price_min')],
            'in_stock' => ['nullable', 'boolean'],
            'min_rating' => ['nullable', 'integer', 'between:1,5'],
            'sort' => ['nullable', 'string', Rule::enum(SortOption::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
        ];
    }

    /**
     * @return list<string>
     */
    public static function typeTabs(): array
    {
        return [self::ALL_TYPES, ...array_map(static fn (SearchableType $type): string => $type->value, SearchableType::cases())];
    }

    public function text(): string
    {
        return $this->string('q')->toString();
    }

    public function isSearchable(): bool
    {
        return (new QueryNormalizer)->normalize($this->text())->searchable;
    }

    public function type(): ?SearchableType
    {
        return SearchableType::tryFrom($this->string('type')->toString());
    }

    public function sort(): SortOption
    {
        return SortOption::tryFrom($this->string('sort')->toString()) ?? SortOption::Relevance;
    }

    public function page(): int
    {
        return $this->optionalInt('page') ?? 1;
    }

    public function toSearchQuery(string $market): SearchQuery
    {
        $priceMin = $this->optionalInt('price_min');
        $priceMax = $this->optionalInt('price_max');
        $minRating = $this->optionalInt('min_rating');

        return new SearchQuery(
            text: $this->text(),
            market: $market,
            filters: new SearchFilters(
                brandSlugs: $this->slugs('brand'),
                categorySlugs: $this->slugs('category'),
                ingredientSlugs: $this->slugs('ingredient'),
                priceMinMinor: $priceMin === null ? null : $priceMin * self::MINOR_PER_MAJOR,
                priceMaxMinor: $priceMax === null ? null : $priceMax * self::MINOR_PER_MAJOR,
                inStock: $this->boolean('in_stock'),
                minRating: $minRating === null ? null : (float) $minRating,
            ),
            sort: $this->sort(),
            page: $this->page(),
            perPage: self::PER_PAGE,
            type: $this->type(),
        );
    }

    /**
     * The validated criteria, echoed to the page and (allow-listed again by
     * RecordSearch) to analytics.
     *
     * @return array{q: string, type: string, sort: string, page: int, brand: list<string>, category: list<string>, ingredient: list<string>, price_min: ?int, price_max: ?int, in_stock: bool, min_rating: ?int}
     */
    public function criteria(): array
    {
        return [
            'q' => $this->text(),
            'type' => $this->type()->value ?? self::ALL_TYPES,
            'sort' => $this->sort()->value,
            'page' => $this->page(),
            'brand' => $this->slugs('brand'),
            'category' => $this->slugs('category'),
            'ingredient' => $this->slugs('ingredient'),
            'price_min' => $this->optionalInt('price_min'),
            'price_max' => $this->optionalInt('price_max'),
            'in_stock' => $this->boolean('in_stock'),
            'min_rating' => $this->optionalInt('min_rating'),
        ];
    }

    /**
     * @return list<string>
     */
    private function slugs(string $key): array
    {
        $values = $this->input($key);

        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }

    private function optionalInt(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
