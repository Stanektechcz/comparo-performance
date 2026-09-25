<?php

namespace App\Domain\Platform\Features;

/**
 * The sole reader of config('features'). Every call site checks a flag
 * through this service, never through config() directly (A-17).
 *
 * Fails CLOSED: a flag whose config key is missing (a stale config cache, a
 * typo, a flag added without its config/features.php entry) is off. The
 * defaults live in config/features.php, explicitly per flag.
 */
final class FeatureFlags
{
    public function enabled(Feature $feature): bool
    {
        return (bool) config("features.{$feature->value}", false);
    }

    /**
     * Client-visible flags only, for the shared Inertia prop.
     *
     * @return array<string, bool>
     */
    public function clientFlags(): array
    {
        $flags = [];

        foreach (Feature::cases() as $feature) {
            if ($feature->clientVisible()) {
                $flags[$feature->value] = $this->enabled($feature);
            }
        }

        return $flags;
    }
}
