<?php

namespace App\Domain\Feeds;

/**
 * Severity of a feed error row. Fatal errors fail the whole run; errors reject
 * one row; warnings and info are published with the row.
 */
enum FeedErrorSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Fatal = 'fatal';

    public function rejectsRow(): bool
    {
        return $this === self::Error || $this === self::Fatal;
    }
}
