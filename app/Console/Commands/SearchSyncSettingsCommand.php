<?php

namespace App\Console\Commands;

use App\Domain\Search\Console\SettingsSynchronizer;
use Illuminate\Console\Command;

class SearchSyncSettingsCommand extends Command
{
    protected $signature = 'comparo:search:sync-settings {--force : apply even when the applied settings fingerprint matches}';

    protected $description = 'Apply the code-defined search index settings when their version or inputs changed';

    public function handle(SettingsSynchronizer $synchronizer): int
    {
        $outcomes = $synchronizer->sync((bool) $this->option('force'));
        $rows = [];

        foreach ($outcomes as $index => $outcome) {
            $rows[] = [$index, $outcome['version'], $outcome['status']];
        }

        $this->table(['Index', 'Version', 'Status'], $rows);

        return self::SUCCESS;
    }
}
