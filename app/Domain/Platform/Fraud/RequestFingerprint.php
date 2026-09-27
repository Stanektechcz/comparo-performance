<?php

namespace App\Domain\Platform\Fraud;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Server-side fraud/abuse-signal fingerprint (D-23): a salted, rotating hash
 * of a truncated IP and a coarse user-agent class only — never the raw IP or
 * user agent, and never a client-side fingerprinting script
 * (comparo.fraud.client_fingerprinting stays false).
 *
 * Pure: the application key and the salt epoch are passed in, mirroring
 * App\Domain\Search\Analytics\SessionHasher. The epoch rotates every
 * comparo.fraud.hash_salt_rotation_days (via epochFor()), so the same
 * request signals hash identically within one rotation window and
 * differently once the window rolls over — request_signals rows keep the
 * hash alongside its salt_epoch (docs/architecture/phase-4-reviews-orders.md
 * §2 review_signals) so a later window's hash is never compared to an
 * earlier one.
 */
final readonly class RequestFingerprint
{
    private const string CONTEXT = 'comparo:fraud-fingerprint:';

    public function __construct(private string $applicationKey)
    {
        if ($applicationKey === '') {
            throw new InvalidArgumentException('Request fingerprinting needs the application key.');
        }
    }

    /**
     * The fingerprint for one request's signals at one salt epoch. Returns
     * only the hash: the truncated IP and user-agent class are consumed
     * in-memory and never surfaced by this method.
     */
    public function hash(string $ip, string $userAgent, int $saltEpoch): string
    {
        $subject = self::truncateIp($ip).'|'.self::userAgentClass($userAgent);

        return hash_hmac('sha256', $subject, $this->epochSecret($saltEpoch));
    }

    /**
     * The rotation epoch a given instant falls into, given the configured
     * rotation cadence (comparo.fraud.hash_salt_rotation_days). Two instants
     * in the same rotationDays-sized window since the Unix epoch yield the
     * same salt epoch, so the same request within a window always hashes
     * the same; the next window yields a different epoch and therefore a
     * different, unlinkable hash.
     */
    public static function epochFor(DateTimeInterface $at, int $rotationDays): int
    {
        if ($rotationDays < 1) {
            throw new InvalidArgumentException('The salt rotation cadence must be at least 1 day.');
        }

        return intdiv(intdiv($at->getTimestamp(), 86_400), $rotationDays);
    }

    private function epochSecret(int $saltEpoch): string
    {
        return hash_hmac('sha256', self::CONTEXT.$saltEpoch, $this->applicationKey);
    }

    /**
     * IPv4: the last octet zeroed. IPv6: kept to a /64 (first 4 groups),
     * the rest zeroed. An unparseable value is never passed through as-is.
     */
    private static function truncateIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $octets = explode('.', $ip);
            $octets[3] = '0';

            return implode('.', $octets);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $binary = inet_pton($ip);

            if ($binary !== false) {
                $truncated = substr($binary, 0, 8).str_repeat("\0", 8);
                $textual = inet_ntop($truncated);

                if ($textual !== false) {
                    return $textual;
                }
            }
        }

        return 'invalid-ip';
    }

    /**
     * A coarse browser family, never the full user-agent string.
     */
    private static function userAgentClass(string $userAgent): string
    {
        $ua = strtolower(trim($userAgent));

        return match (true) {
            $ua === '' => 'unknown',
            str_contains($ua, 'bot') || str_contains($ua, 'spider') || str_contains($ua, 'crawler') => 'bot',
            str_contains($ua, 'edg/') => 'edge',
            str_contains($ua, 'firefox/') => 'firefox',
            str_contains($ua, 'chrome/') => 'chrome',
            str_contains($ua, 'safari/') => 'safari',
            default => 'other',
        };
    }
}
