<?php

namespace App\Domain\Search\Indexing;

use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\Engines\IndexNames;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

/**
 * Coordinates a full rebuild (SearchReindexer) with the outbox
 * (docs/architecture/phase-3-search.md §5), so a change indexed into the
 * live index while `<index>_tmp` is being filled — a compliance block, say —
 * is never lost at the swap:
 *
 * 1. `begin()` sets the marker (index → tmp name + watermark) once the twin
 *    is empty and configured;
 * 2. while it exists, DocumentIndexer writes every live change to the live
 *    index AND the twin, and first `record()`s the entity ids under the
 *    rebuild id;
 * 3. `finish()` (right after the swap) removes the marker and returns the
 *    recorded ids, which the reindexer re-enqueues: a rebuild chunk BUILT
 *    before a change but WRITTEN after its dual write is stale, and only a
 *    fresh outbox run after the swap corrects it.
 *
 * Ids are recorded before the dual write, so every write that can precede a
 * stale chunk is covered by the set `finish()` reads. The marker lives in
 * the shared cache (keyed by driver and physical index) with a TTL
 * (`comparo.search.indexing.rebuild_marker_ttl_seconds`), so a rebuild
 * killed without cleanup stops dual writes eventually; dual writes into a
 * twin that is no longer rebuilt are harmless (the next rebuild flushes it).
 * Recording is append-only (an atomic counter plus one entry per call), so
 * concurrent writers never lose each other's ids. One rebuild per index at
 * a time.
 */
final readonly class RebuildMarker
{
    public const int DEFAULT_TTL_SECONDS = 21_600;

    public function __construct(
        private Repository $cache,
        private IndexNames $names,
    ) {}

    public function begin(SearchIndex $index, DateTimeImmutable $watermark): Rebuild
    {
        $rebuild = new Rebuild($index, (string) Str::ulid(), $index->temporary(), $watermark->format(DateTimeInterface::ATOM));

        $this->cache->put($this->counterKey($rebuild->id), 0, self::ttl());
        $this->cache->put($this->markerKey($index), ['id' => $rebuild->id, 'temporary' => $rebuild->temporary, 'watermark' => $rebuild->watermark], self::ttl());

        return $rebuild;
    }

    public function active(SearchIndex $index): ?Rebuild
    {
        $marker = $this->cache->get($this->markerKey($index));

        if (! is_array($marker) || ! isset($marker['id'], $marker['temporary'], $marker['watermark'])) {
            return null;
        }

        return new Rebuild($index, (string) $marker['id'], (string) $marker['temporary'], (string) $marker['watermark']);
    }

    /**
     * Notes entity ids the outbox is about to write during the rebuild.
     *
     * @param  list<int>  $ids
     */
    public function record(Rebuild $rebuild, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $entry = $this->cache->increment($this->counterKey($rebuild->id));

        if (! is_int($entry) || $entry < 1) {
            // The rebuild finished (or expired) meanwhile: the write lands
            // after the swap, directly in the new live index.
            return;
        }

        $this->cache->put($this->entryKey($rebuild->id, $entry), $ids, self::ttl());
    }

    /**
     * Removes the marker and returns every recorded id (unique, ascending).
     *
     * @return list<int>
     */
    public function finish(Rebuild $rebuild): array
    {
        if ($this->active($rebuild->index)?->id === $rebuild->id) {
            $this->cache->forget($this->markerKey($rebuild->index));
        }

        // The counter keeps its TTL: a writer that records after this read
        // only adds an entry that expires with it (its write lands after the swap).
        $entries = (int) $this->cache->get($this->counterKey($rebuild->id), 0);
        $ids = [];

        for ($entry = 1; $entry <= $entries; $entry++) {
            $recorded = $this->cache->get($this->entryKey($rebuild->id, $entry));
            $this->cache->forget($this->entryKey($rebuild->id, $entry));

            foreach (is_array($recorded) ? $recorded : [] as $id) {
                $ids[(int) $id] = true;
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }

    private function markerKey(SearchIndex $index): string
    {
        return 'comparo:search:rebuild:'.config('scout.driver').':'.$this->names->live($index);
    }

    private function counterKey(string $rebuildId): string
    {
        return "comparo:search:rebuild:{$rebuildId}:entries";
    }

    private function entryKey(string $rebuildId, int $entry): string
    {
        return "comparo:search:rebuild:{$rebuildId}:entry:{$entry}";
    }

    private static function ttl(): int
    {
        return max(60, (int) config('comparo.search.indexing.rebuild_marker_ttl_seconds', self::DEFAULT_TTL_SECONDS));
    }
}
