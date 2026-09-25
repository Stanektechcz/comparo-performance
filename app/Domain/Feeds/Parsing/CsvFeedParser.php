<?php

namespace App\Domain\Feeds\Parsing;

use App\Domain\Feeds\FeedErrorCode;
use Generator;
use RuntimeException;

/**
 * Streaming CSV/TSV reader.
 *
 * Reads physical lines and joins them while a quoted field is open, so quoted
 * delimiters, doubled quotes and embedded newlines work and every record keeps
 * the line number it starts on. All supported encodings are ASCII-compatible,
 * so each record is converted to UTF-8 before it is split.
 */
final class CsvFeedParser implements FeedParser
{
    private const string BOM = "\xEF\xBB\xBF";

    /** A single record (including quoted newlines) larger than this is malformed. */
    private const int MAX_RECORD_BYTES = 1024 * 1024;

    /**
     * @return Generator<int, RawFeedRow>
     */
    public function rows(string $path, ParseOptions $options): Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The feed payload file could not be opened.');
        }

        try {
            yield from $this->records($handle, $options);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return Generator<int, RawFeedRow>
     */
    private function records($handle, ParseOptions $options): Generator
    {
        $physicalLine = 0;
        $header = null;
        $delimiter = $options->delimiter;
        $rowCount = 0;

        while (($record = $this->nextRecord($handle, $physicalLine)) !== null) {
            [$startLine, $bytes] = $record;

            if ($startLine === 1 && str_starts_with($bytes, self::BOM)) {
                $bytes = substr($bytes, strlen(self::BOM));
            }

            $text = $options->toUtf8($bytes, $startLine);

            if (trim($text) === '') {
                continue;
            }

            $delimiter ??= $this->detectDelimiter($text);
            $cells = str_getcsv($text, $delimiter, '"', '');

            if ($header === null) {
                $header = $this->header($cells);

                continue;
            }

            if (implode('', array_map(trim(...), array_map(strval(...), $cells))) === '') {
                continue; // a line of empty cells (";;;;")
            }

            if (++$rowCount > $options->maxRows) {
                throw new FeedParseException(FeedErrorCode::RowLimitExceeded, ['limit' => $options->maxRows], $startLine);
            }

            yield new RawFeedRow($startLine, $this->combine($header, $cells));
        }

        if ($rowCount === 0) {
            throw new FeedParseException(FeedErrorCode::EmptyFeed);
        }
    }

    /**
     * Read one logical record: physical lines are joined while the number of
     * quote characters is odd (a quoted field is still open).
     *
     * @param  resource  $handle
     * @return array{0: int, 1: string}|null
     */
    private function nextRecord($handle, int &$physicalLine): ?array
    {
        $line = fgets($handle);

        if ($line === false) {
            return null;
        }

        $startLine = ++$physicalLine;
        $buffer = $line;

        while (substr_count($buffer, '"') % 2 === 1) {
            if (strlen($buffer) > self::MAX_RECORD_BYTES) {
                throw new FeedParseException(FeedErrorCode::ParserError, ['format' => 'CSV'], $startLine);
            }

            $next = fgets($handle);

            if ($next === false) {
                // Unterminated quoted field at the end of the file.
                throw new FeedParseException(FeedErrorCode::ParserError, ['format' => 'CSV'], $startLine);
            }

            $physicalLine++;
            $buffer .= $next;
        }

        return [$startLine, rtrim($buffer, "\r\n")];
    }

    /**
     * Pick the candidate delimiter that occurs most often outside quotes in the
     * header line; ties keep the order ; , TAB |. A single column falls back to a comma.
     */
    private function detectDelimiter(string $headerLine): string
    {
        $unquoted = preg_replace('/"[^"]*"/', '', $headerLine) ?? $headerLine;
        $best = ',';
        $bestCount = 0;

        foreach (ParseOptions::DELIMITERS as $candidate) {
            $count = substr_count($unquoted, $candidate);

            if ($count > $bestCount) {
                [$best, $bestCount] = [$candidate, $count];
            }
        }

        return $best;
    }

    /**
     * @param  array<int, string|null>  $cells
     * @return array<int, string> column index => header name (blank and repeated names are skipped)
     */
    private function header(array $cells): array
    {
        $header = [];

        foreach ($cells as $index => $cell) {
            $name = trim((string) $cell);

            if ($name !== '' && ! in_array($name, $header, true)) {
                $header[$index] = $name;
            }
        }

        return $header;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string|null>  $cells
     * @return array<string, string>
     */
    private function combine(array $header, array $cells): array
    {
        $fields = [];

        foreach ($header as $index => $name) {
            $fields[$name] = (string) ($cells[$index] ?? '');
        }

        return $fields;
    }
}
