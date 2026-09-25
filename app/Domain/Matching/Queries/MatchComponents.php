<?php

namespace App\Domain\Matching\Queries;

use App\Domain\Matching\Engine\FeedItemFacts;
use App\Domain\Matching\Engine\MatchPart;
use App\Domain\Matching\Engine\MatchPartLabel;
use App\Domain\Matching\Engine\MatchResult;

/**
 * The JSON stored in matching_decisions.components, and the part list the
 * review queues and the preview expose.
 *
 * Shape: {parts: [{signal, points, label, params}], facts: {title, ean,
 * brand_raw, pack_raw, variant_raw}, facts_fingerprint, bucket, level,
 * policy_version}. `label` is the prototype's English text for staff
 * evidence; presenters localise from signal + params.
 */
final class MatchComponents
{
    /**
     * @return array{parts: list<array{signal: string, points: int, label: string, params: array<string, int|string>}>, facts: array{title: string, ean: ?string, brand_raw: ?string, pack_raw: ?string, variant_raw: ?string}, facts_fingerprint: string, bucket: ?string, level: ?string, policy_version: ?string}
     */
    public static function build(FeedItemFacts $facts, ?MatchResult $result): array
    {
        return [
            'parts' => $result === null ? [] : self::parts($result->parts),
            'facts' => ListingFacts::toArray($facts),
            'facts_fingerprint' => ListingFacts::fingerprint($facts),
            'bucket' => $result?->bucket->value,
            'level' => $result?->level->value,
            'policy_version' => $result?->policyVersion,
        ];
    }

    /**
     * @param  list<MatchPart>  $parts
     * @return list<array{signal: string, points: int, label: string, params: array<string, int|string>}>
     */
    public static function parts(array $parts): array
    {
        return array_map(static fn (MatchPart $part): array => [
            'signal' => $part->signal->value,
            'points' => $part->points,
            'label' => MatchPartLabel::english($part),
            'params' => $part->params,
        ], $parts);
    }

    /**
     * The parts stored in a decision's components (tolerates the legacy list shape).
     *
     * @param  array<int|string, mixed>|null  $components
     * @return list<array<string, mixed>>
     */
    public static function storedParts(?array $components): array
    {
        if ($components === null) {
            return [];
        }

        $parts = array_key_exists('parts', $components) ? $components['parts'] : $components;

        if (! is_array($parts)) {
            return [];
        }

        return array_values(array_filter($parts, is_array(...)));
    }

    /**
     * @param  array<int|string, mixed>|null  $components
     */
    public static function storedFingerprint(?array $components): ?string
    {
        $fingerprint = $components['facts_fingerprint'] ?? null;

        return is_string($fingerprint) ? $fingerprint : null;
    }

    /**
     * @param  array<int|string, mixed>|null  $components
     */
    public static function storedBucket(?array $components): ?string
    {
        $bucket = $components['bucket'] ?? null;

        return is_string($bucket) ? $bucket : null;
    }
}
