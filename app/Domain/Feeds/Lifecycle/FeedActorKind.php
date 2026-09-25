<?php

namespace App\Domain\Feeds\Lifecycle;

/**
 * Who asks for a feed source or run transition. Merchants pause/resume their
 * own feeds, staff disable/re-enable, the pipeline (system) moves sources
 * after run outcomes.
 */
enum FeedActorKind: string
{
    case Merchant = 'merchant';
    case Staff = 'staff';
    case System = 'system';
}
