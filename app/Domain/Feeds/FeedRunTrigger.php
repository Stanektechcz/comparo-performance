<?php

namespace App\Domain\Feeds;

/**
 * What started a feed run.
 */
enum FeedRunTrigger: string
{
    case Manual = 'manual';
    case Schedule = 'schedule';
    case Api = 'api';
}
