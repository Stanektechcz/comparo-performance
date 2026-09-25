<?php

namespace App\Domain\Feeds\Fetching;

use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;

/**
 * Feed access credentials (basic auth, bearer token or one custom header).
 *
 * Secrets are private, excluded from var_dump/print_r and never part of an
 * exception message; they are only ever handed to the HTTP client.
 */
final readonly class FeedCredentials
{
    /** Headers a merchant may not set: they control framing, routing or identity. */
    private const array FORBIDDEN_HEADERS = [
        'host', 'content-length', 'transfer-encoding', 'connection', 'upgrade', 'te', 'trailer',
        'proxy-authorization', 'expect', 'keep-alive',
    ];

    private function __construct(
        public FeedAuthType $type,
        #[\SensitiveParameter]
        private string $name,
        #[\SensitiveParameter]
        private string $secret,
    ) {
        if (preg_match('/[\r\n\x00]/', $name.$secret) === 1) {
            throw new InvalidArgumentException('Feed credentials may not contain line breaks.');
        }
    }

    public static function basic(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password): self
    {
        return new self(FeedAuthType::Basic, $username, $password);
    }

    public static function bearer(#[\SensitiveParameter] string $token): self
    {
        if (trim($token) === '') {
            throw new InvalidArgumentException('The bearer token may not be empty.');
        }

        return new self(FeedAuthType::Bearer, '', $token);
    }

    public static function header(string $name, #[\SensitiveParameter] string $value): self
    {
        if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~\-]{1,64}$/', $name) !== 1 || in_array(strtolower($name), self::FORBIDDEN_HEADERS, true)) {
            throw new InvalidArgumentException('The credential header name is not allowed.');
        }

        return new self(FeedAuthType::Header, $name, $value);
    }

    /**
     * From the decrypted `feed_sources.credentials` array:
     * {type: basic, username, password} | {type: bearer, token} | {type: header, name, value}.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(#[\SensitiveParameter] array $data): self
    {
        $string = static fn (string $key): string => is_string($data[$key] ?? null)
            ? $data[$key]
            : throw new InvalidArgumentException("Feed credentials are missing [{$key}].");

        return match (FeedAuthType::tryFrom(is_string($data['type'] ?? null) ? $data['type'] : '')) {
            FeedAuthType::Basic => self::basic($string('username'), $string('password')),
            FeedAuthType::Bearer => self::bearer($string('token')),
            FeedAuthType::Header => self::header($string('name'), $string('value')),
            null => throw new InvalidArgumentException('Unknown feed credential type.'),
        };
    }

    public function applyTo(PendingRequest $request): PendingRequest
    {
        return match ($this->type) {
            FeedAuthType::Basic => $request->withBasicAuth($this->name, $this->secret),
            FeedAuthType::Bearer => $request->withToken($this->secret),
            FeedAuthType::Header => $request->withHeaders([$this->name => $this->secret]),
        };
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['type' => $this->type->value, 'secret' => '[redacted]'];
    }
}
