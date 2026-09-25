<?php

namespace App\Domain\Offers;

enum LinkStatus: string
{
    case Ok = 'ok';
    case Broken = 'broken';
    case MissingTracking = 'missing_tracking';

    public function isHealthy(): bool
    {
        return $this === self::Ok;
    }
}
