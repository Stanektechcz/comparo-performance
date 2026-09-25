<?php

namespace App\Domain\Feeds\Actions;

/**
 * Outcome of {@see ReconcileMissingListings}: offers hidden by this call,
 * whether the mass-removal guard held them all back, and the products whose
 * offers changed.
 */
final readonly class ReconcileResult
{
    /**
     * @param  list<int>  $productIds
     */
    public function __construct(
        public int $deactivated,
        public bool $held,
        public array $productIds,
    ) {}
}
