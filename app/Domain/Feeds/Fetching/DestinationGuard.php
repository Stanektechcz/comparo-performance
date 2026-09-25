<?php

namespace App\Domain\Feeds\Fetching;

use App\Domain\Feeds\FeedErrorCode;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;

/**
 * SSRF guard for merchant feed URLs (§8). Pure: it parses with the same URI
 * parser the HTTP client uses and classifies addresses; resolving names is the
 * caller's job ({@see HostResolver}).
 *
 * Allowed: http/https, port 80 or 443, no userinfo, a DNS name or a canonical
 * public IP literal. Refused: every other scheme, non-canonical IPv4 literals
 * (2130706433, 0x7f.0.0.1, 0177.0.0.1, 127.1), localhost-style names and any
 * address in a private, loopback, link-local, CGNAT, documentation, benchmark,
 * multicast, reserved, IPv4-mapped/compatible, NAT64 or Teredo range.
 */
final class DestinationGuard
{
    private const array ALLOWED_PORTS = [80, 443];

    /** @var list<string> */
    private const array BLOCKED_IPV4 = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
    ];

    /** @var list<string> */
    private const array BLOCKED_IPV6 = [
        '::/96',          // unspecified, loopback and deprecated IPv4-compatible
        '::ffff:0:0/96',  // IPv4-mapped
        '64:ff9b::/96',   // NAT64
        '64:ff9b:1::/48', // local-use NAT64
        '100::/64',       // discard-only
        '2001::/32',      // Teredo (embeds arbitrary IPv4)
        '2001:10::/28',   // ORCHID
        '2001:db8::/32',  // documentation
        'fc00::/7',       // unique local
        'fe80::/10',      // link-local
        'fec0::/10',      // site-local (deprecated)
        'ff00::/8',       // multicast
    ];

    private const string CANONICAL_IPV4 = '/^(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}$/';

    /**
     * @throws FeedFetchException BLOCKED_DESTINATION
     */
    public function parse(#[\SensitiveParameter] string $url): Destination
    {
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1 || strlen($url) > 2048) {
            throw $this->blocked();
        }

        try {
            $uri = new Uri($url);
        } catch (InvalidArgumentException) {
            throw $this->blocked();
        }

        $scheme = strtolower($uri->getScheme());
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($scheme, ['http', 'https'], true) || $uri->getUserInfo() !== '' || ! in_array($port, self::ALLOWED_PORTS, true)) {
            throw $this->blocked();
        }

        [$host, $literal] = $this->host($uri->getHost());

        if ($literal !== null && $this->isBlockedAddress($literal)) {
            throw $this->blocked();
        }

        return new Destination((string) $uri->withFragment(''), $scheme, $host, $port, $literal);
    }

    /**
     * @param  list<string>  $addresses
     *
     * @throws FeedFetchException UNREACHABLE_URL when empty, BLOCKED_DESTINATION when any address is not public
     */
    public function assertPublic(array $addresses): void
    {
        if ($addresses === []) {
            throw new FeedFetchException(FeedErrorCode::UnreachableUrl);
        }

        foreach ($addresses as $address) {
            if ($this->isBlockedAddress($address)) {
                throw $this->blocked();
            }
        }
    }

    public function isBlockedAddress(string $address): bool
    {
        $binary = @inet_pton($address);

        if ($binary === false) {
            return true;
        }

        if (strlen($binary) === 4) {
            return $this->inAnyRange($binary, self::BLOCKED_IPV4) || $this->failsPhpFilter($address);
        }

        // 6to4 embeds an IPv4 address in bytes 2–5.
        if (str_starts_with($binary, "\x20\x02") && $this->isBlockedAddress((string) inet_ntop(substr($binary, 2, 4)))) {
            return true;
        }

        return $this->inAnyRange($binary, self::BLOCKED_IPV6) || $this->failsPhpFilter($address);
    }

    /**
     * @return array{0: string, 1: string|null} host, literal address
     */
    private function host(string $rawHost): array
    {
        $host = rtrim(strtolower($rawHost), '.');

        if ($host === '') {
            throw $this->blocked();
        }

        if (str_starts_with($host, '[')) {
            $address = substr($host, 1, -1);

            if (str_contains($address, '%') || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw $this->blocked();
            }

            return [$address, $address];
        }

        $labels = explode('.', $host);

        // WHATWG URL parsers treat a numeric last label as IPv4: only canonical dotted quads pass.
        if (preg_match('/^(0x[0-9a-f]*|\d+)$/', (string) end($labels)) === 1) {
            if (preg_match(self::CANONICAL_IPV4, $host) !== 1) {
                throw $this->blocked();
            }

            return [$host, $host];
        }

        return [$this->dnsName($host), null];
    }

    private function dnsName(string $host): string
    {
        if (preg_match('/[^\x00-\x7F]/', $host) === 1) {
            $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : false;
            $host = is_string($ascii) ? strtolower($ascii) : throw $this->blocked();
        }

        $local = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];
        $isLocalName = in_array($host, $local, true)
            || preg_match('/\.(localhost|local|localdomain|internal|home\.arpa)$/', $host) === 1;

        if ($isLocalName || strlen($host) > 253 || preg_match('/^[a-z0-9_]([a-z0-9_\-]{0,62})(\.[a-z0-9_]([a-z0-9_\-]{0,62}))*$/', $host) !== 1) {
            throw $this->blocked();
        }

        return $host;
    }

    /**
     * @param  list<string>  $ranges
     */
    private function inAnyRange(string $binary, array $ranges): bool
    {
        foreach ($ranges as $range) {
            [$network, $bits] = explode('/', $range);
            $networkBinary = (string) inet_pton($network);

            if (strlen($networkBinary) === strlen($binary) && $this->prefixMatches($binary, $networkBinary, (int) $bits)) {
                return true;
            }
        }

        return false;
    }

    private function prefixMatches(string $address, string $network, int $bits): bool
    {
        $fullBytes = intdiv($bits, 8);

        if (strncmp($address, $network, $fullBytes) !== 0) {
            return false;
        }

        $remainder = $bits % 8;

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($address[$fullBytes]) & $mask) === (ord($network[$fullBytes]) & $mask);
    }

    /**
     * Second opinion from PHP's own private/reserved range tables.
     */
    private function failsPhpFilter(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function blocked(): FeedFetchException
    {
        return new FeedFetchException(FeedErrorCode::BlockedDestination);
    }
}
