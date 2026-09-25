<?php

namespace App\Http\Presenters\Merchant;

use App\Domain\Feeds\FeedErrorSeverity;
use App\Domain\Feeds\Queries\FeedErrorGroup;
use App\Models\FeedError;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * A run's errors for the merchant: groups with the translated, actionable
 * message and example rows, and the paginated error rows. Messages are
 * rendered from the code + stored params only (no free text is stored).
 */
final class FeedErrorPresenter
{
    /**
     * @param  list<FeedErrorGroup>  $groups
     * @return list<array<string, mixed>>
     */
    public function groups(array $groups): array
    {
        return array_map(static fn (FeedErrorGroup $group): array => [
            'code' => $group->code,
            'severity' => self::severity($group->severity),
            'count' => $group->count,
            'capped' => $group->capped,
            'message' => FeedMessages::message($group->code, $group->samples[0]['params'] ?? []),
            'samples' => array_map(static fn (array $sample): array => [
                'rowNumber' => $sample['row_number'],
                'sku' => $sample['merchant_sku'],
                'field' => FeedMessages::fieldLabel($sample['field']),
                'message' => FeedMessages::message($group->code, $sample['params']),
            ], $group->samples),
        ], $groups);
    }

    /**
     * @param  LengthAwarePaginator<int, FeedError>  $rows
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function rows(LengthAwarePaginator $rows, array $query = []): array
    {
        /** @var list<FeedError> $items */
        $items = array_values($rows->items());
        (new EloquentCollection($items))->loadMissing('item:id,merchant_sku');

        return MerchantFormat::paginated($rows, static fn (FeedError $error): array => [
            'id' => $error->id,
            'rowNumber' => $error->row_number,
            'sku' => $error->item?->merchant_sku,
            'code' => $error->code,
            'severity' => self::severity($error->severity),
            'field' => FeedMessages::fieldLabel($error->field),
            'message' => FeedMessages::message($error->code, $error->message_params),
        ], $query);
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function severity(FeedErrorSeverity $severity): array
    {
        return MerchantFormat::labelled($severity->value, match ($severity) {
            FeedErrorSeverity::Fatal => 'Run stopped',
            FeedErrorSeverity::Error => 'Row skipped',
            FeedErrorSeverity::Warning => 'Warning',
            FeedErrorSeverity::Info => 'Info',
        });
    }
}
