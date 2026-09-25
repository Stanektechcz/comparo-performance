<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\Mapping\FieldMapping;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\FeedMapping;
use App\Models\FeedSource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Saves a field mapping as a new immutable version and makes it current.
 *
 * The source row is locked so versions are assigned serially; the previous
 * current version is unflagged before the new one is inserted (partial unique
 * index `feed_mappings_single_current`). Saving a mapping identical to the
 * current one returns the current version and writes nothing.
 */
final class SaveFeedMapping
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(FeedSource $source, FieldMapping $mapping, FeedActor $actor, ?string $notes = null): FeedMapping
    {
        $fieldMap = $mapping->toArray();

        return DB::transaction(function () use ($source, $fieldMap, $actor, $notes): FeedMapping {
            $locked = FeedSource::query()->lockForUpdate()->findOrFail($source->id);
            $current = FeedMapping::query()->where('feed_source_id', $locked->id)->where('is_current', true)->first();

            if ($current !== null && $current->field_map === $fieldMap) {
                return $current;
            }

            $current?->update(['is_current' => false]);

            $created = FeedMapping::query()->create([
                'feed_source_id' => $locked->id,
                'merchant_id' => $locked->merchant_id,
                'version' => (int) FeedMapping::query()->where('feed_source_id', $locked->id)->max('version') + 1,
                'field_map' => $fieldMap,
                'is_current' => true,
                'activated_at' => Date::now(),
                'created_by_user_id' => $actor->userId(),
                'notes' => $notes,
            ]);

            // A new mapping must re-process even a byte-identical payload.
            $locked->forceFill(['last_checksum' => null])->save();

            $this->audit->record(
                AuditAction::FeedSourceMappingChanged,
                $actor->audit,
                $locked,
                $current === null ? [] : ['version' => $current->version, 'field_map' => $current->field_map],
                ['version' => $created->version, 'field_map' => $fieldMap],
            );

            return $created;
        });
    }
}
