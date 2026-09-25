<?php

namespace App\Domain\Platform\Audit;

/**
 * Privileged actions recorded in the audit log.
 *
 * Values are stored in audit_logs.action (max 96 chars) and must never be
 * renamed once written; add new cases instead. Format: `{subject}.{verb}`.
 */
enum AuditAction: string
{
    case FeedSourceCreated = 'feed_source.created';
    case FeedSourceUpdated = 'feed_source.updated';
    case FeedSourceStatusChanged = 'feed_source.status_changed';
    case FeedSourceCredentialsChanged = 'feed_source.credentials_changed';
    case FeedSourceMappingChanged = 'feed_source.mapping_changed';
    case FeedSourceDeleted = 'feed_source.deleted';

    case FeedRunStartedManually = 'feed_run.started_manually';
    case FeedRunCancelled = 'feed_run.cancelled';

    case MatchingDecided = 'matching.decided';
    case MatchingRematched = 'matching.rematched';
    case MatchingUnlinked = 'matching.unlinked';
    case MatchingConflictResolved = 'matching.conflict_resolved';

    case ProductCandidateProposed = 'product_candidate.proposed';
    case ProductCandidateResolved = 'product_candidate.resolved';
}
