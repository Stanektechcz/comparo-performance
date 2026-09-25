<?php

namespace App\Domain\Feeds\Parsing;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedFormat;
use InvalidArgumentException;

/**
 * How to read one feed payload. Built from the feed source's settings.
 */
final readonly class ParseOptions
{
    public const int DEFAULT_MAX_ROWS = 200_000;

    public const int DEFAULT_MAX_JSON_BYTES = 64 * 1024 * 1024;

    /** @var array<string, string> accepted spellings => canonical name */
    private const array ENCODINGS = [
        'UTF-8' => 'UTF-8',
        'UTF8' => 'UTF-8',
        'WINDOWS-1250' => 'Windows-1250',
        'CP1250' => 'Windows-1250',
        'WINDOWS-1252' => 'Windows-1252',
        'CP1252' => 'Windows-1252',
        'ISO-8859-1' => 'ISO-8859-1',
        'LATIN1' => 'ISO-8859-1',
        'ISO-8859-2' => 'ISO-8859-2',
        'LATIN2' => 'ISO-8859-2',
    ];

    /** @var list<string> */
    public const array DELIMITERS = [';', ',', "\t", '|'];

    /** @var list<string> record element names tried, in order, when none is configured */
    public const array RECORD_ELEMENTS = ['SHOPITEM', 'item', 'product', 'entry'];

    /** Canonical encoding name: UTF-8, Windows-1250, Windows-1252, ISO-8859-1 or ISO-8859-2. */
    public string $encoding;

    /**
     * @throws FeedParseException UNSUPPORTED_ENCODING for any other encoding
     * @throws InvalidArgumentException for an invalid delimiter, element name or limit
     */
    public function __construct(
        public FeedFormat $format,
        string $encoding = 'UTF-8',
        public ?string $delimiter = null,
        public ?string $recordElement = null,
        public int $maxRows = self::DEFAULT_MAX_ROWS,
        public int $maxJsonBytes = self::DEFAULT_MAX_JSON_BYTES,
    ) {
        $canonical = self::ENCODINGS[strtoupper(trim($encoding))] ?? null;

        if ($canonical === null) {
            throw new FeedParseException(FeedErrorCode::UnsupportedEncoding, ['encoding' => mb_substr($encoding, 0, 40)]);
        }

        if ($delimiter !== null && ! in_array($delimiter, self::DELIMITERS, true)) {
            throw new InvalidArgumentException('The CSV delimiter must be one of ; , TAB or |.');
        }

        if ($recordElement !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_.\-]{0,63}$/', $recordElement) !== 1) {
            throw new InvalidArgumentException('The record element must be a plain XML element name.');
        }

        if ($maxRows < 1 || $maxJsonBytes < 1) {
            throw new InvalidArgumentException('Parse limits must be positive.');
        }

        $this->encoding = $canonical;
    }

    public function isUtf8(): bool
    {
        return $this->encoding === 'UTF-8';
    }

    /**
     * Convert raw bytes in the configured encoding to UTF-8.
     *
     * @throws FeedParseException UNSUPPORTED_ENCODING when the bytes are not valid in that encoding
     */
    public function toUtf8(string $bytes, ?int $lineNumber = null): string
    {
        if ($this->isUtf8()) {
            if (! mb_check_encoding($bytes, 'UTF-8')) {
                throw new FeedParseException(FeedErrorCode::UnsupportedEncoding, ['encoding' => $this->encoding], $lineNumber);
            }

            return $bytes;
        }

        $converted = @iconv($this->encoding, 'UTF-8', $bytes);

        if ($converted === false) {
            throw new FeedParseException(FeedErrorCode::UnsupportedEncoding, ['encoding' => $this->encoding], $lineNumber);
        }

        return $converted;
    }
}
