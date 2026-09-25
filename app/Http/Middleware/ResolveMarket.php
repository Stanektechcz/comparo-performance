<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Platform\Markets\MarketResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the request's MarketContext (query ?market= → market cookie → default).
 */
class ResolveMarket
{
    public function __construct(private readonly MarketResolver $markets) {}

    public function handle(Request $request, Closure $next): Response
    {
        $market = $this->markets->resolve(
            $request->query('market'),
            $request->cookie((string) config('comparo.market_cookie')),
        );

        app()->instance(MarketContext::class, $market);
        Context::add('market', $market->code);

        return $next($request);
    }
}
