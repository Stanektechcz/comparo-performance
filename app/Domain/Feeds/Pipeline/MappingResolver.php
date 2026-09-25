<?php

namespace App\Domain\Feeds\Pipeline;

use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Mapping\FieldMapping;
use App\Models\FeedMapping;

/**
 * The field mapping a run uses: the version pinned on the run (or the source's
 * current one for previews), else a suggestion from the first record's keys.
 */
final class MappingResolver
{
    public function load(?int $mappingId): ?FieldMapping
    {
        if ($mappingId === null) {
            return null;
        }

        $fieldMap = FeedMapping::query()->whereKey($mappingId)->value('field_map');

        return $fieldMap === null ? null : $this->fromStored($fieldMap);
    }

    public function current(int $feedSourceId): ?FieldMapping
    {
        $id = FeedMapping::query()->where('feed_source_id', $feedSourceId)->where('is_current', true)->value('id');

        return $this->load($id === null ? null : (int) $id);
    }

    /**
     * @param  list<string>  $headers
     */
    public function resolve(?FieldMapping $pinned, array $headers): FieldMapping
    {
        return $pinned ?? FieldMapping::suggest($headers);
    }

    /**
     * Stored maps are decoded leniently: keys that are not canonical fields and
     * blank sources are ignored, so a legacy key can never crash a run.
     */
    private function fromStored(mixed $fieldMap): FieldMapping
    {
        $decoded = is_string($fieldMap) ? json_decode($fieldMap, true) : $fieldMap;
        $valid = [];

        foreach (is_array($decoded) ? $decoded : [] as $field => $source) {
            if (FeedField::tryFrom((string) $field) !== null && is_string($source) && trim($source) !== '') {
                $valid[(string) $field] = $source;
            }
        }

        return FieldMapping::fromArray($valid);
    }
}
