<?php

namespace App\Domain\Search;

/**
 * Whether a synonym term takes part in query expansion (`search_synonyms.status`).
 */
enum SynonymStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
