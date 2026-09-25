<?php

namespace App\Domain\Platform\PrototypeImport;

use JsonException;

/**
 * The materialised prototype SEED exported by tools/prototype-parity/export-fixtures.mjs.
 *
 * Shape: {meta: {frozenNow, ...}, seed: {NOW, currencies, countries, ...}, derived: {merchantRatings, productRatings}}.
 * All money is EUR floats, all timestamps epoch milliseconds (UTC).
 */
final readonly class PrototypeSnapshot
{
    /**
     * @var list<string>
     */
    private const array REQUIRED_LISTS = [
        'currencies', 'countries', 'brands', 'categories', 'ingredients', 'products',
        'merchants', 'offers', 'coupons', 'complianceRules',
    ];

    /**
     * @param  array<string, mixed>  $seed
     * @param  array<string, mixed>  $derived
     */
    private function __construct(
        public array $seed,
        public array $derived,
        public int $nowMs,
    ) {}

    /**
     * @throws PrototypeImportException
     */
    public static function fromFile(string $path): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw PrototypeImportException::unreadable($path);
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw PrototypeImportException::invalid($path, $exception->getMessage());
        }

        if (! is_array($decoded) || ! is_array($decoded['seed'] ?? null)) {
            throw PrototypeImportException::invalid($path, 'the "seed" object is missing.');
        }

        $seed = $decoded['seed'];

        if (! is_int($seed['NOW'] ?? null)) {
            throw PrototypeImportException::invalid($path, 'seed.NOW must be an epoch-millisecond integer.');
        }

        foreach (self::REQUIRED_LISTS as $key) {
            if (! is_array($seed[$key] ?? null)) {
                throw PrototypeImportException::invalid($path, "seed.{$key} must be a list.");
            }
        }

        $derived = is_array($decoded['derived'] ?? null) ? $decoded['derived'] : [];

        return new self($seed, $derived, $seed['NOW']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function records(string $key): array
    {
        $records = $this->seed[$key] ?? [];

        return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $values = $this->seed[$key] ?? [];

        return is_array($values) ? array_values(array_filter($values, 'is_string')) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function section(string $key): array
    {
        $section = $this->seed[$key] ?? [];

        return is_array($section) ? $section : [];
    }

    /**
     * Derived rating summary ({average, count}) for a merchant or product id.
     *
     * @param  'merchantRatings'|'productRatings'  $kind
     * @return array{average: float|null, count: int}
     */
    public function rating(string $kind, int $id): array
    {
        $summary = $this->derived[$kind][(string) $id] ?? null;

        if (! is_array($summary)) {
            return ['average' => null, 'count' => 0];
        }

        $count = (int) ($summary['count'] ?? 0);
        $average = $summary['average'] ?? null;

        return [
            'average' => $count > 0 && is_numeric($average) ? (float) $average : null,
            'count' => $count,
        ];
    }
}
