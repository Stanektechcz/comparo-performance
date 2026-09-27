<?php

namespace App\Domain\Reviews;

/**
 * State of an official merchant reply (review_replies.status). Merchants can
 * post and edit (24 h); hiding and removal are moderation outcomes.
 */
enum ReplyStatus: string
{
    case Published = 'published';
    case Hidden = 'hidden';
    case Removed = 'removed';

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
