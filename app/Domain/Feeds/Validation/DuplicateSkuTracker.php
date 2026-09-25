<?php

namespace App\Domain\Feeds\Validation;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Normalisation\NormalisedFeedItem;

/**
 * Detects repeated merchant SKUs within one run. The first occurrence wins;
 * later ones become DUPLICATE_SKU rejections. SKUs compare exactly (after the
 * mapper's whitespace trim), as merchant SKUs are case-sensitive identifiers.
 *
 * One tracker per run; it is the only stateful object in the validation layer.
 */
final class DuplicateSkuTracker
{
    /** @var array<string, int> sku => line of first occurrence */
    private array $firstLineBySku = [];

    public function track(NormalisedFeedItem $item): NormalisedFeedItem|RowRejection
    {
        $firstLine = $this->firstLineBySku[$item->merchantSku] ?? null;

        if ($firstLine === null) {
            $this->firstLineBySku[$item->merchantSku] = $item->lineNumber;

            return $item;
        }

        return new RowRejection($item->lineNumber, $item->merchantSku, [
            new FeedIssue(FeedErrorCode::DuplicateSku, FeedField::MerchantSku, [
                'sku' => $item->merchantSku,
                'first_line' => $firstLine,
            ]),
        ]);
    }

    public function count(): int
    {
        return count($this->firstLineBySku);
    }
}
