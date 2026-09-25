<?php

namespace App\Domain\Matching;

/**
 * Kind of an item in the staff matching-conflict queue.
 */
enum ConflictKind: string
{
    /** Sources disagree about a product fact (e.g. EAN, pack size). */
    case FieldConflict = 'field_conflict';
    /** A listing matched a product that is blocked or unknown in its market. */
    case ComplianceHold = 'compliance_hold';
    /** A merge was requested but is blocked by conflicting facts. */
    case MergeBlocked = 'merge_blocked';
}
