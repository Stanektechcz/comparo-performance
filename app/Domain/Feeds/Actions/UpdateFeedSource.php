<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditChanges;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\FeedSource;
use Illuminate\Support\Facades\DB;

/**
 * Updates a feed source's settings (not its status, mapping or credentials,
 * which have their own audited actions). Audits the diff with the URL masked;
 * a no-op update writes nothing.
 */
final class UpdateFeedSource
{
    /** @var list<string> */
    private const array PARSE_SETTINGS = ['format', 'encoding', 'delimiter', 'record_element', 'currency', 'availability_map', 'url', 'transport'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(FeedSource $source, FeedSourceData $data, FeedActor $actor): FeedSource
    {
        $attributes = FeedSourceAttributes::fromData($data);

        return DB::transaction(function () use ($source, $attributes, $actor): FeedSource {
            $locked = FeedSource::query()->lockForUpdate()->findOrFail($source->id);
            $locked->fill($attributes);

            if (! $locked->isDirty()) {
                return $locked;
            }

            $changes = AuditChanges::fromModel($locked);

            // New parse settings must re-process even a byte-identical payload.
            if ($locked->isDirty(self::PARSE_SETTINGS)) {
                $locked->last_checksum = null;
            }

            $locked->save();

            $this->audit->record(
                AuditAction::FeedSourceUpdated,
                $actor->audit,
                $locked,
                FeedSourceAttributes::auditable($changes['before']),
                FeedSourceAttributes::auditable($changes['after']),
            );

            return $locked;
        });
    }
}
