<?php

namespace App\Http\Middleware;

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Merchants\MerchantContext;
use App\Domain\Platform\Features\FeatureFlags;
use App\Domain\Platform\Markets\MarketContext;
use App\Http\Presenters\MarketPresenter;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default. Everything is whitelisted:
     * the user model is never serialized wholesale.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->user($request->user()),
            ],
            'market' => fn (): array => app(MarketPresenter::class)->shared(app(MarketContext::class)),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'features' => fn (): array => app(FeatureFlags::class)->clientFlags(),
            'merchantContext' => fn (): ?array => app()->bound(MerchantContext::class)
                ? app(MerchantContext::class)->toArray()
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function user(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'is_staff' => $user->can(Permission::AccessStaffConsole->value),
        ];
    }
}
