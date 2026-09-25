<?php

namespace App\Domain\Offers\Exceptions;

use DomainException;

/**
 * The merchant SKU already belongs to another feed source of the merchant
 * (the pipeline records SKU_OWNED_BY_OTHER_SOURCE and rejects the row).
 */
final class SkuOwnedByOtherSource extends DomainException
{
    public function __construct(
        public readonly int $merchantId,
        public readonly string $merchantSku,
        public readonly int $ownerFeedSourceId,
        public readonly ?int $attemptedFeedSourceId,
    ) {
        parent::__construct(sprintf(
            'SKU [%s] of merchant %d is owned by feed source %d; it cannot be written by %s.',
            $merchantSku,
            $merchantId,
            $ownerFeedSourceId,
            $attemptedFeedSourceId === null ? 'a non-feed writer' : "feed source {$attemptedFeedSourceId}",
        ));
    }
}
