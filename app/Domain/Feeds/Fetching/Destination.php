<?php

namespace App\Domain\Feeds\Fetching;

/**
 * A syntactically vetted fetch target. `host` is lower-case ASCII without
 * brackets; `literalAddress` is set when the host is an IP address.
 */
final readonly class Destination
{
    public function __construct(
        #[\SensitiveParameter]
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public ?string $literalAddress = null,
    ) {}

    /**
     * scheme://host:port — credentials are only ever sent to the original origin.
     */
    public function origin(): string
    {
        return $this->scheme.'://'.$this->host.':'.$this->port;
    }

    /**
     * libcurl CURLOPT_RESOLVE entry pinning the host to a vetted address.
     */
    public function resolveEntry(string $address): string
    {
        $pinned = str_contains($address, ':') ? '['.$address.']' : $address;

        return $this->host.':'.$this->port.':'.$pinned;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['origin' => $this->origin()];
    }
}
