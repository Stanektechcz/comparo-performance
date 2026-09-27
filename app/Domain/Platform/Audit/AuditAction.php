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

    // Phase 4: reviews, purchase verification and orders
    // (docs/architecture/phase-4-reviews-orders.md).
    case ReviewSubmitted = 'review.submitted';
    case ReviewModerated = 'review.moderated';
    case ReviewWithdrawn = 'review.withdrawn';
    case ReviewReported = 'review.reported';
    case ReviewReplyPosted = 'review.reply_posted';
    case ReviewReplyEdited = 'review.reply_edited';
    case ReviewReplyRemoved = 'review.reply_removed';

    case PurchaseProofSubmitted = 'purchase_proof.submitted';
    case PurchaseProofDecided = 'purchase_proof.decided';
    case PurchaseProofReceiptViewed = 'purchase_proof.receipt_viewed';
    case PurchaseProofReceiptPurged = 'purchase_proof.receipt_purged';

    case OrderCreatedFromEvidence = 'order.created_from_evidence';
    case OrderDeliveryReported = 'order.delivery_reported';
    case OrderReturnOpened = 'order.return_opened';
    case OrderDisputeOpened = 'order.dispute_opened';

    case ReportDecided = 'report.decided';
}
