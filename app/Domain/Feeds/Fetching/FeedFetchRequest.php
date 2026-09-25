<?php

namespace App\Domain\Feeds\Fetching;

use InvalidArgumentException;

/**
 * One feed download. The URL may carry access tokens in its query string, so
 * it is treated as a secret (never logged or echoed).
 */
final readonly class FeedFetchRequest
{
    public const int DEFAULT_MAX_BYTES = 100 * 1024 * 1024;

    public const int DEFAULT_CONNECT_TIMEOUT_SECONDS = 5;

    public const int DEFAULT_TIMEOUT_SECONDS = 120;

    public const int DEFAULT_MAX_REDIRECTS = 3;

    public function __construct(
        #[\SensitiveParameter]
        public string $url,
        public string $destinationPath,
        public ?FeedCredentials $credentials = null,
        public int $maxBytes = self::DEFAULT_MAX_BYTES,
        public int $connectTimeoutSeconds = self::DEFAULT_CONNECT_TIMEOUT_SECONDS,
        public int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        public int $maxRedirects = self::DEFAULT_MAX_REDIRECTS,
    ) {
        if ($maxBytes < 1 || $connectTimeoutSeconds < 1 || $timeoutSeconds < 1 || $maxRedirects < 0) {
            throw new InvalidArgumentException('Feed fetch limits must be positive.');
        }
    }

    /**
     * @return array<string, string|int|bool>
     */
    public function __debugInfo(): array
    {
        return ['destinationPath' => $this->destinationPath, 'hasCredentials' => $this->credentials !== null, 'maxBytes' => $this->maxBytes];
    }
}
