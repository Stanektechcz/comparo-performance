<?php

namespace App\Http\Presenters;

use App\Domain\Platform\Markets\MarketContext;
use App\Domain\Platform\Markets\MarketResolver;

final class MarketPresenter
{
    public function __construct(private readonly MarketResolver $markets) {}

    /**
     * @return array{code: string, name: string, currency: string, locale: string, options: list<array{code: string, name: string, currency: string}>}
     */
    public function shared(MarketContext $market): array
    {
        return [
            ...$market->toArray(),
            'options' => array_values(array_map(
                static fn (MarketContext $option): array => ['code' => $option->code, 'name' => $option->name, 'currency' => $option->currency],
                $this->markets->all(),
            )),
        ];
    }
}
