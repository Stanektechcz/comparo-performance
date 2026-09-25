<?php

namespace App\Http\Presenters;

use App\Domain\Shared\Money;

final class MoneyPresenter
{
    /**
     * @return array{minor: int, currency: string}|null
     */
    public static function present(?Money $money): ?array
    {
        return $money === null ? null : ['minor' => $money->minor, 'currency' => $money->currency];
    }

    /**
     * Decimal string for machine-readable contexts (JSON-LD), e.g. 3576 → "35.76".
     */
    public static function decimal(Money $money): string
    {
        $sign = $money->minor < 0 ? '-' : '';
        $absolute = abs($money->minor);

        return sprintf('%s%d.%02d', $sign, intdiv($absolute, 100), $absolute % 100);
    }
}
