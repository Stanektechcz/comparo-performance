<?php

namespace App\Http\Presenters;

/**
 * A presented search page: the Inertia props (without `searchId`, which the
 * controller adds after recording) and the displayed result references in
 * display order, which analytics store for click attribution.
 */
final readonly class SearchPresentation
{
    /**
     * @param  array<string, mixed>  $props
     * @param  list<array{type: string, id: int}>  $refs  position = index + 1
     * @param  int  $total  the displayed result count (engine total minus hits dropped by the compliance re-check)
     */
    public function __construct(
        public array $props,
        public array $refs,
        public int $total,
    ) {}
}
