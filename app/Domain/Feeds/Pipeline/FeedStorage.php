<?php

namespace App\Domain\Feeds\Pipeline;

use App\Domain\Feeds\FeedFormat;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The private feeds disk (`comparo.feeds.disk`). Payloads live under
 * `feeds/{merchantId}/`; the disk must be local because fetchers stream to,
 * and parsers read from, a local path.
 */
final class FeedStorage
{
    public static function merchantDirectory(int $merchantId): string
    {
        return 'feeds/'.$merchantId;
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('comparo.feeds.disk', 'local'));
    }

    public function maxPayloadBytes(): int
    {
        return max(1, (int) config('comparo.feeds.max_payload_bytes'));
    }

    /**
     * A fresh relative path for a fetched payload; the merchant directory is created.
     */
    public function newPayloadPath(int $merchantId, FeedFormat $format): string
    {
        $directory = self::merchantDirectory($merchantId);
        $this->disk()->makeDirectory($directory);

        return $directory.'/'.Str::uuid()->toString().'.'.$format->value;
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->disk()->path($relativePath);
    }

    public function exists(string $relativePath): bool
    {
        return $this->disk()->exists($relativePath);
    }

    public function delete(string $relativePath): void
    {
        $this->disk()->delete($relativePath);
    }

    /**
     * Whether a stored path is inside the merchant's own directory (no traversal).
     */
    public function belongsToMerchant(string $relativePath, int $merchantId): bool
    {
        $prefix = self::merchantDirectory($merchantId).'/';

        return str_starts_with($relativePath, $prefix)
            && ! str_contains($relativePath, '..')
            && ! str_contains($relativePath, '\\')
            && preg_match('#^[A-Za-z0-9._/\-]+$#', $relativePath) === 1
            && substr_count(substr($relativePath, strlen($prefix)), '/') === 0;
    }
}
