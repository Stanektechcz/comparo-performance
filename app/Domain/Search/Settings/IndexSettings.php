<?php

namespace App\Domain\Search\Settings;

/**
 * Versioned settings of one search index, defined in code. Engines apply
 * them (Meilisearch) or validate and ignore them (database engine).
 */
interface IndexSettings
{
    /**
     * The logical index the settings target (a live index or its `_tmp` twin).
     */
    public function index(): string;

    /**
     * The same settings for another (e.g. temporary) index of the same kind.
     */
    public function forIndex(string $index): static;

    public function version(): int;

    /**
     * The Meilisearch settings object (PATCH /indexes/{uid}/settings).
     *
     * @return array<string, mixed>
     */
    public function toMeilisearch(): array;

    /**
     * Changes whenever the version or any effective setting changes (active
     * markets, synonyms); the sync command compares it with the applied one.
     */
    public function fingerprint(): string;
}
