<?php

namespace App\Http\Requests\Admin\Catalogue;

/**
 * The tabs of the staff matching console (`?tab=`).
 */
enum MatchingQueueTab: string
{
    case Listings = 'listings';
    case Conflicts = 'conflicts';
    case Candidates = 'candidates';
    case History = 'history';

    public function label(): string
    {
        return match ($this) {
            self::Listings => 'Listings to review',
            self::Conflicts => 'Open conflicts',
            self::Candidates => 'New-product proposals',
            self::History => 'Decision history',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $tab): string => $tab->value, self::cases());
    }
}
