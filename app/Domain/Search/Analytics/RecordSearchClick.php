<?php

namespace App\Domain\Search\Analytics;

use App\Domain\Search\Events\SearchResultClicked;
use App\Domain\Search\Local\SearchableType;
use App\Domain\Search\SearchEntityType;
use App\Models\SearchClick;
use App\Models\SearchQuery as SearchQueryRecord;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Attributes a result click to a recorded search. A click is accepted only
 * when the search exists, is at most `clickWindowMinutes` old, came from the
 * same browser session (its session hash recomputed for the search's UTC
 * day matches) and listed exactly that entity at that position. The first
 * accepted click per (search, entity) is stored; repeats are ignored.
 * Returns whether a click was stored — callers never reveal why not.
 */
final readonly class RecordSearchClick
{
    public function __construct(
        private SessionHasher $sessions,
        private AnalyticsSettings $settings,
    ) {}

    public function record(SearchClickInput $click): bool
    {
        $type = SearchableType::tryFrom($click->entityType);
        $search = SearchQueryRecord::query()->find($click->searchId);

        if ($type === null || $search === null || ! $this->isAttributable($search, $type, $click)) {
            return false;
        }

        $entity = self::entityType($type);

        if (SearchClick::query()->where('search_id', $search->search_id)->where('entity_type', $entity->value)->where('entity_id', $click->entityId)->exists()) {
            return false;
        }

        try {
            SearchClick::query()->create([
                'search_id' => $search->search_id,
                'entity_type' => $entity,
                'entity_id' => $click->entityId,
                'position' => $click->position,
                'clicked_at' => $click->clickedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        event(new SearchResultClicked($search->search_id, $entity->value, $click->entityId, $click->position));

        return true;
    }

    public static function entityType(SearchableType $type): SearchEntityType
    {
        return match ($type) {
            SearchableType::Product => SearchEntityType::Product,
            SearchableType::Brand => SearchEntityType::Brand,
            SearchableType::Shop => SearchEntityType::Merchant,
            SearchableType::Category => SearchEntityType::Category,
            SearchableType::Ingredient => SearchEntityType::Ingredient,
        };
    }

    private function isAttributable(SearchQueryRecord $search, SearchableType $type, SearchClickInput $click): bool
    {
        $occurredAt = $search->occurred_at->toImmutable();
        $age = $click->clickedAt->getTimestamp() - $occurredAt->getTimestamp();

        if ($age < 0 || $age > $this->settings->clickWindowMinutes * 60) {
            return false;
        }

        $sessionHash = $this->sessions->hash($click->sessionId, $occurredAt);

        if ($search->session_hash === null || $sessionHash === null || ! hash_equals($search->session_hash, $sessionHash)) {
            return false;
        }

        $ref = ($search->result_refs ?? [])[$click->position - 1] ?? null;

        return is_array($ref)
            && ($ref['type'] ?? null) === $type->value
            && (int) ($ref['id'] ?? 0) === $click->entityId;
    }
}
