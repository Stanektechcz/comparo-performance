<?php

namespace App\Http\Presenters\Merchant;

use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Shared, whitelisted shapes of the merchant portal (camelCase for Inertia).
 */
final class MerchantFormat
{
    public static function date(?DateTimeInterface $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }

    /**
     * @template TValue of string
     *
     * @param  TValue  $value
     * @return array{value: TValue, label: string}
     */
    public static function labelled(string $value, string $label): array
    {
        return ['value' => $value, 'label' => $label];
    }

    /**
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $paginator
     * @param  callable(TItem): array<string, mixed>  $map
     * @param  array<string, mixed>  $query  query string kept on the page links
     * @return array{data: list<array<string, mixed>>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}, links: array{prev: ?string, next: ?string}}
     */
    public static function paginated(LengthAwarePaginator $paginator, callable $map, array $query = []): array
    {
        if ($query !== []) {
            $paginator->appends($query);
        }

        /** @var list<TItem> $items */
        $items = array_values($paginator->items());

        return [
            'data' => array_map($map, $items),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => ['prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
        ];
    }
}
