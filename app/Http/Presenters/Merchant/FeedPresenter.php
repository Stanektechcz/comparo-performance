<?php

namespace App\Http\Presenters\Merchant;

use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\FeedTransport;
use App\Domain\Feeds\FeedUrlMask;
use App\Http\Requests\Merchant\Feeds\FeedSourceRequest;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Feed sources and runs for the merchant portal. Explicit whitelists only:
 * the URL is always masked ({@see FeedUrlMask}), credentials are reduced to
 * `hasCredentials`, payload paths, checksums, idempotency keys and
 * correlation ids never leave the server.
 */
final class FeedPresenter
{
    /**
     * @param  LengthAwarePaginator<int, FeedSource>  $sources
     * @return array<string, mixed>
     */
    public function index(LengthAwarePaginator $sources): array
    {
        /** @var list<FeedSource> $items */
        $items = array_values($sources->items());
        (new EloquentCollection($items))->loadMissing('country:id,code,name');

        return MerchantFormat::paginated($sources, fn (FeedSource $source): array => [
            ...$this->source($source),
            'latestRun' => $source->latestRun === null ? null : $this->run($source->latestRun),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function source(FeedSource $source): array
    {
        $mapping = $source->currentMapping;
        $country = $source->country;

        return [
            'id' => $source->id,
            'name' => $source->name,
            'format' => MerchantFormat::labelled($source->format->value, strtoupper($source->format->value)),
            'transport' => self::transport($source->transport),
            'status' => self::status($source->status),
            'statusReason' => self::statusReason($source),
            'maskedUrl' => FeedUrlMask::mask($source->url),
            'hasCredentials' => $source->hasCredentials(),
            'market' => $country === null ? null : ['code' => $country->code, 'name' => $country->name],
            'currency' => $source->currency,
            'intervalMinutes' => $source->interval_minutes,
            'lastRunAt' => MerchantFormat::date($source->last_run_at),
            'lastSuccessAt' => MerchantFormat::date($source->last_success_at),
            'nextRunAt' => MerchantFormat::date($source->next_run_at),
            'consecutiveFailures' => $source->consecutive_failures,
            'mapping' => $mapping === null ? null : [
                'version' => $mapping->version,
                'activatedAt' => MerchantFormat::date($mapping->activated_at),
                'mappedFields' => count($mapping->field_map),
            ],
        ];
    }

    /**
     * The edit form's defaults. The stored URL is NEVER sent (it may carry
     * tokens): the form shows the masked URL and an empty field.
     *
     * @return array<string, mixed>
     */
    public function formDefaults(FeedSource $source): array
    {
        $delimiter = array_search($source->delimiter, FeedSourceRequest::DELIMITERS, true);

        return [
            'name' => $source->name,
            'format' => $source->format->value,
            'transport' => $source->transport->value,
            'maskedUrl' => FeedUrlMask::mask($source->url),
            'currency' => $source->currency,
            'country' => $source->country?->code,
            'encoding' => $source->encoding,
            'delimiter' => is_string($delimiter) ? $delimiter : '',
            'recordElement' => $source->record_element ?? '',
            'intervalMinutes' => $source->interval_minutes,
            'hasCredentials' => $source->hasCredentials(),
        ];
    }

    /**
     * What the viewer may do with the source, and what its state allows.
     *
     * @return array{can: array<string, bool>, actions: array<string, bool>}
     */
    public function permissions(FeedSource $source, User $viewer): array
    {
        $gate = Gate::forUser($viewer);
        $manage = $gate->allows('update', $source);
        $activeRun = $source->latestRun !== null && ! $source->latestRun->isTerminal();
        $manualRunStatus = $source->status->allowsManualRun();

        return [
            'can' => [
                'update' => $manage,
                'run' => $gate->allows('run', $source),
                'manageCredentials' => $gate->allows('manageCredentials', $source),
            ],
            'actions' => [
                'pause' => $source->status === FeedSourceStatus::Active,
                'resume' => $source->status === FeedSourceStatus::Paused,
                'runNow' => $source->transport === FeedTransport::Url && $manualRunStatus && ! $activeRun,
                'upload' => $source->transport === FeedTransport::Upload && $manualRunStatus && ! $activeRun,
            ],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, FeedRun>  $runs
     * @return array<string, mixed>
     */
    public function runs(LengthAwarePaginator $runs): array
    {
        return MerchantFormat::paginated($runs, fn (FeedRun $run): array => $this->run($run));
    }

    /**
     * @return array<string, mixed>
     */
    public function run(FeedRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => self::runStatus($run->status),
            'outcome' => $run->outcome === null ? null : self::outcome($run->outcome),
            'trigger' => self::trigger($run->trigger),
            'isActive' => ! $run->isTerminal(),
            'isCancellable' => $run->status->isCancellable(),
            'createdAt' => MerchantFormat::date($run->created_at),
            'finishedAt' => MerchantFormat::date($run->finished_at),
            'durationMs' => $run->duration_ms,
            'rowsRead' => $run->rows_read,
            'rowsValid' => $run->rows_valid,
            'rowsInvalid' => $run->rows_invalid,
            'warnings' => $run->warnings,
            'errors' => $run->errors,
            'failure' => self::failure($run),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runDetail(FeedRun $run): array
    {
        return [
            ...$this->run($run),
            'payloadBytes' => $run->payload_bytes,
            'stages' => self::stages($run),
            'metrics' => self::metrics($run),
        ];
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function status(FeedSourceStatus $status): array
    {
        return MerchantFormat::labelled($status->value, match ($status) {
            FeedSourceStatus::Draft => 'Draft',
            FeedSourceStatus::Active => 'Active',
            FeedSourceStatus::Paused => 'Paused',
            FeedSourceStatus::Error => 'Needs attention',
            FeedSourceStatus::Disabled => 'Disabled by Comparo',
        });
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function transport(FeedTransport $transport): array
    {
        return MerchantFormat::labelled($transport->value, match ($transport) {
            FeedTransport::Url => 'Fetched from a URL',
            FeedTransport::Upload => 'File upload',
            FeedTransport::ApiPush => 'API push',
            FeedTransport::ManualUpload => 'Uploaded by Comparo',
        });
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function runStatus(FeedRunStatus $status): array
    {
        return MerchantFormat::labelled($status->value, match ($status) {
            FeedRunStatus::Queued => 'Queued',
            FeedRunStatus::Fetching => 'Fetching',
            FeedRunStatus::Parsing => 'Reading the file',
            FeedRunStatus::Normalizing => 'Checking rows',
            FeedRunStatus::Matching => 'Matching products',
            FeedRunStatus::Publishing => 'Publishing offers',
            FeedRunStatus::Completed => 'Completed',
            FeedRunStatus::Failed => 'Failed',
            FeedRunStatus::Cancelled => 'Cancelled',
        });
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function outcome(FeedRunOutcome $outcome): array
    {
        return MerchantFormat::labelled($outcome->value, match ($outcome) {
            FeedRunOutcome::Published => 'Published',
            FeedRunOutcome::PublishedWithWarnings => 'Published with warnings',
            FeedRunOutcome::Unchanged => 'Unchanged since the last run',
        });
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function trigger(FeedRunTrigger $trigger): array
    {
        return MerchantFormat::labelled($trigger->value, match ($trigger) {
            FeedRunTrigger::Manual => 'Started manually',
            FeedRunTrigger::Schedule => 'Scheduled',
            FeedRunTrigger::Api => 'API',
        });
    }

    /**
     * The status reason is either a FeedErrorCode (pipeline) or a staff marker.
     */
    private static function statusReason(FeedSource $source): ?string
    {
        $reason = $source->status_reason;

        if ($reason === null || $reason === '') {
            return null;
        }

        if ($source->status === FeedSourceStatus::Disabled) {
            return 'Comparo disabled this feed. Contact merchant support to re-enable it.';
        }

        return preg_match('/^[A-Z_]+$/', $reason) === 1 ? FeedMessages::message($reason, []) : null;
    }

    /**
     * @return array{code: string, message: string}|null
     */
    private static function failure(FeedRun $run): ?array
    {
        if ($run->failure_code === null) {
            return null;
        }

        return [
            'code' => $run->failure_code,
            // failure_reason is the translated message the pipeline stored (no internals).
            'message' => $run->failure_reason ?? FeedMessages::message($run->failure_code, []),
        ];
    }

    /**
     * @return list<array{key: string, label: string, at: ?string}>
     */
    private static function stages(FeedRun $run): array
    {
        $stages = [
            'queued' => ['Queued', $run->created_at],
            'started' => ['Started', $run->started_at],
            'fetched' => ['File received', $run->fetched_at],
            'parsed' => ['File read', $run->parsed_at],
            'normalized' => ['Rows checked', $run->normalized_at],
            'matched' => ['Products matched', $run->matched_at],
            'published' => ['Offers published', $run->published_at],
            'finished' => ['Finished', $run->finished_at],
        ];

        $timeline = [];

        foreach ($stages as $key => [$label, $at]) {
            $timeline[] = ['key' => $key, 'label' => $label, 'at' => MerchantFormat::date($at)];
        }

        return $timeline;
    }

    /**
     * @return list<array{title: string, items: list<array{key: string, label: string, value: int}>}>
     */
    private static function metrics(FeedRun $run): array
    {
        $groups = [
            'Rows' => ['rows_read' => 'Read', 'rows_valid' => 'Valid', 'rows_invalid' => 'Rejected'],
            'Matching' => ['rows_matched' => 'Matched', 'rows_suggested' => 'Awaiting review', 'rows_unmatched' => 'Unmatched', 'rows_compliance_hold' => 'Compliance hold'],
            'Offers' => ['offers_created' => 'Created', 'offers_updated' => 'Updated', 'offers_unchanged' => 'Unchanged', 'offers_deactivated' => 'Deactivated', 'offers_reactivated' => 'Reactivated'],
            'Signals' => ['price_changes' => 'Price changes', 'anomalies' => 'Anomalies', 'warnings' => 'Warnings', 'errors' => 'Errors'],
        ];

        $result = [];

        foreach ($groups as $title => $metrics) {
            $items = [];

            foreach ($metrics as $column => $label) {
                $items[] = ['key' => $column, 'label' => $label, 'value' => (int) $run->getAttribute($column)];
            }

            $result[] = ['title' => $title, 'items' => $items];
        }

        return $result;
    }
}
