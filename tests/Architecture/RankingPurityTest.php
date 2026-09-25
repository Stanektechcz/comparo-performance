<?php

use App\Domain\Offers\Ranking\RankingContext;
use App\Domain\Offers\Ranking\RankingFactor;
use App\Domain\Offers\Ranking\RankingService;
use App\Domain\Offers\Ranking\RankingWeights;
use Illuminate\Support\Facades\Schema;

/**
 * Invariant: commercial money never buys organic position (docs/adr/0004).
 */
const COMMERCIAL_TERMS = '/commission|affiliate|subscription|plan|tier|partner|sponsor|campaign|spend|budget|revenue|commercial|bid|placement|payout|invoice/i';

it('keeps every commercial input out of the ranking context', function () {
    $fields = array_map(
        static fn (ReflectionParameter $parameter): string => $parameter->getName(),
        (new ReflectionMethod(RankingContext::class, '__construct'))->getParameters(),
    );

    expect($fields)->not->toBeEmpty()
        ->and(preg_grep(COMMERCIAL_TERMS, $fields))->toBe([]);
});

it('has no commercial ranking factor', function () {
    $factors = array_merge(
        array_map(static fn (RankingFactor $factor): string => $factor->value, RankingFactor::cases()),
        array_map(static fn (RankingFactor $factor): string => $factor->name, RankingFactor::cases()),
    );

    expect(preg_grep(COMMERCIAL_TERMS, $factors))->toBe([]);
});

it('rejects weights for factors outside the closed factor set', function () {
    RankingWeights::fromArray('tampered', ['price' => 30, 'commission' => 50]);
})->throws(InvalidArgumentException::class, 'Unknown ranking factor [commission]');

it('commercial_spend_does_not_change_organic_rank', function () {
    // ComparoRank can only ever see the context, the versioned weights and the evaluation time.
    $parameterTypes = array_map(
        static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
        (new ReflectionMethod(RankingService::class, 'rank'))->getParameters(),
    );

    expect($parameterTypes)->toBe([RankingContext::class, RankingWeights::class, DateTimeImmutable::class]);

    // The tables the ranking inputs are read from carry no commercial columns.
    foreach (['merchants', 'offers', 'merchant_trust_signals', 'merchant_shipping_zones', 'coupons', 'products'] as $table) {
        expect(preg_grep(COMMERCIAL_TERMS, Schema::getColumnListing($table)))->toBe([], "table {$table}");
    }
});

arch('ranking never depends on commercial, affiliate or persistence code')
    ->expect('App\Domain\Offers\Ranking')
    ->not->toUse([
        'App\Domain\Commercial',
        'App\Domain\Affiliate',
        'App\Models',
        'Illuminate\Database',
        'Illuminate\Support\Facades',
    ]);
