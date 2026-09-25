<?php

namespace App\Domain\Feeds;

/**
 * How a feed payload reaches Comparo. The payload format is FeedFormat.
 */
enum FeedTransport: string
{
    /** Fetched from the merchant's URL (SSRF-guarded), manually or on schedule. */
    case Url = 'url';
    /** A file uploaded by the merchant through the dashboard. */
    case Upload = 'upload';
    /** Pushed by the merchant through the API. */
    case ApiPush = 'api_push';
    /** A one-off file entered by staff on the merchant's behalf. */
    case ManualUpload = 'manual_upload';

    public function isFetched(): bool
    {
        return $this === self::Url;
    }
}
