<?php

namespace App\Domain\Feeds\Validation;

/**
 * How serious a feed issue is. The string values deliberately equal the matching
 * cases of the persisted `FeedErrorSeverity` enum, so the storage layer can map
 * them with `FeedErrorSeverity::from($severity->value)`.
 */
enum FeedIssueSeverity: string
{
    /** The row is still published; the merchant should fix it. */
    case Warning = 'warning';

    /** The row is rejected; the rest of the run continues. */
    case Error = 'error';

    /** The whole run fails and nothing is published. */
    case Fatal = 'fatal';
}
