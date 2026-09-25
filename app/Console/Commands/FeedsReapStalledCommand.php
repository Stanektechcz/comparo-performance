<?php

namespace App\Console\Commands;

use App\Domain\Feeds\Console\ReapStalledFeedRuns;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class FeedsReapStalledCommand extends Command
{
    protected $signature = 'comparo:feeds:reap-stalled';

    protected $description = 'Fail feed runs stuck in one stage beyond its deadline as STALLED';

    public function handle(ReapStalledFeedRuns $reaper): int
    {
        $reaped = $reaper->run(Date::now()->toImmutable());

        $this->info("Stalled feed runs failed: {$reaped}.");

        return self::SUCCESS;
    }
}
