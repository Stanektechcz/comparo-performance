<?php

namespace App\Domain\Pricing\History;

enum SnapshotSource: string
{
    case Feed = 'feed';
    case MerchantApi = 'merchant_api';
    case Manual = 'manual';
    /** Fictional prototype data, only ever loaded into local/demo environments. */
    case PrototypeDemo = 'prototype_demo';
}
