<?php

namespace App\Domain\Feeds\Parsing;

use App\Domain\Feeds\FeedFormat;

final class FeedParserFactory
{
    public static function for(FeedFormat $format): FeedParser
    {
        return match ($format) {
            FeedFormat::Csv => new CsvFeedParser,
            FeedFormat::Xml => new XmlFeedParser,
            FeedFormat::Json => new JsonFeedParser,
        };
    }
}
