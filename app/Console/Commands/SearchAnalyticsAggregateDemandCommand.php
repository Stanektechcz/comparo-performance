<?php

namespace App\Console\Commands;

use App\Domain\Search\Analytics\AggregateSearchDemand;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class SearchAnalyticsAggregateDemandCommand extends Command
{
    protected $signature = 'comparo:search:aggregate-demand {--date= : UTC day to aggregate (Y-m-d); defaults to yesterday}';

    protected $description = 'Aggregate non-bot search-page queries of one UTC day into search_demand_daily';

    public function handle(AggregateSearchDemand $aggregator): int
    {
        $option = $this->option('date');
        $day = Date::now('UTC')->subDay()->toImmutable();

        if (is_string($option) && $option !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $option, new DateTimeZone('UTC'));

            if ($parsed === false || $parsed->format('Y-m-d') !== $option) {
                $this->error('The --date option takes a Y-m-d day.');

                return self::INVALID;
            }

            $day = $parsed;
        }

        try {
            $rows = $aggregator->run($day);
        } catch (Exception $exception) {
            report($exception);
            $this->error('Aggregation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Search demand for {$day->format('Y-m-d')}: {$rows} market/query rows.");

        return self::SUCCESS;
    }
}
