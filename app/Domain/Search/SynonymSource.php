<?php

namespace App\Domain\Search;

/**
 * Origin of a synonym term (`search_synonyms.source`): the frozen prototype
 * `H.synonyms` seed or a staff addition.
 */
enum SynonymSource: string
{
    case Prototype = 'prototype';
    case Staff = 'staff';
}
