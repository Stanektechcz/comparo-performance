<?php

namespace App\Domain\Catalog;

enum ProductStatus: string
{
    case Active = 'active';
    /** Historical identity kept for its URL; 301 to the survivor, never listed or indexed. */
    case Merged = 'merged';
    case Retired = 'retired';
}
