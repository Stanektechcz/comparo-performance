<?php

namespace App\Domain\Feeds\Parsing;

interface FeedParser
{
    /**
     * Stream the records of a local feed file.
     *
     * Rows are yielded lazily; a run-fatal problem (malformed file, unsupported
     * encoding, no rows, too many rows) throws {@see FeedParseException}, possibly
     * after some rows were already yielded.
     *
     * @return iterable<int, RawFeedRow>
     *
     * @throws FeedParseException
     */
    public function rows(string $path, ParseOptions $options): iterable;
}
