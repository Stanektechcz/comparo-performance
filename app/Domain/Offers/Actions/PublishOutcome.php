<?php

namespace App\Domain\Offers\Actions;

enum PublishOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    /** Terms identical: no offer write and no events (freshness may still be refreshed). */
    case Unchanged = 'unchanged';
    /** A deactivated offer is live again (its terms may also have changed). */
    case Reactivated = 'reactivated';
}
