<?php

namespace App\Domain\Search\Console;

use App\Domain\Search\Contracts\SearchEngine;
use App\Domain\Search\Engines\IndexNames;
use App\Domain\Search\Settings\IndexSettings;
use App\Domain\Search\Settings\IndexSettingsFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Applies the code-defined index settings when they differ from the last
 * applied ones. The applied fingerprint (version + effective settings) is
 * remembered per driver and physical index in the cache; a lost cache entry
 * only causes a harmless re-apply.
 */
final readonly class SettingsSynchronizer
{
    public const string APPLIED = 'applied';

    public const string UNCHANGED = 'unchanged';

    public function __construct(
        private SearchEngine $engine,
        private IndexSettingsFactory $settings,
        private Repository $cache,
        private IndexNames $names,
    ) {}

    /**
     * @return array<string, array{version: int, status: string}> logical index => outcome
     */
    public function sync(bool $force = false): array
    {
        $outcomes = [];

        foreach ($this->settings->all() as $settings) {
            $applied = $force || $this->cache->get($this->key($settings)) !== $settings->fingerprint();

            if ($applied) {
                $this->engine->applySettings($settings);
                $this->remember($settings);
            }

            $outcomes[$settings->index()] = ['version' => $settings->version(), 'status' => $applied ? self::APPLIED : self::UNCHANGED];
        }

        return $outcomes;
    }

    public function remember(IndexSettings $settings): void
    {
        $this->cache->forever($this->key($settings), $settings->fingerprint());
    }

    private function key(IndexSettings $settings): string
    {
        return 'comparo:search:settings:'.config('scout.driver').':'.$this->names->physical($settings->index());
    }
}
