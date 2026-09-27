<?php

namespace App\Domain\Reviews;

/**
 * Why content was reported (content_reports.reason; MODERATION.md "Reporting").
 */
enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Misleading = 'misleading';
    case FakeReview = 'fake_review';
    case ConflictOfInterest = 'conflict_of_interest';
    case IllegalContent = 'illegal_content';
    case PersonalInformation = 'personal_information';
    case Other = 'other';
}
