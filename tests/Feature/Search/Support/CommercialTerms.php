<?php

namespace Tests\Feature\Search\Support;

/**
 * The commercial-term pattern of tests/Architecture/RankingPurityTest.php
 * (COMMERCIAL_TERMS), read from that file so both checks can never drift
 * apart: search documents must be as free of commercial data as ranking
 * inputs.
 */
final class CommercialTerms
{
    public static function pattern(): string
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3).'/Architecture/RankingPurityTest.php');

        if (preg_match("/const COMMERCIAL_TERMS = '([^']+)';/", $source, $matches) !== 1) {
            throw new \RuntimeException('COMMERCIAL_TERMS not found in RankingPurityTest.php.');
        }

        return $matches[1];
    }

    /**
     * Every key of a nested payload, dotted (`markets.DE.min_total_minor`).
     *
     * @param  array<array-key, mixed>  $payload
     * @return list<string>
     */
    public static function keys(array $payload, string $prefix = ''): array
    {
        $keys = [];

        foreach ($payload as $key => $value) {
            $path = $prefix.$key;
            $keys[] = $path;

            if (is_array($value)) {
                array_push($keys, ...self::keys($value, $path.'.'));
            }
        }

        return $keys;
    }
}
