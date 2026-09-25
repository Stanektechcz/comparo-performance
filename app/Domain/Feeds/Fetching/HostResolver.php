<?php

namespace App\Domain\Feeds\Fetching;

interface HostResolver
{
    /**
     * Every A and AAAA address the host name currently resolves to.
     * An empty list means the name does not resolve.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
