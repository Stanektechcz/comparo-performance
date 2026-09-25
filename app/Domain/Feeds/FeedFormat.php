<?php

namespace App\Domain\Feeds;

/**
 * Payload format of a merchant feed (MERCHANT-FEEDS.md). How the payload arrives
 * (URL fetch, upload, API push) is the source's transport, not its format.
 */
enum FeedFormat: string
{
    case Csv = 'csv';
    case Xml = 'xml';
    case Json = 'json';
}
