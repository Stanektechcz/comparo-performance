<?php

namespace App\Console\Commands;

use App\Domain\Search\Analytics\PruneSearchAnalytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class SearchAnalyticsPruneCommand extends Command
{
    protected $signature = 'comparo:search:prune-analytics';

    protected $description = 'Null expired search session hashes and delete search analytics rows past retention';

    public function handle(PruneSearchAnalytics $pruner): int
    {
        $counts = $pruner->run(Date::now()->toImmutable());

        $this->info("Session hashes nulled: {$counts['sessions_nulled']}, searches deleted: {$counts['searches_deleted']}, clicks deleted: {$counts['clicks_deleted']}.");

        return self::SUCCESS;
    }
}
