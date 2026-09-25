<?php

namespace App\Domain\Feeds;

/**
 * How a completed feed run ended.
 */
enum FeedRunOutcome: string
{
    case Published = 'published';
    /** Published, but with row warnings or a held mass deactivation. */
    case PublishedWithWarnings = 'published_with_warnings';
    /** The payload checksum matched the previous run; nothing was re-processed. */
    case Unchanged = 'unchanged';
}
