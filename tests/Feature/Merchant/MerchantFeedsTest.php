<?php

use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\FeedTransport;
use App\Domain\Feeds\Jobs\FetchFeedPayload;
use App\Domain\Merchants\MerchantRole;
use App\Domain\Platform\Audit\AuditAction;
use App\Http\Presenters\Merchant\FeedErrorCsv;
use App\Models\AuditLog;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedMapping;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\Merchant;
use Database\Factories\CurrencyFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Merchant\MerchantPortal;

beforeEach(function () {
    Storage::fake('local');
    Bus::fake();
    CurrencyFactory::resolveId('EUR');
    $this->tenant = MerchantPortal::tenant();
    $this->merchant = $this->tenant['merchant'];
    $this->feed = $this->tenant['feed'];
    $this->owner = MerchantPortal::member($this->merchant);
});

function uploadFeed(FeedSource $source): FeedSource
{
    $source->forceFill(['transport' => FeedTransport::Upload, 'url' => null, 'interval_minutes' => null])->save();

    return $source;
}

describe('feed list and settings', function () {
    it('lists only the active merchant\'s feeds with a masked URL and the latest run', function () {
        MerchantPortal::tenant();

        $this->actingAs($this->owner)->get(route('merchant.feeds.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/feeds/index')
                ->has('feeds.data', 1)
                ->where('feeds.data.0.id', $this->feed->id)
                ->where('feeds.data.0.maskedUrl', 'https://feeds.example.com/listings.csv?…')
                ->where('feeds.data.0.hasCredentials', true)
                ->where('feeds.data.0.latestRun.id', $this->tenant['run']->id)
                ->where('feeds.data.0.latestRun.outcome.value', 'published_with_warnings')
                ->where('can.create', true));
    });

    it('shows an empty list to a merchant without feeds', function () {
        $user = MerchantPortal::member(Merchant::factory()->create(), MerchantRole::Analyst);

        $this->actingAs($user)->get(route('merchant.feeds.index'))
            ->assertInertia(fn (Assert $page) => $page->has('feeds.data', 0)->where('feeds.meta.total', 0)->where('can.create', false));
    });

    it('creates a URL feed as an audited draft for the active merchant', function () {
        $response = $this->actingAs($this->owner)->post(route('merchant.feeds.store'), [
            'name' => 'Main catalogue', 'format' => 'csv', 'transport' => 'url',
            'url' => 'https://shop.example.com/export.csv?key=abc', 'currency' => 'EUR', 'country' => 'DE',
            'encoding' => 'UTF-8', 'delimiter' => 'semicolon', 'interval_minutes' => 360,
        ]);

        $source = FeedSource::query()->where('name', 'Main catalogue')->sole();
        $response->assertRedirect(route('merchant.feeds.show', $source->id));

        expect($source->merchant_id)->toBe($this->merchant->id)
            ->and($source->status)->toBe(FeedSourceStatus::Draft)
            ->and($source->delimiter)->toBe(';')
            ->and($source->interval_minutes)->toBe(360);

        $audit = AuditLog::query()->where('action', AuditAction::FeedSourceCreated->value)->sole();
        expect($audit->actor_id)->toBe($this->owner->id)
            ->and(json_encode($audit->after))->not->toContain('key=abc');
    });

    it('refuses private or non-http feed URLs without echoing them', function (string $url) {
        $this->actingAs($this->owner)->from(route('merchant.feeds.create'))->post(route('merchant.feeds.store'), [
            'name' => 'Internal', 'format' => 'csv', 'transport' => 'url', 'url' => $url, 'currency' => 'EUR', 'encoding' => 'UTF-8',
        ])->assertSessionHasErrors('url');

        expect(FeedSource::query()->where('name', 'Internal')->exists())->toBeFalse()
            ->and(session('errors')->first('url'))->not->toContain($url);
    })->with(['http://127.0.0.1/feed.csv', 'ftp://shop.example.com/feed.csv', 'https://user:pw@shop.example.com/feed.csv', 'https://shop.example.com:8443/feed.csv']);

    it('keeps the stored URL when an update leaves the URL empty, and audits the change', function () {
        $this->actingAs($this->owner)->put(route('merchant.feeds.update', $this->feed->id), [
            'name' => 'Renamed feed', 'format' => 'csv', 'transport' => 'url', 'url' => '',
            'currency' => 'EUR', 'country' => 'DE', 'encoding' => 'UTF-8',
        ])->assertRedirect(route('merchant.feeds.show', $this->feed->id));

        $fresh = $this->feed->fresh();
        expect($fresh?->name)->toBe('Renamed feed')
            ->and($fresh?->url)->toBe($this->feed->url)
            ->and(AuditLog::query()->where('action', AuditAction::FeedSourceUpdated->value)->where('actor_id', $this->owner->id)->exists())->toBeTrue();
    });

    it('never sends the stored URL or credentials to the edit form', function () {
        $this->actingAs($this->owner)->get(route('merchant.feeds.edit', $this->feed->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/feeds/edit')
                ->where('defaults.maskedUrl', 'https://feeds.example.com/listings.csv?…')
                ->where('defaults.hasCredentials', true)
                ->missing('defaults.url')
                ->where('credentialsConfirmed', false)
                ->where('can.manageCredentials', true))
            ->assertDontSee(MerchantPortal::PLANTED_TOKEN)
            ->assertDontSee(MerchantPortal::PLANTED_PASSWORD);
    });
});

describe('mapping', function () {
    it('previews the latest payload with suggested mapping, missing fields and row issues', function () {
        $path = 'feeds/'.$this->merchant->id.'/sample.csv';
        Storage::disk('local')->put($path, "sku,title,price,currency,availability,url\nA-1,Whey 900 g,24.90,EUR,in_stock,https://shop.example.com/a\nA-2,Casein,abc,EUR,in_stock,https://shop.example.com/b\n");
        FeedRun::factory()->forSource($this->feed)->completed()->create(['payload_path' => $path]);

        $this->actingAs($this->owner)->get(route('merchant.feeds.mapping.edit', $this->feed->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/feeds/mapping')
                ->where('preview.available', true)
                ->where('preview.headers', ['sku', 'title', 'price', 'currency', 'availability', 'url'])
                ->where('preview.mappingSuggested', true)
                ->where('preview.mapping.merchant_sku', 'sku')
                ->has('preview.rows', 2)
                ->where('preview.rows.1.status', 'invalid')
                ->where('preview.rows.1.issues.0.code', 'INVALID_PRICE')
                ->where('preview.rows.1.issues.0.message', fn (string $message) => str_contains($message, '"abc"'))
                ->has('fields', 17));
    });

    it('previews an unsaved draft mapping', function () {
        $path = 'feeds/'.$this->merchant->id.'/sample.csv';
        Storage::disk('local')->put($path, "code,name\nA-1,Whey\n");
        FeedRun::factory()->forSource($this->feed)->completed()->create(['payload_path' => $path]);

        $this->actingAs($this->owner)->get(route('merchant.feeds.mapping.edit', [$this->feed->id, 'draft' => ['title' => 'code']]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.isDraft', true)
                ->where('preview.mapping', ['title' => 'code'])
                ->where('preview.missingRequired', fn ($missing) => collect($missing)->pluck('key')->contains('merchant_sku')));
    });

    it('says truthfully when there is no payload to preview yet', function () {
        $this->actingAs($this->owner)->get(route('merchant.feeds.mapping.edit', $this->feed->id))
            ->assertInertia(fn (Assert $page) => $page->where('preview.available', false)->where('preview.rows', []));
    });

    it('saves each changed mapping as a new version and nothing for an identical one', function () {
        $mapping = ['merchant_sku' => 'sku', 'title' => 'name', 'price' => 'price', 'availability' => 'stock', 'product_url' => 'link', 'gtin' => ''];

        $this->actingAs($this->owner)->put(route('merchant.feeds.mapping.update', $this->feed->id), ['mapping' => $mapping])
            ->assertRedirect(route('merchant.feeds.mapping.edit', $this->feed->id));
        $this->actingAs($this->owner)->put(route('merchant.feeds.mapping.update', $this->feed->id), ['mapping' => $mapping]);
        $this->actingAs($this->owner)->put(route('merchant.feeds.mapping.update', $this->feed->id), ['mapping' => [...$mapping, 'brand' => 'maker']]);

        $versions = FeedMapping::query()->where('feed_source_id', $this->feed->id)->orderBy('version')->get();
        expect($versions)->toHaveCount(2)
            ->and($versions[0]->field_map)->not->toHaveKey('gtin')
            ->and($versions[1]->is_current)->toBeTrue()
            ->and($versions[1]->field_map['brand'])->toBe('maker')
            ->and($versions[1]->created_by_user_id)->toBe($this->owner->id)
            ->and(AuditLog::query()->where('action', AuditAction::FeedSourceMappingChanged->value)->count())->toBe(2);
    });

    it('requires every required field and rejects unknown fields', function () {
        $this->actingAs($this->owner)->from(route('merchant.feeds.mapping.edit', $this->feed->id))
            ->put(route('merchant.feeds.mapping.update', $this->feed->id), ['mapping' => ['merchant_sku' => 'sku', 'bogus' => 'x']])
            ->assertSessionHasErrors(['mapping', 'mapping.title', 'mapping.price', 'mapping.availability', 'mapping.product_url'])
            ->assertSessionDoesntHaveErrors('mapping.currency');

        expect(FeedMapping::query()->where('feed_source_id', $this->feed->id)->exists())->toBeFalse();
    });
});

describe('runs', function () {
    it('queues a manual run, dispatches the pipeline and audits the merchant user', function () {
        $response = $this->actingAs($this->owner)->post(route('merchant.feeds.runs.store', $this->feed->id));

        $run = FeedRun::query()->where('feed_source_id', $this->feed->id)->where('status', FeedRunStatus::Queued)->sole();
        $response->assertRedirect(route('merchant.feeds.runs.show', [$this->feed->id, $run->id]));

        expect($run->trigger)->toBe(FeedRunTrigger::Manual)
            ->and($run->triggered_by_user_id)->toBe($this->owner->id);
        Bus::assertDispatched(FetchFeedPayload::class, fn (FetchFeedPayload $job): bool => $job->runId === $run->id);
        expect(AuditLog::query()->where('action', AuditAction::FeedRunStartedManually->value)->where('actor_id', $this->owner->id)->exists())->toBeTrue();
    });

    it('explains the manual-run cooldown with the retry time', function () {
        FeedRun::factory()->forSource($this->feed)->manual($this->owner)->completed()->create(['created_at' => now()->subMinutes(3)]);

        $this->actingAs($this->owner)->from(route('merchant.feeds.show', $this->feed->id))
            ->post(route('merchant.feeds.runs.store', $this->feed->id))
            ->assertSessionHasErrors(['run' => 'A manual run was started recently. You can start the next one in 12 minutes.']);
    });

    it('explains that a run is already in progress', function () {
        $active = FeedRun::factory()->forSource($this->feed)->running()->create();

        $this->actingAs($this->owner)->from(route('merchant.feeds.show', $this->feed->id))
            ->post(route('merchant.feeds.runs.store', $this->feed->id))
            ->assertSessionHasErrors(['run' => "Run #{$active->id} of this feed is still in progress. Wait for it to finish or cancel it first."]);
    });

    it('stores an upload on the private disk in the merchant directory and runs it', function () {
        uploadFeed($this->feed);
        $csv = "sku,title,price,currency,availability,url\nA-1,Whey,24.90,EUR,in_stock,https://shop.example.com/a\n";

        $this->actingAs($this->owner)
            ->post(route('merchant.feeds.uploads.store', $this->feed->id), ['file' => UploadedFile::fake()->createWithContent('export.csv', $csv)])
            ->assertRedirect();

        $run = FeedRun::query()->where('feed_source_id', $this->feed->id)->where('status', FeedRunStatus::Queued)->sole();
        expect($run->payload_path)->toStartWith('feeds/'.$this->merchant->id.'/')
            ->and($run->payload_path)->not->toContain('export');
        Storage::disk('local')->assertExists((string) $run->payload_path);
        expect(Storage::disk('local')->get((string) $run->payload_path))->toBe($csv);
        Bus::assertDispatched(FetchFeedPayload::class, fn (FetchFeedPayload $job): bool => $job->runId === $run->id);
    });

    it('unpacks gzip uploads before storing them', function () {
        uploadFeed($this->feed);
        $csv = "sku,title\nA-1,Whey\n";

        $this->actingAs($this->owner)
            ->post(route('merchant.feeds.uploads.store', $this->feed->id), ['file' => UploadedFile::fake()->createWithContent('export.csv.gz', (string) gzencode($csv))])
            ->assertRedirect();

        $run = FeedRun::query()->where('feed_source_id', $this->feed->id)->where('status', FeedRunStatus::Queued)->sole();
        expect(Storage::disk('local')->get((string) $run->payload_path))->toBe($csv);
    });

    it('rejects files that are not feeds, uploads to URL feeds, and removes the file when the run cannot start', function () {
        $this->actingAs($this->owner)->from(route('merchant.feeds.show', $this->feed->id))
            ->post(route('merchant.feeds.uploads.store', $this->feed->id), ['file' => UploadedFile::fake()->createWithContent('feed.csv', "a,b\n1,2\n")])
            ->assertSessionHasErrors(['file' => 'This feed is fetched from its URL. Files can only be uploaded to upload feeds.']);

        uploadFeed($this->feed);
        $this->actingAs($this->owner)->from(route('merchant.feeds.show', $this->feed->id))
            ->post(route('merchant.feeds.uploads.store', $this->feed->id), ['file' => UploadedFile::fake()->createWithContent('photo.csv', "\x89PNG\r\n\x1a\n".str_repeat("\0", 64))])
            ->assertSessionHasErrors(['file' => 'The file content is not a text, XML, JSON or gzip feed.']);

        FeedRun::factory()->forSource($this->feed)->running()->create();
        $this->actingAs($this->owner)->from(route('merchant.feeds.show', $this->feed->id))
            ->post(route('merchant.feeds.uploads.store', $this->feed->id), ['file' => UploadedFile::fake()->createWithContent('feed.csv', "a,b\n1,2\n")])
            ->assertSessionHasErrors('file');

        expect(Storage::disk('local')->allFiles('feeds/'.$this->merchant->id))->toBe([]);
    });

    it('shows run metrics, stages and grouped errors with actionable messages', function () {
        $run = $this->tenant['run'];

        $this->actingAs($this->owner)->get(route('merchant.feeds.runs.show', [$this->feed->id, $run->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('merchant/feeds/runs/show')
                ->where('run.id', $run->id)
                ->where('run.rowsRead', 12)
                ->has('run.stages', 8)
                ->has('run.metrics', 4)
                ->has('errorGroups', 2)
                ->where('errorGroups.0.code', 'INVALID_PRICE')
                ->where('errorGroups.0.count', 2)
                ->where('errorGroups.0.severity.label', 'Row skipped')
                ->where('errorGroups.0.message', fn (string $message) => str_contains($message, 'is not a valid positive price') && str_contains($message, '"price"'))
                ->has('errorGroups.0.samples', 2)
                ->where('errorGroups.1.code', 'INVALID_GTIN')
                ->has('errorRows.data', 3)
                ->where('can.cancel', true));

        $this->actingAs($this->owner)->get(route('merchant.feeds.runs.show', [$this->feed->id, $run->id, 'code' => 'INVALID_GTIN']))
            ->assertInertia(fn (Assert $page) => $page->where('errorFilter', 'INVALID_GTIN')->has('errorRows.data', 1));
    });

    it('shows the translated failure of a failed run', function () {
        $run = FeedRun::factory()->forSource($this->feed)->failed('AUTH_FAILED')->create(['failure_reason' => __('feeds.errors.AUTH_FAILED')]);

        $this->actingAs($this->owner)->get(route('merchant.feeds.runs.show', [$this->feed->id, $run->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('run.failure.code', 'AUTH_FAILED')
                ->where('run.failure.message', __('feeds.errors.AUTH_FAILED')));
    });

    it('exports the run errors as CSV with spreadsheet formulas neutralised', function () {
        $run = $this->tenant['run'];
        $item = FeedItem::factory()->create(['feed_run_id' => $run->id, 'merchant_sku' => '=cmd|calc!A1', 'row_number' => 2]);
        FeedError::factory()->forItem($item)->create(['code' => 'MISSING_GTIN', 'severity' => 'warning', 'field' => 'gtin', 'message_params' => null]);

        $response = $this->actingAs($this->owner)->get(route('merchant.feeds.runs.errors.export', [$this->feed->id, $run->id]));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        expect($csv)->toContain("'=cmd|calc!A1")
            ->and($csv)->not->toContain(',=cmd')
            ->and(substr_count($csv, "\n"))->toBe(5)
            ->and(FeedErrorCsv::neutralise('+1'))->toBe("'+1")
            ->and(FeedErrorCsv::neutralise('-1'))->toBe("'-1")
            ->and(FeedErrorCsv::neutralise('@SUM(A1)'))->toBe("'@SUM(A1)")
            ->and(FeedErrorCsv::neutralise("\tx"))->toBe("'\tx")
            ->and(FeedErrorCsv::neutralise("\rx"))->toBe("'\rx")
            ->and(FeedErrorCsv::neutralise('plain'))->toBe('plain');
    });

    it('cancels a queued run and refuses to cancel a finished one', function () {
        $queued = FeedRun::factory()->forSource($this->feed)->queued()->create();

        $this->actingAs($this->owner)->post(route('merchant.feeds.runs.cancel', [$this->feed->id, $queued->id]))
            ->assertRedirect(route('merchant.feeds.runs.show', [$this->feed->id, $queued->id]));
        expect($queued->fresh()?->status)->toBe(FeedRunStatus::Cancelled);

        $this->actingAs($this->owner)->from(route('merchant.feeds.show', $this->feed->id))
            ->post(route('merchant.feeds.runs.cancel', [$this->feed->id, $this->tenant['run']->id]))
            ->assertSessionHasErrors('run');
    });
});

describe('status', function () {
    it('pauses and resumes an active feed, audited with the merchant user', function () {
        $manager = MerchantPortal::member($this->merchant, MerchantRole::Manager);

        $this->actingAs($manager)->post(route('merchant.feeds.status.update', $this->feed->id), ['action' => 'pause'])->assertRedirect();
        expect($this->feed->fresh()?->status)->toBe(FeedSourceStatus::Paused);

        $this->actingAs($manager)->post(route('merchant.feeds.status.update', $this->feed->id), ['action' => 'resume'])->assertRedirect();
        expect($this->feed->fresh()?->status)->toBe(FeedSourceStatus::Active)
            ->and(AuditLog::query()->where('action', AuditAction::FeedSourceStatusChanged->value)->where('actor_id', $manager->id)->count())->toBe(2);
    });

    it('refuses to pause a draft feed with a friendly error', function () {
        $this->feed->forceFill(['status' => FeedSourceStatus::Draft])->save();

        $this->actingAs($this->owner)->from(route('merchant.feeds.show', $this->feed->id))
            ->post(route('merchant.feeds.status.update', $this->feed->id), ['action' => 'pause'])
            ->assertSessionHasErrors(['status' => 'Only an active feed can be paused.']);
    });
});

describe('credentials', function () {
    it('stores new credentials encrypted and audits only that they changed', function () {
        $this->actingAs($this->owner)->withSession(MerchantPortal::passwordConfirmed())
            ->put(route('merchant.feeds.credentials.update', $this->feed->id), [
                'type' => 'header', 'header_name' => 'X-Api-Key', 'header_value' => MerchantPortal::PLANTED_HEADER_VALUE,
            ])->assertRedirect();

        $fresh = $this->feed->fresh();
        expect($fresh?->credentials)->toBe(['type' => 'header', 'name' => 'X-Api-Key', 'value' => MerchantPortal::PLANTED_HEADER_VALUE])
            ->and((string) $fresh?->getRawOriginal('credentials'))->not->toContain(MerchantPortal::PLANTED_HEADER_VALUE);

        $audit = AuditLog::query()->where('action', AuditAction::FeedSourceCredentialsChanged->value)->sole();
        expect($audit->after)->toBe(['credentials_changed' => true])
            ->and($audit->actor_id)->toBe($this->owner->id);
    });

    it('clears credentials with the none type', function () {
        $this->actingAs($this->owner)->withSession(MerchantPortal::passwordConfirmed())
            ->put(route('merchant.feeds.credentials.update', $this->feed->id), ['type' => 'none'])->assertRedirect();

        expect($this->feed->fresh()?->hasCredentials())->toBeFalse();
    });

    it('never flashes submitted secrets back into the session on a validation error', function () {
        $this->actingAs($this->owner)->withSession(MerchantPortal::passwordConfirmed())
            ->from(route('merchant.feeds.edit', $this->feed->id))
            ->put(route('merchant.feeds.credentials.update', $this->feed->id), [
                'type' => 'header', 'header_name' => 'Host', 'header_value' => MerchantPortal::PLANTED_HEADER_VALUE,
            ])->assertSessionHasErrors(['header_name' => 'This header controls the connection and cannot be used for credentials.']);

        expect(json_encode(session()->all()))->not->toContain(MerchantPortal::PLANTED_HEADER_VALUE)
            ->and($this->feed->fresh()?->credentials['password'] ?? null)->toBe(MerchantPortal::PLANTED_PASSWORD);
    });
});
