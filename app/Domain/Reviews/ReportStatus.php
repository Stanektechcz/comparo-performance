<?php

namespace App\Domain\Reviews;

/**
 * Content report state (content_reports.status): open → upheld | dismissed.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Upheld = 'upheld';
    case Dismissed = 'dismissed';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
