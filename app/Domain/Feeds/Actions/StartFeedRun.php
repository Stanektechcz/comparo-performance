<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\Exceptions\FeedRunAlreadyActive;
use App\Domain\Feeds\Exceptions\FeedRunNotAllowed;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\Pipeline\FeedRunPipeline;
use App\Domain\Feeds\Pipeline\FeedStorage;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\FeedMapping;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MatchingPolicy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Queues a feed run and dispatches its pipeline (§5).
 *
 * - Idempotent: a known idempotency key (`schedule:{source}:{slot}`,
 *   `manual:{uuid}`, `api:{uuid}`) returns the existing run and dispatches nothing.
 * - One non-terminal run per source: enforced by the partial unique index;
 *   a violation surfaces as {@see FeedRunAlreadyActive} with the active run id.
 * - Scheduled runs need an active source; manual/API runs a draft, active or
 *   error source; manual runs honour `comparo.feeds.manual_run_cooldown_minutes`.
 * - Upload transports run on a payload already stored in the merchant's
 *   directory of the private feeds disk ({@see StoreFeedUpload}).
 * - The run pins the current mapping and the active matching policy and
 *   carries the correlation id of the caller's Context (a new one if absent).
 * - Manual starts are audited as `feed_run.started_manually`.
 */
final class StartFeedRun
{
    public function __construct(
        private readonly FeedRunPipeline $pipeline,
        private readonly FeedStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws FeedRunAlreadyActive
     * @throws FeedRunNotAllowed
     */
    public function handle(
        FeedSource $source,
        FeedRunTrigger $trigger,
        ?FeedActor $actor = null,
        ?string $idempotencyKey = null,
        ?string $uploadedPayloadPath = null,
    ): FeedRun {
        $key = $idempotencyKey ?? $trigger->value.':'.Str::uuid()->toString();

        if ($key === '' || strlen($key) > 96) {
            throw new InvalidArgumentException('The idempotency key must have 1 to 96 characters.');
        }

        $existing = $this->byKey($source->id, $key);

        if ($existing !== null) {
            return $existing;
        }

        if ($trigger === FeedRunTrigger::Manual && ($actor === null || $actor->userId() === null)) {
            throw new InvalidArgumentException('A manual feed run needs the user who started it.');
        }

        $source = FeedSource::query()->findOrFail($source->id);
        $this->assertAllowed($source, $trigger);
        $payloadPath = $this->payloadPath($source, $uploadedPayloadPath);
        $this->assertNoActiveRun($source->id);

        if ($trigger === FeedRunTrigger::Manual) {
            $this->assertCooledDown($source->id);
        }

        try {
            $run = DB::transaction(fn (): FeedRun => $this->create($source, $trigger, $actor, $key, $payloadPath));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->byKey($source->id, $key) ?? throw $this->activeRunConflict($source->id, $exception);
        }

        $this->pipeline->dispatch($run);

        return $run;
    }

    private function create(FeedSource $source, FeedRunTrigger $trigger, ?FeedActor $actor, string $key, ?string $payloadPath): FeedRun
    {
        $run = FeedRun::query()->create([
            'feed_source_id' => $source->id,
            'merchant_id' => $source->merchant_id,
            'feed_mapping_id' => FeedMapping::query()->where('feed_source_id', $source->id)->where('is_current', true)->value('id'),
            'matching_policy_id' => MatchingPolicy::query()->active()->value('id'),
            'trigger' => $trigger,
            'triggered_by_user_id' => $actor?->userId(),
            'status' => FeedRunStatus::Queued,
            'idempotency_key' => $key,
            'correlation_id' => $this->correlationId(),
            'payload_path' => $payloadPath,
        ]);

        if ($trigger === FeedRunTrigger::Manual && $actor !== null) {
            $this->audit->record(AuditAction::FeedRunStartedManually, $actor->audit, $run, after: [
                'feed_source_id' => $source->id,
                'trigger' => $trigger->value,
                'uploaded_payload' => $payloadPath !== null,
            ]);
        }

        return $run;
    }

    private function assertAllowed(FeedSource $source, FeedRunTrigger $trigger): void
    {
        $allowed = $trigger === FeedRunTrigger::Schedule
            ? $source->status->isSchedulable()
            : $source->status->allowsManualRun();

        if (! $allowed) {
            throw FeedRunNotAllowed::forStatus($source->status, $trigger);
        }
    }

    private function payloadPath(FeedSource $source, ?string $uploadedPayloadPath): ?string
    {
        if ($source->transport->isFetched()) {
            if ($uploadedPayloadPath !== null) {
                throw new InvalidArgumentException('URL feeds fetch their own payload.');
            }

            return null;
        }

        if ($uploadedPayloadPath === null
            || ! $this->storage->belongsToMerchant($uploadedPayloadPath, $source->merchant_id)
            || ! $this->storage->exists($uploadedPayloadPath)) {
            throw FeedRunNotAllowed::missingPayload();
        }

        return $uploadedPayloadPath;
    }

    private function assertNoActiveRun(int $sourceId): void
    {
        $activeRunId = $this->activeRunId($sourceId);

        if ($activeRunId !== null) {
            throw new FeedRunAlreadyActive($activeRunId);
        }
    }

    private function assertCooledDown(int $sourceId): void
    {
        $cooldownMinutes = max(0, (int) config('comparo.feeds.manual_run_cooldown_minutes'));

        if ($cooldownMinutes === 0) {
            return;
        }

        $lastManualStart = FeedRun::query()
            ->where('feed_source_id', $sourceId)
            ->where('trigger', FeedRunTrigger::Manual->value)
            ->max('created_at');

        if ($lastManualStart === null) {
            return;
        }

        $availableAt = Date::parse((string) $lastManualStart)->addMinutes($cooldownMinutes);
        $now = Date::now();

        if ($availableAt->greaterThan($now)) {
            throw FeedRunNotAllowed::coolingDown(max(1, (int) ceil($now->diffInSeconds($availableAt))));
        }
    }

    private function activeRunConflict(int $sourceId, UniqueConstraintViolationException $exception): \Throwable
    {
        $activeRunId = $this->activeRunId($sourceId);

        return $activeRunId !== null ? new FeedRunAlreadyActive($activeRunId) : $exception;
    }

    private function activeRunId(int $sourceId): ?int
    {
        $id = FeedRun::query()->where('feed_source_id', $sourceId)->active()->value('id');

        return $id === null ? null : (int) $id;
    }

    private function byKey(int $sourceId, string $key): ?FeedRun
    {
        return FeedRun::query()->where('feed_source_id', $sourceId)->where('idempotency_key', $key)->first();
    }

    private function correlationId(): string
    {
        $correlationId = Context::get('correlation_id');

        if (is_string($correlationId) && $correlationId !== '') {
            return mb_substr($correlationId, 0, 64);
        }

        $correlationId = (string) Str::uuid7();
        Context::add('correlation_id', $correlationId);

        return $correlationId;
    }
}
