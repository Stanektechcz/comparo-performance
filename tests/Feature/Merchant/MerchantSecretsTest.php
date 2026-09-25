<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Feature\Merchant\MerchantPortal;
use Tests\Feature\Merchant\MerchantRouteMap;

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();
    $this->tenant = MerchantPortal::tenant();
    $this->owner = MerchantPortal::member($this->tenant['merchant']);
    $this->ids = ['feed' => $this->tenant['feed']->id, 'run' => $this->tenant['run']->id, 'listing' => $this->tenant['listing']->id];
});

function responseBody(TestResponse $response): string
{
    $body = $response->baseResponse instanceof StreamedResponse
        ? $response->streamedContent()
        : (string) $response->getContent();

    return $body.json_encode(session()->all());
}

dataset('merchant pages', MerchantRouteMap::names('read'));

it('never serialises feed credentials or the raw feed URL on any merchant page', function (string $name) {
    $response = $this->actingAs($this->owner)
        ->withSession(MerchantPortal::passwordConfirmed())
        ->get(MerchantRouteMap::url($name, $this->ids))
        ->assertOk();

    $body = responseBody($response);

    expect($body)->not->toContain(MerchantPortal::PLANTED_PASSWORD)
        ->and($body)->not->toContain(MerchantPortal::PLANTED_TOKEN)
        ->and($body)->not->toContain('feed-user')
        ->and($body)->not->toContain('payload_path')
        ->and($body)->not->toContain((string) $this->tenant['run']->payload_path);
})->with('merchant pages');

it('never serialises credentials in Inertia JSON responses either', function (string $name) {
    $response = $this->actingAs($this->owner)
        ->withSession(MerchantPortal::passwordConfirmed())
        ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get(MerchantRouteMap::url($name, $this->ids));

    expect(responseBody($response))->not->toContain(MerchantPortal::PLANTED_PASSWORD)
        ->not->toContain(MerchantPortal::PLANTED_TOKEN);
})->with(['merchant.feeds.index', 'merchant.feeds.show', 'merchant.feeds.edit', 'merchant.feeds.mapping.edit', 'merchant.feeds.runs.show', 'merchant.matching.listings.show']);

it('keeps newly saved credentials out of every page, the session and the audit log', function () {
    $this->actingAs($this->owner)->withSession(MerchantPortal::passwordConfirmed())
        ->put(route('merchant.feeds.credentials.update', $this->ids['feed']), [
            'type' => 'header', 'header_name' => 'X-Api-Key', 'header_value' => MerchantPortal::PLANTED_HEADER_VALUE,
        ])->assertRedirect();

    foreach (MerchantRouteMap::names('read') as $name) {
        $body = responseBody($this->actingAs($this->owner)->withSession(MerchantPortal::passwordConfirmed())->get(MerchantRouteMap::url($name, $this->ids)));

        expect($body)->not->toContain(MerchantPortal::PLANTED_HEADER_VALUE, "{$name} leaked the credential.");
    }

    expect(AuditLog::query()->get()->toJson())->not->toContain(MerchantPortal::PLANTED_HEADER_VALUE)
        ->not->toContain(MerchantPortal::PLANTED_TOKEN);
});
