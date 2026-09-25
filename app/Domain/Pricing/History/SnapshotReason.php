<?php

namespace App\Domain\Pricing\History;

enum SnapshotReason: string
{
    case FirstSeen = 'first_seen';
    case PriceChange = 'price_change';
    case ShippingChange = 'shipping_change';
    case AvailabilityChange = 'availability_change';
    case Scheduled = 'scheduled';
    case Correction = 'correction';
    case PrototypeImport = 'prototype_import';
}
