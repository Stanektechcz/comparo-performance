<?php

namespace App\Console\Commands;

use App\Domain\Search\Console\SearchReindexer;
use App\Domain\Search\Contracts\SearchIndex;
use App\Domain\Search\SearchEntityType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class SearchReindexCommand extends Command
{
    protected $signature = 'comparo:search:reindex
        {entity? : product, brand, merchant, category or ingredient (plural index names accepted); all when omitted}
        {--chunk=200 : entities per build/upsert batch (1-1000)}';

    protected $description = 'Rebuild search indexes into <index>_tmp and swap them in atomically';

    public function handle(SearchReindexer $reindexer): int
    {
        $entity = $this->argument('entity');
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);

        if ($chunk === false || $chunk < 1 || $chunk > SearchReindexer::MAX_CHUNK) {
            $this->error('The --chunk option must be an integer between 1 and '.SearchReindexer::MAX_CHUNK.'.');

            return self::INVALID;
        }

        $indexes = $entity === null ? SearchIndex::cases() : self::index((string) $entity);

        if ($indexes === []) {
            $this->error('Unknown entity. Use one of: '.implode(', ', array_map(static fn (SearchEntityType $type): string => $type->value, SearchEntityType::cases())).'.');

            return self::INVALID;
        }

        $now = Date::now()->toImmutable();

        foreach ($indexes as $index) {
            $report = $reindexer->reindex($index, $chunk, $now);
            $this->info("Reindexed {$index->value}: {$report->upserted} documents.");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<SearchIndex>
     */
    private static function index(string $entity): array
    {
        $index = SearchIndex::tryFrom($entity) ?? (($type = SearchEntityType::tryFrom($entity)) === null ? null : SearchIndex::forEntity($type));

        return $index === null ? [] : [$index];
    }
}
