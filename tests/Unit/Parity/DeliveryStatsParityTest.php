<?php

use App\Domain\Orders\Delivery\DeliveryPolicy;
use Tests\Support\PrototypeFixtures;

/**
 * Measured delivery parity: every seed shop in every market it serves, every
 * shop over all markets, and the synthetic boundary sets (minimum sample
 * 7/8, even/odd medians, the p90 index, half-up rounding, returned/disputed
 * orders in the total) must equal the prototype exactly. The "too few"
 * sentence is presentation copy and is not part of the contract.
 */
it('reproduces the measured delivery of every shop, market and synthetic set', function () {
    $fixture = PrototypeFixtures::load('delivery');
    $cases = PrototypeFixtures::deliveryCases(DeliveryPolicy::prototype());

    expect($fixture['cases'])->toHaveCount(155)
        ->and($fixture['allMarkets'])->toHaveCount(count(PrototypeFixtures::seed()['merchants']))
        ->and($fixture['synthetic'])->toHaveCount(19)
        ->and($cases)->toHaveCount(155 + 13 + 19);

    $mismatches = array_filter($cases, static fn (array $case): bool => $case['actual'] !== $case['expected']);

    expect(array_slice($mismatches, 0, 10, true))
        ->toBe([], sprintf('%d of %d delivery cases differ from the prototype', count($mismatches), count($cases)));
});

it('measures seed shops on both sides of the minimum sample', function () {
    $stats = array_column([...PrototypeFixtures::load('delivery')['cases'], ...PrototypeFixtures::load('delivery')['allMarkets']], 'stats');

    expect(array_filter($stats, static fn (array $record): bool => $record['enough']))->not->toBeEmpty()
        ->and(array_filter($stats, static fn (array $record): bool => ! $record['enough']))->not->toBeEmpty();
});
