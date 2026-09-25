<?php

namespace App\Domain\Platform\Features;

/**
 * The sole reader of config('features'). Every call site checks a flag
 * through this service, never through config() directly (A-17).
 */
final class FeatureFlags
{
    public function enabled(Feature $feature): bool
    {
        return (bool) config("features.{$feature->value}", true);
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
