<?php

namespace App\Domain\Reviews\Abuse;

/**
 * The moderation-queue heuristics of HTML `spamSignals` (17073-17082), in
 * the order the prototype lists them.
 */
enum SpamSignal: string
{
    case Caps = 'caps';
    case ExcessivePunctuation = 'excessive_punctuation';
    case OutboundLink = 'outbound_link';
    case VeryShort = 'very_short';
    case Unverified = 'unverified';
    case GenericPraise = 'generic_praise';
}
