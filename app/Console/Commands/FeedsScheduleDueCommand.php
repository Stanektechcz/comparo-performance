<?php

namespace App\Console\Commands;

use App\Domain\Feeds\Console\ScheduleDueFeeds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class FeedsScheduleDueCommand extends Command
{
    protected $signature = 'comparo:feeds:schedule-due';

    protected $description = 'Start the scheduled run of every active feed source that is due';

    public function handle(ScheduleDueFeeds $scheduler): int
    {
        $counts = $scheduler->run(Date::now()->toImmutable());

        $this->info("Feed runs started: {$counts['started']}, skipped: {$counts['skipped']}.");

        return self::SUCCESS;
    }
}
