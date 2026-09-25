<?php

namespace App\Console\Commands;

use App\Domain\Feeds\Console\PruneFeedData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class FeedsPruneCommand extends Command
{
    protected $signature = 'comparo:feeds:prune';

    protected $description = 'Delete expired feed payload files and staged feed items';

    public function handle(PruneFeedData $pruner): int
    {
        $counts = $pruner->run(Date::now()->toImmutable());

        $this->info("Feed payloads purged: {$counts['payloads']}, staged items deleted: {$counts['items']}.");

        return self::SUCCESS;
    }
}
