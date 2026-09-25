<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\FeedSource;
use Illuminate\Support\Facades\DB;

/**
 * Creates a merchant feed source in `draft`. It becomes active after its
 * first successful run; it is never scheduled before that.
 */
final class CreateFeedSource
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(int $merchantId, FeedSourceData $data, FeedActor $actor): FeedSource
    {
        $attributes = FeedSourceAttributes::fromData($data);

        return DB::transaction(function () use ($merchantId, $attributes, $actor): FeedSource {
            $source = FeedSource::query()->create([
                ...$attributes,
                'merchant_id' => $merchantId,
                'status' => FeedSourceStatus::Draft,
                'consecutive_failures' => 0,
            ]);

            $this->audit->record(AuditAction::FeedSourceCreated, $actor->audit, $source, after: [
                'merchant_id' => $merchantId,
                'status' => FeedSourceStatus::Draft->value,
                ...FeedSourceAttributes::auditable($attributes),
            ]);

            return $source;
        });
    }
}
