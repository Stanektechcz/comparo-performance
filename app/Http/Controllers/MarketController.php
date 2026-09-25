<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMarketRequest;
use Illuminate\Http\RedirectResponse;

class MarketController extends Controller
{
    private const int ONE_YEAR_IN_MINUTES = 525_600;

    private const string DEFAULT_COOKIE_NAME = 'comparo_market';

    public function __invoke(UpdateMarketRequest $request): RedirectResponse
    {
        $configured = config('comparo.market_cookie');
        $name = is_string($configured) && $configured !== '' ? $configured : self::DEFAULT_COOKIE_NAME;

        return back()->withCookie(cookie(
            name: $name,
            value: $request->validated('market'),
            minutes: self::ONE_YEAR_IN_MINUTES,
            sameSite: 'lax',
        ));
    }
}
