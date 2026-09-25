<?php

namespace App\Domain\Platform\PrototypeIntegrity;

final readonly class PrototypeIntegrityReport
{
    /**
     * @param  list<string>  $missing
     * @param  list<string>  $changed
     */
    public function __construct(
        public int $protected,
        public array $missing,
        public array $changed,
    ) {}

    public function intact(): bool
    {
        return $this->missing === [] && $this->changed === [];
    }

    public function verified(): int
    {
        return $this->protected - count($this->missing) - count($this->changed);
    }
}
