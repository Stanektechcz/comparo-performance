<?php

namespace App\Domain\Orders;

/**
 * The evidence an order was created from (orders.source). Orders exist only
 * from evidence (D-15); there is no manual order entry.
 */
enum OrderSource: string
{
    case AffiliateConversion = 'affiliate_conversion';
    case PurchaseProof = 'purchase_proof';
    case ClickDeclaration = 'click_declaration';
}
