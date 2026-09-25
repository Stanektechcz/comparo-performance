<?php

namespace App\Domain\Search\Documents;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Id and timestamp helpers shared by the document builders.
 */
final class BuildIds
{
    /**
     * @param  list<int>  $ids
     * @return list<int> unique, ascending
     */
    public static function normalize(array $ids): array
    {
        $unique = array_values(array_unique(array_map(intval(...), $ids)));
        sort($unique);

        return $unique;
    }

    /**
     * Requested ids without a document, as document ids.
     *
     * @param  list<int>  $requested
     * @param  list<int>  $built
     * @return list<string>
     */
    public static function missing(array $requested, array $built): array
    {
        return array_values(array_map(strval(...), array_diff($requested, $built)));
    }

    public static function indexedAt(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM);
    }
}
