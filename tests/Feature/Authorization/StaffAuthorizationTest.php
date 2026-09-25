<?php

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Accounts\Authorization\StaffRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function staff(StaffRole $role): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole($role->value));
}

it('grants every permission to super admins and only its bundle to other roles', function () {
    $superAdmin = staff(StaffRole::SuperAdmin);
    $analyst = staff(StaffRole::Analyst);

    foreach (Permission::cases() as $permission) {
        expect($superAdmin->can($permission->value))->toBeTrue($permission->value);
    }

    expect($analyst->can(Permission::ViewAnalytics->value))->toBeTrue()
        ->and($analyst->can(Permission::ManageCompliance->value))->toBeFalse()
        ->and($analyst->can(Permission::ConfigureRanking->value))->toBeFalse()
        ->and($analyst->can(Permission::ViewMerchantRisk->value))->toBeFalse();
});

it('keeps Horizon staff-only in every environment', function () {
    $horizon = '/'.trim((string) config('horizon.path'), '/');

    $this->get($horizon)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($horizon)->assertForbidden();
    $this->actingAs(staff(StaffRole::Analyst))->get($horizon)->assertForbidden();
    $this->actingAs(staff(StaffRole::MarketplaceAdmin))->get($horizon)->assertOk();
});

it('shares only a whitelisted user payload with the frontend', function () {
    $user = staff(StaffRole::Support);

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.is_staff', true)
            ->missing('auth.user.password')
            ->missing('auth.user.two_factor_secret')
            ->missing('auth.user.remember_token'));

    $this->actingAs(User::factory()->create())->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_staff', false));
});
