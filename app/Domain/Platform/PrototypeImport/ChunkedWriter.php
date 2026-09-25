<?php

namespace App\Domain\Platform\PrototypeImport;

use Illuminate\Support\Facades\DB;

/**
 * Batched query-builder writes (≈500 rows per statement) so large series
 * never build one giant statement or hold every row in memory.
 */
final class ChunkedWriter
{
    public const int CHUNK_SIZE = 500;

    /**
     * @var list<array<string, mixed>>
     */
    private array $buffer = [];

    private int $written = 0;

    private function __construct(private readonly string $table) {}

    public static function into(string $table): self
    {
        return new self($table);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function add(array $row): void
    {
        $this->buffer[] = $row;

        if (count($this->buffer) >= self::CHUNK_SIZE) {
            $this->flush();
        }
    }

    /**
     * @return int rows written in total
     */
    public function flush(): int
    {
        if ($this->buffer !== []) {
            DB::table($this->table)->insert($this->buffer);
            $this->written += count($this->buffer);
            $this->buffer = [];
        }

        return $this->written;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  non-empty-list<non-empty-string>  $uniqueBy
     * @param  list<string>|null  $update  columns to overwrite on conflict (default: all but the keys and created_at)
     */
    public static function upsert(string $table, array $rows, array $uniqueBy, ?array $update = null): void
    {
        if ($rows === []) {
            return;
        }

        $update ??= array_values(array_diff(array_keys($rows[0]), [...$uniqueBy, 'created_at']));

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $update);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function insertOrIgnore(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            DB::table($table)->insertOrIgnore($chunk);
        }
    }
}
