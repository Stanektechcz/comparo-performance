<?php

namespace App\Http\Presenters\Merchant;

use App\Domain\Feeds\FeedErrorCode;
use Illuminate\Support\Facades\Lang;

/**
 * Merchant-facing, actionable feed messages (`lang/en/feeds.php`). Missing
 * placeholders are filled with an ellipsis (never left as `:name`), a
 * `:field` parameter is rendered with its human label, and unknown codes
 * fall back to a neutral sentence — raw server text is never shown.
 */
final class FeedMessages
{
    private const string MISSING = '…';

    private const int VALUE_MAX = 120;

    /**
     * @param  array<array-key, mixed>|null  $params
     */
    public static function message(string $code, ?array $params = []): string
    {
        $errorCode = FeedErrorCode::tryFrom($code);
        $key = $errorCode?->messageKey() ?? 'feeds.errors.'.$code;

        if (! Lang::has($key)) {
            return 'The feed reported a problem ('.mb_substr($code, 0, 64).').';
        }

        $replace = [];

        foreach ($errorCode?->messageParams() ?? [] as $name) {
            $replace[$name] = self::param($name, $params[$name] ?? null);
        }

        return (string) __($key, $replace);
    }

    public static function fieldLabel(?string $field): ?string
    {
        if ($field === null || $field === '') {
            return null;
        }

        $key = 'feeds.fields.'.$field;

        return Lang::has($key) ? (string) __($key) : $field;
    }

    private static function param(string $name, mixed $value): string
    {
        if (! is_scalar($value) || (string) $value === '') {
            return self::MISSING;
        }

        $text = (string) $value;

        if ($name === 'field') {
            return (string) self::fieldLabel($text);
        }

        return mb_strlen($text) > self::VALUE_MAX ? mb_substr($text, 0, self::VALUE_MAX).'…' : $text;
    }
}
