<?php

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Accounts\Authorization\StaffRole;
use App\Domain\Merchants\MerchantRole;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Merchant isolation (docs/adr/0005): negative tests first.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->merchantA = Merchant::factory()->create();
    $this->merchantB = Merchant::factory()->create();
    $this->offerA = Offer::factory()->create(['merchant_id' => $this->merchantA->id]);
    $this->offerB = Offer::factory()->create(['merchant_id' => $this->merchantB->id]);

    $this->ownerA = User::factory()->create();
    $this->merchantA->members()->attach($this->ownerA, ['role' => MerchantRole::Owner->value]);
    $this->analystA = User::factory()->create();
    $this->merchantA->members()->attach($this->analystA, ['role' => MerchantRole::Analyst->value]);
});

it('merchant_a_cannot_view_merchant_b_offer', function () {
    Sanctum::actingAs($this->ownerA);

    $this->getJson(route('api.merchant.v1.offers.show', $this->offerB->id))->assertNotFound();
    $this->getJson(route('api.merchant.v1.offers.show', $this->offerA->id))->assertOk()->assertJsonPath('data.id', $this->offerA->id);
});

it('lists only the merchant\'s own offers', function () {
    Sanctum::actingAs($this->ownerA);

    $ids = collect($this->getJson(route('api.merchant.v1.offers.index'))->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$this->offerA->id]);
});

it('merchant_a_cannot_edit_merchant_b_offer', function () {
    expect($this->ownerA->can('update', $this->offerB))->toBeFalse()
        ->and($this->ownerA->can('update', $this->offerA))->toBeTrue()
        ->and($this->analystA->can('update', $this->offerA))->toBeFalse();
});

it('merchant_cannot_access_internal_risk_score', function () {
    Sanctum::actingAs($this->ownerA);

    $body = $this->getJson(route('api.merchant.v1.offers.show', $this->offerA->id))->assertOk()->getContent();

    expect($body)->not->toContain('risk')
        ->not->toContain('trust')
        ->not->toContain('commission')
        ->and($this->ownerA->can(Permission::ViewMerchantRisk->value))->toBeFalse();
});

it('requires authentication for the merchant API', function () {
    $this->getJson(route('api.merchant.v1.offers.index'))->assertUnauthorized();
});

it('lets staff with the offers permission view any offer through the policy', function () {
    $admin = tap(User::factory()->create(), fn (User $user) => $user->assignRole(StaffRole::MarketplaceAdmin->value));

    expect($admin->can('view', $this->offerB))->toBeTrue()
        ->and(User::factory()->create()->can('view', $this->offerB))->toBeFalse();
});
