<?php

namespace App\Providers;

use App\Domain\Accounts\Authorization\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeMailNotificationsTo(...) — wire to the on-call channel when production exists.
    }

    /**
     * Horizon is staff-only in every environment (including local): the
     * default "allow everything locally" rule is replaced by the permission gate.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(static fn (Request $request): bool => Gate::check('viewHorizon', [$request->user()]));
    }

    /**
     * Register the Horizon gate.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static fn (?User $user = null): bool => $user?->can(Permission::ViewHorizon->value) ?? false);
    }
}
