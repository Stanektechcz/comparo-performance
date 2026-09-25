<?php

namespace App\Domain\Platform\PrototypeImport;

/**
 * Data-quality findings collected while importing the prototype snapshot.
 * Nothing is silently dropped: every skipped or adjusted record is noted here.
 */
final class ImportReport
{
    /**
     * @var array<string, list<string>>
     */
    private array $issues = [];

    public function note(string $category, string $detail): void
    {
        $this->issues[$category][] = $detail;
    }

    public function count(string $category): int
    {
        return count($this->issues[$category] ?? []);
    }

    /**
     * @return list<string>
     */
    public function details(string $category): array
    {
        return $this->issues[$category] ?? [];
    }

    public function isClean(): bool
    {
        return $this->issues === [];
    }

    /**
     * One human-readable line per category.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        $lines = [];

        foreach ($this->issues as $category => $details) {
            $lines[] = sprintf('%s: %d (%s)', $category, count($details), implode('; ', array_slice($details, 0, 5)).(count($details) > 5 ? '; …' : ''));
        }

        return $lines;
    }
}
