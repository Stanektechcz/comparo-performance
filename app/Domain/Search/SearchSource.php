<?php

namespace App\Domain\Search;

/**
 * Where a recorded search was made (`search_queries.source`).
 */
enum SearchSource: string
{
    case Page = 'page';
    case Suggest = 'suggest';
}
