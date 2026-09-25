<?php

use App\Domain\Merchants\Risk\RiskService;
use App\Domain\Merchants\Trust\TrustService;
use Tests\Support\PrototypeFixtures;

it('reproduces the prototype Trust Score and its breakdown for every merchant', function () {
    $service = new TrustService;

    foreach (PrototypeFixtures::load('trust')['merchants'] as $case) {
        $trust = $service->score(PrototypeFixtures::trustSignals(PrototypeFixtures::merchant($case['merchantId'])));
        $expected = $case['trust'];

        expect($trust->score)->toBe($expected['score'], "merchant {$case['merchantId']} score")
            ->and($trust->label)->toBe($expected['label'])
            ->and($trust->penalty)->toEqual((float) $expected['penalty'])
            ->and(array_map(static fn (array $s): array => [$s['key'], $s['points'], $s['percent']], $trust->signals))
            ->toEqual(array_map(static fn (array $s): array => [$s['key'], (float) $s['pts'], $s['pct']], $expected['signals']))
            ->and(array_map(static fn (array $s): array => [$s['label'], $s['value'], $s['percent'], $s['good']], $trust->publicSignals))
            ->toEqual(array_map(static fn (array $s): array => [$s['label'], $s['value'], $s['pct'], $s['good']], $expected['public']));
    }
});

it('reproduces the prototype internal risk score and level for every merchant', function () {
    $service = new RiskService;

    foreach (PrototypeFixtures::load('trust')['merchants'] as $case) {
        $risk = $service->assess(PrototypeFixtures::riskInput(PrototypeFixtures::merchant($case['merchantId'])));
        $expected = $case['risk'];

        expect($risk->score)->toBe($expected['score'], "merchant {$case['merchantId']} risk score")
            ->and($risk->level->value)->toBe($expected['level'])
            ->and(array_map(static fn (array $s): array => [$s['label'], $s['points']], $risk->signals))
            ->toEqual(array_map(static fn (array $s): array => [$s['label'], $s['pts']], $expected['signals']));
    }
});
