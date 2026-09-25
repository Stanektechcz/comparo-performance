<?php

namespace App\Console\Commands;

use App\Domain\Platform\PrototypeIntegrity\PrototypeManifest;
use Illuminate\Console\Command;
use InvalidArgumentException;

class VerifyPrototypeCommand extends Command
{
    protected $signature = 'comparo:verify-prototype {--manifest='.PrototypeManifest::DEFAULT_PATH.' : Manifest path relative to the project root}';

    protected $description = 'Fail when any protected prototype specification file is missing or changed';

    public function handle(): int
    {
        try {
            $manifest = PrototypeManifest::fromFile(base_path((string) $this->option('manifest')));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $report = $manifest->verify(base_path());

        foreach ($report->missing as $path) {
            $this->error("MISSING  {$path}");
        }

        foreach ($report->changed as $path) {
            $this->error("CHANGED  {$path}");
        }

        if (! $report->intact()) {
            $this->error("Prototype integrity violated: {$report->verified()}/{$report->protected} files intact.");

            return self::FAILURE;
        }

        $this->info("Prototype intact: {$report->verified()}/{$report->protected} protected files verified.");

        return self::SUCCESS;
    }
}
