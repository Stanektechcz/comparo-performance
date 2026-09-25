<?php

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Merchants\MerchantRole;
use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Covers the always-present `auth.user.has_merchant_access` and
 * `auth.user.staff_can` shared props (App\Http\Middleware\HandleInertiaRequests),
 * which the merchant nav and staff "Catalogue matching" entry gate on
 * (resources/js/components/merchant/merchant-nav.tsx,
 * resources/js/components/app-sidebar.tsx). Both are cheap, whitelisted
 * booleans, never the full merchant/permission list.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('shares has_merchant_access true for a merchant member on the dashboard', function () {
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    $merchant->members()->attach($user, ['role' => MerchantRole::Owner->value]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.has_merchant_access', true));
});

it('shares has_merchant_access false for a user with no merchant membership', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.has_merchant_access', false));
});

it('shares review_matching true for staff with the matching.review permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::ReviewMatching->value);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.staff_can.review_matching', true));
});

it('shares review_matching false for staff without the matching.review permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::AccessStaffConsole->value);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.staff_can.review_matching', false));
});

it('shares neither flag for guests', function () {
    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

it('never exposes other permission names or merchant ids in the shared flags', function () {
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    $merchant->members()->attach($user, ['role' => MerchantRole::Owner->value]);
    $user->givePermissionTo([
        Permission::ReviewMatching->value,
        Permission::ManageCatalogue->value,
        Permission::ViewMerchants->value,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.has_merchant_access', true)
            ->where('auth.user.is_staff', false)
            // Only the whitelisted `review_matching` key: no other permission
            // names (e.g. `catalogue.manage`, `merchants.view`) and no
            // merchant id or membership list leak through.
            ->where('auth.user.staff_can', ['review_matching' => true])
            ->missing('auth.user.merchant_id')
            ->missing('auth.user.merchants')
            ->missing('auth.user.staff_can.manage_catalogue')
            ->missing('auth.user.staff_can.view_merchants'));
});
