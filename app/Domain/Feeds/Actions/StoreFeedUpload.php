<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\Pipeline\FeedStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stores an uploaded feed file on the private feeds disk under
 * `feeds/{merchantId}/{uuid}.{format}` and returns that path. The client file
 * name and extension are never used; the file is never public and never
 * executed. MIME sniffing belongs to the Form Request (P2-12).
 */
final class StoreFeedUpload
{
    public function __construct(private readonly FeedStorage $storage) {}

    /**
     * @throws InvalidArgumentException when the file is invalid or larger than `comparo.feeds.max_payload_bytes`
     */
    public function handle(int $merchantId, FeedFormat $format, UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('The feed upload failed.');
        }

        if ($file->getSize() > $this->storage->maxPayloadBytes()) {
            throw new InvalidArgumentException(FeedErrorCode::PayloadTooLarge->value);
        }

        $name = Str::uuid()->toString().'.'.$format->value;
        $path = $this->storage->disk()->putFileAs(FeedStorage::merchantDirectory($merchantId), $file, $name, ['visibility' => 'private']);

        if (! is_string($path)) {
            throw new RuntimeException('The feed upload could not be stored.');
        }

        return $path;
    }
}
