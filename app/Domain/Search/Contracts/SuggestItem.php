<?php

namespace App\Domain\Search\Contracts;

use App\Domain\Search\Local\SearchableType;

/**
 * One header-suggest entry: a label and a slug to link to. Never prices,
 * never outbound links.
 */
final readonly class SuggestItem
{
    public function __construct(
        public SearchableType $type,
        public int|string $id,
        public string $label,
        public ?string $slug,
    ) {}
}
