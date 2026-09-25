<?php

namespace App\Domain\Feeds\Fetching;

use App\Domain\Feeds\FeedErrorCode;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Copies a response body to disk chunk by chunk, transparently gunzipping
 * payloads that start with the gzip magic bytes (whatever the headers claim),
 * counting the stored (decoded) bytes against the cap and hashing them
 * (sha256) on the way. A failure deletes the partial file.
 */
final class PayloadWriter
{
    private const int CHUNK_BYTES = 65_536;

    /** Smaller reads for gzip bound the size of one inflated chunk (≈1:1000 worst case). */
    private const int GZIP_CHUNK_BYTES = 8_192;

    /**
     * @return array{bytes: int, sha256: string, decompressed: bool}
     *
     * @throws FeedFetchException PAYLOAD_TOO_LARGE or PARSER_ERROR (corrupt gzip)
     */
    public function write(StreamInterface $body, string $path, int $maxBytes): array
    {
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('The feed payload file could not be created.');
        }

        try {
            $result = $this->copy($body, $handle, $maxBytes);
            fclose($handle);

            return $result;
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($path);

            throw $exception;
        }
    }

    /**
     * @param  resource  $handle
     * @return array{bytes: int, sha256: string, decompressed: bool}
     */
    private function copy(StreamInterface $body, $handle, int $maxBytes): array
    {
        $hash = hash_init('sha256');
        $bytes = 0;
        $inflate = null;
        $first = true;

        while (! $body->eof()) {
            $chunk = $body->read($inflate !== null || $first ? self::GZIP_CHUNK_BYTES : self::CHUNK_BYTES);

            if ($chunk === '') {
                break;
            }

            if ($first) {
                $first = false;
                $inflate = str_starts_with($chunk, "\x1F\x8B") ? $this->inflater() : null;
            }

            $data = $inflate !== null ? $this->inflate($inflate, $chunk, ZLIB_SYNC_FLUSH) : $chunk;
            $bytes = $this->append($handle, $hash, $data, $bytes, $maxBytes);
        }

        if ($inflate !== null) {
            if (inflate_get_status($inflate) !== ZLIB_STREAM_END) {
                $bytes = $this->append($handle, $hash, $this->inflate($inflate, '', ZLIB_FINISH), $bytes, $maxBytes);
            }

            $this->assertGzipComplete($inflate);
        }

        return ['bytes' => $bytes, 'sha256' => hash_final($hash), 'decompressed' => $inflate !== null];
    }

    /**
     * @param  resource  $handle
     */
    private function append($handle, \HashContext $hash, string $data, int $bytes, int $maxBytes): int
    {
        $bytes += strlen($data);

        if ($bytes > $maxBytes) {
            throw new FeedFetchException(FeedErrorCode::PayloadTooLarge, ['limit_mb' => max(1, intdiv($maxBytes, 1024 * 1024))]);
        }

        if ($data !== '') {
            hash_update($hash, $data);

            if (fwrite($handle, $data) !== strlen($data)) {
                throw new RuntimeException('The feed payload file could not be written.');
            }
        }

        return $bytes;
    }

    private function inflater(): \InflateContext
    {
        $context = inflate_init(ZLIB_ENCODING_GZIP);

        if ($context === false) {
            throw new RuntimeException('zlib is not available.');
        }

        return $context;
    }

    /**
     * A truncated or corrupt gzip stream never reaches its end marker.
     */
    private function assertGzipComplete(\InflateContext $context): void
    {
        if (inflate_get_status($context) !== ZLIB_STREAM_END) {
            throw new FeedFetchException(FeedErrorCode::ParserError, ['format' => 'gzip']);
        }
    }

    private function inflate(\InflateContext $context, string $chunk, int $flush): string
    {
        $data = @inflate_add($context, $chunk, $flush);

        if ($data === false) {
            throw new FeedFetchException(FeedErrorCode::ParserError, ['format' => 'gzip']);
        }

        return $data;
    }
}
