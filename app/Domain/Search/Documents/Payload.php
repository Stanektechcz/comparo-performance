<?php

namespace App\Domain\Search\Documents;

use App\Domain\Shared\Text\TextFold;

/**
 * Typed reads of stored document payloads and the folded searchable text.
 */
final class Payload
{
    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<string>
     */
    public static function strings(array $payload, string $key): array
    {
        $values = $payload[$key] ?? [];

        return is_array($values) ? array_values(array_map(static fn (mixed $value): string => (string) $value, $values)) : [];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function optionalFloat(array $payload, string $key): ?float
    {
        $value = $payload[$key] ?? null;

        return $value === null ? null : (float) $value;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public static function map(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * Folded, space-joined, empty parts dropped.
     *
     * @param  list<?string>  $parts
     */
    public static function searchableText(array $parts): string
    {
        return TextFold::fold(implode(' ', array_filter($parts, static fn (?string $part): bool => $part !== null && $part !== '')));
    }
}
