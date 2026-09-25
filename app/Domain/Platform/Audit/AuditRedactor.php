<?php

namespace App\Domain\Platform\Audit;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * Masks secrets before they reach audit_logs.before/after.
 *
 * Any value whose key looks secret (password, secret, token, credential,
 * api key, authorization, cookie — case-insensitive, anywhere in the key) is
 * replaced with `[redacted]`, including whole nested structures under such a
 * key. Booleans are kept, because a flag such as `credentials_changed: true`
 * carries no secret. Objects are flattened to arrays first so secrets inside
 * them are masked too. The redactor never throws.
 */
final class AuditRedactor
{
    public const string MASK = '[redacted]';

    private const string SECRET_KEY_PATTERN = '/password|secret|token|credential|api_?key|authorization|cookie/i';

    private const int MAX_DEPTH = 16;

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redact(array $data): array
    {
        return $this->redactArray($data, 0);
    }

    public function isSecretKey(int|string $key): bool
    {
        return is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redactArray(array $data, int $depth): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            $redacted[$key] = $this->isSecretKey($key) && ! is_bool($value)
                ? self::MASK
                : $this->redactValue($value, $depth + 1);
        }

        return $redacted;
    }

    private function redactValue(mixed $value, int $depth): mixed
    {
        if (is_float($value) && ! is_finite($value)) {
            return (string) $value; // NAN/INF cannot be JSON-encoded.
        }

        if (is_string($value)) {
            return mb_scrub($value, 'UTF-8'); // invalid UTF-8 cannot be JSON-encoded.
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($depth > self::MAX_DEPTH) {
            return '[truncated]';
        }

        try {
            $normalized = $this->normalize($value);
        } catch (Throwable) {
            return '[unserializable]';
        }

        return is_array($normalized)
            ? $this->redactArray($normalized, $depth)
            : $this->redactValue($normalized, $depth + 1);
    }

    /**
     * Turns objects into plain data the redactor can inspect.
     */
    private function normalize(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof Arrayable => $value->toArray(),
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            $value instanceof Stringable => (string) $value,
            is_object($value) => get_object_vars($value),
            default => '[unserializable]',
        };
    }
}
