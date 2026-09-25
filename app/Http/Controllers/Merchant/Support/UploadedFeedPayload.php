<?php

namespace App\Http\Controllers\Merchant\Support;

use App\Domain\Feeds\Actions\StoreFeedUpload;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\Fetching\FeedFetchException;
use App\Domain\Feeds\Fetching\PayloadWriter;
use App\Domain\Feeds\Pipeline\FeedStorage;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Stores a validated feed upload on the private feeds disk and returns its
 * path in the merchant's directory.
 *
 * Plain files go through {@see StoreFeedUpload}. Gzip files (detected by
 * their magic bytes, never by name) are inflated through the pipeline's
 * {@see PayloadWriter}, which caps the INFLATED size at
 * `comparo.feeds.max_payload_bytes` — the parsers read plain files only.
 */
final class UploadedFeedPayload
{
    public const string KEY = 'file';

    private const string GZIP_MAGIC = "\x1F\x8B";

    public function __construct(
        private readonly StoreFeedUpload $store,
        private readonly FeedStorage $storage,
        private readonly PayloadWriter $writer,
    ) {}

    /**
     * @throws ValidationException
     */
    public function store(int $merchantId, FeedFormat $format, UploadedFile $file): string
    {
        try {
            return self::isGzip($file)
                ? $this->inflate($merchantId, $format, $file)
                : $this->store->handle($merchantId, $format, $file);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([self::KEY => $exception->getMessage() === FeedErrorCode::PayloadTooLarge->value
                ? self::tooLargeMessage($this->storage->maxPayloadBytes())
                : 'The upload failed. Try again.']);
        } catch (FeedFetchException $exception) {
            throw ValidationException::withMessages([self::KEY => $exception->errorCode === FeedErrorCode::PayloadTooLarge
                ? self::tooLargeMessage($this->storage->maxPayloadBytes())
                : 'The compressed file is damaged and could not be unpacked.']);
        }
    }

    public function delete(string $path): void
    {
        $this->storage->delete($path);
    }

    public static function tooLargeMessage(int $maxBytes): string
    {
        return 'The feed is larger than '.intdiv($maxBytes, 1024 * 1024).' MB (after unpacking). Split it into several feeds.';
    }

    public static function isGzip(UploadedFile $file): bool
    {
        $handle = @fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 2);
        fclose($handle);

        return $magic === self::GZIP_MAGIC;
    }

    /**
     * @throws FeedFetchException PAYLOAD_TOO_LARGE or PARSER_ERROR (corrupt gzip)
     */
    private function inflate(int $merchantId, FeedFormat $format, UploadedFile $file): string
    {
        $handle = @fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('The feed upload failed.');
        }

        $path = $this->storage->newPayloadPath($merchantId, $format);
        $this->writer->write(Utils::streamFor($handle), $this->storage->absolutePath($path), $this->storage->maxPayloadBytes());

        return $path;
    }
}
