<?php

use App\Domain\Orders\Delivery\DeliveryPolicy;
use Tests\Support\PrototypeFixtures;

/**
 * Proves the delivery parity suite can fail: moving the minimum sample or
 * the reported percentile must break fixture cases.
 */
function countChangedDeliveryCases(DeliveryPolicy $policy): int
{
    return count(array_filter(
        PrototypeFixtures::deliveryCases($policy),
        static fn (array $case): bool => $case['actual'] !== $case['expected'],
    ));
}

it('passes unchanged with the prototype policy', function () {
    expect(countChangedDeliveryCases(DeliveryPolicy::prototype()))->toBe(0);
});

it('breaks delivery cases when the policy moves', function (array $overrides) {
    expect(countChangedDeliveryCases(DeliveryPolicy::prototype()->with($overrides)))->toBeGreaterThan(0);
})->with([
    'minimum sample 7' => [['minSample' => 7]],
    'minimum sample 9' => [['minSample' => 9]],
    'percentile 0.85' => [['percentile' => 0.85]],
    'percentile 0.95' => [['percentile' => 0.95]],
]);
