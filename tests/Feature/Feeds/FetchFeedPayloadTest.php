<?php

use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\StartFeedRun;
use App\Domain\Feeds\Events\FeedFailed;
use App\Domain\Feeds\Events\FeedImported;
use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\FeedItemMatchStatus;
use App\Domain\Feeds\FeedItemValidationStatus;
use App\Domain\Feeds\FeedRunOutcome;
use App\Domain\Feeds\FeedRunStatus;
use App\Domain\Feeds\FeedRunTrigger;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\Fetching\FeedFetcher;
use App\Domain\Feeds\Fetching\FeedFetchException;
use App\Domain\Feeds\Jobs\FetchFeedPayload;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Cache\CatalogCacheVersion;
use App\Models\AuditLog;
use App\Models\FeedError;
use App\Models\FeedItem;
use App\Models\FeedRun;
use App\Models\FeedSource;
use App\Models\MerchantProduct;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Feeds\FeedPipelineFixtures;

function feedCsv(): string
{
    return FeedPipelineFixtures::csv([
        FeedPipelineFixtures::row('PEA-186'),
        FeedPipelineFixtures::row('PEA-190', '32.90'),
        FeedPipelineFixtures::row('PEA-210', '27.90'),
    ]);
}

function serveFeed(string $body = '', int $status = 200, string $contentType = 'text/csv'): Factory
{
    return FeedPipelineFixtures::fakeFetcher([
        FeedPipelineFixtures::MERCHANT_DOMAIN.'/*' => Factory::response($body === '' ? feedCsv() : $body, $status, ['Content-Type' => $contentType]),
    ]);
}

function runFetch(FeedRun $run): FetchFeedPayload
{
    $job = (new FetchFeedPayload($run->id, $run->feed_source_id))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

beforeEach(function () {
    $this->travelTo('2026-09-25 10:00:00');
    Http::preventStrayRequests();
    Storage::fake('local');
    Event::fake([FeedImported::class, FeedFailed::class]);
    FeedPipelineFixtures::currencies();
    $this->user = User::factory()->create();
    $this->merchant = FeedPipelineFixtures::merchant();
    $this->source = FeedSource::factory()->for($this->merchant)->url(FeedPipelineFixtures::FEED_URL)->create();
});

it('imports a URL feed end to end and activates the draft source', function () {
    $http = serveFeed();

    $run = app(StartFeedRun::class)->handle($this->source, FeedRunTrigger::Manual, FeedActor::merchant($this->user))->refresh();

    expect($run->status)->toBe(FeedRunStatus::Completed)
        ->and($run->outcome)->toBe(FeedRunOutcome::Published)
        ->and($run->only(['rows_read', 'rows_valid', 'rows_invalid', 'warnings', 'errors']))
        ->toBe(['rows_read' => 3, 'rows_valid' => 3, 'rows_invalid' => 0, 'warnings' => 0, 'errors' => 0])
        ->and($run->checksum)->toBe(hash('sha256', feedCsv()))
        ->and($run->payload_bytes)->toBe(strlen(feedCsv()))
        ->and(Storage::disk('local')->get((string) $run->payload_path))->toBe(feedCsv())
        ->and($run->payload_path)->toStartWith('feeds/'.$this->merchant->id.'/')
        ->and($run->fetched_at)->not->toBeNull()
        ->and($run->parsed_at)->not->toBeNull()
        ->and($run->normalized_at)->not->toBeNull()
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->duration_ms)->toBe(0);

    $this->source->refresh();
    expect($this->source->status)->toBe(FeedSourceStatus::Active)
        ->and($this->source->last_checksum)->toBe($run->checksum)
        ->and($this->source->last_success_at?->toDateTimeString())->toBe('2026-09-25 10:00:00')
        ->and($this->source->next_run_at?->toDateTimeString())->toBe('2026-09-25 16:00:00')
        ->and($this->source->consecutive_failures)->toBe(0);

    $items = FeedItem::query()->where('feed_run_id', $run->id)->orderBy('row_number')->get();
    expect($items->pluck('merchant_sku')->all())->toBe(['PEA-186', 'PEA-190', 'PEA-210'])
        ->and($items->pluck('price_minor')->all())->toBe([4054, 3290, 2790])
        ->and($items->every(fn (FeedItem $item) => $item->validation_status === FeedItemValidationStatus::Valid
            && $item->match_status === FeedItemMatchStatus::Unmatched
            && $item->diff_action === 'not_linked'
            && $item->raw_payload === null))->toBeTrue();

    $http->assertSentCount(1);
    Event::assertDispatched(FeedImported::class, fn (FeedImported $event) => $event->runId === $run->id && $event->outcome === 'published');
    $activation = AuditLog::query()->where('action', AuditAction::FeedSourceStatusChanged->value)->sole();
    expect($activation->actor_type)->toBe('system')
        ->and($activation->after)->toMatchArray(['status' => 'active', 'reason' => 'first_successful_run']);
});

it('completes an unchanged payload without parsing and re-confirms listings and offer freshness', function () {
    $this->source->forceFill(['status' => FeedSourceStatus::Active, 'last_checksum' => hash('sha256', feedCsv())])->save();
    $listing = MerchantProduct::factory()->fromFeed($this->source)->create(['last_seen_at' => now()->subDay()]);
    $offer = Offer::factory()->forListing($listing)->create(['source_updated_at' => now()->subHours(2)]);
    $missing = MerchantProduct::factory()->fromFeed($this->source)->missing()->create();
    $inactive = Offer::factory()->forListing(MerchantProduct::factory()->fromFeed($this->source)->create())->inactive()->create(['source_updated_at' => now()->subHours(3)]);
    $versionBefore = app(CatalogCacheVersion::class)->forProduct($offer->product_id);
    serveFeed();
    $run = FeedRun::factory()->forSource($this->source)->scheduled()->queued()->create();

    runFetch($run);

    $run->refresh();
    expect($run->status)->toBe(FeedRunStatus::Completed)
        ->and($run->outcome)->toBe(FeedRunOutcome::Unchanged)
        ->and($run->payload_path)->toBeNull()
        ->and($run->payload_purged_at)->not->toBeNull()
        ->and($run->parsed_at)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(FeedItem::query()->count())->toBe(0)
        ->and($listing->refresh()->last_seen_run_id)->toBe($run->id)
        ->and($listing->last_seen_at?->toDateTimeString())->toBe('2026-09-25 10:00:00')
        ->and($missing->refresh()->last_seen_run_id)->toBeNull()
        ->and($offer->refresh()->source_updated_at->toDateTimeString())->toBe('2026-09-25 10:00:00')
        ->and($offer->last_feed_run_id)->toBe($run->id)
        ->and($inactive->refresh()->last_feed_run_id)->toBeNull()
        ->and(app(CatalogCacheVersion::class)->forProduct($offer->product_id))->not->toBe($versionBefore);
    Event::assertDispatched(FeedImported::class, fn (FeedImported $event) => $event->outcome === 'unchanged');
});

it('keeps offer freshness untouched on unchanged payloads when configured', function () {
    config(['comparo.feeds.unchanged_refreshes_freshness' => false]);
    $this->source->forceFill(['status' => FeedSourceStatus::Active, 'last_checksum' => hash('sha256', feedCsv())])->save();
    $listing = MerchantProduct::factory()->fromFeed($this->source)->create();
    $offer = Offer::factory()->forListing($listing)->create(['source_updated_at' => now()->subHours(2)]);
    serveFeed();
    $run = FeedRun::factory()->forSource($this->source)->queued()->create();

    runFetch($run);

    expect($listing->refresh()->last_seen_run_id)->toBe($run->id)
        ->and($offer->refresh()->source_updated_at->toDateTimeString())->toBe('2026-09-25 08:00:00')
        ->and($offer->last_feed_run_id)->toBeNull();
});

it('leaves transient failures to the queue retry without failing the run', function (int $status, ?string $host) {
    if ($host === null) {
        serveFeed('', $status);
    } else {
        FeedPipelineFixtures::fakeFetcher([]);
        $this->source->forceFill(['url' => "https://{$host}/feed.csv"])->save();
    }
    $run = FeedRun::factory()->forSource($this->source)->queued()->create();

    expect(fn () => runFetch($run))->toThrow(FeedFetchException::class);

    $run->refresh();
    expect($run->status)->toBe(FeedRunStatus::Fetching)
        ->and($run->failure_code)->toBeNull();
    Event::assertNotDispatched(FeedFailed::class);
})->with([
    'server error' => [503, null],
    'name does not resolve' => [200, 'unknown.example.com'],
]);

it('fails permanent fetch errors at once and marks the job failed', function (int $status, FeedErrorCode $code) {
    serveFeed('nope', $status);
    $run = FeedRun::factory()->forSource($this->source)->queued()->create();

    $job = runFetch($run);

    $job->assertFailed();
    $run->refresh();
    expect($run->status)->toBe(FeedRunStatus::Failed)
        ->and($run->failure_code)->toBe($code->value)
        ->and($run->failure_reason)->toBe(__($code->messageKey(), $status === 404 ? ['status' => 404] : []))
        ->and(FeedError::query()->where('feed_run_id', $run->id)->sole()->only(['code', 'row_number']))
        ->toBe(['code' => $code->value, 'row_number' => null])
        ->and($this->source->refresh()->consecutive_failures)->toBe(1);
    Event::assertDispatched(FeedFailed::class, fn (FeedFailed $event) => $event->code === $code->value);
})->with([
    'not found' => [404, FeedErrorCode::HttpError],
    'unauthorised' => [401, FeedErrorCode::AuthFailed],
]);

it('refuses to fetch when URL fetching is switched off', function () {
    config(['features.feed-url-fetch' => false]);
    $http = serveFeed();
    $run = FeedRun::factory()->forSource($this->source)->queued()->create();

    runFetch($run)->assertFailed();

    expect($run->refresh()->failure_code)->toBe('FETCH_DISABLED');
    $http->assertNothingSent();
});

it('never leaks credentials into the run, its errors, audit rows or logs', function () {
    Log::spy();
    $this->source->forceFill(['credentials' => ['type' => 'bearer', 'token' => 'tok-super-secret']])->save();
    $http = serveFeed('denied', 403);
    $run = FeedRun::factory()->forSource($this->source)->queued()->create();

    runFetch($run);

    $http->assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer tok-super-secret'));
    $stored = json_encode([
        $run->refresh()->toArray(),
        FeedError::query()->get()->toArray(),
        AuditLog::query()->get()->toArray(),
    ]);
    expect($stored)->not->toContain('tok-super-secret')->not->toContain('s3cret-feed-token')
        ->and($this->source->refresh()->toJson())->not->toContain('tok-super-secret')
        ->and($run->failure_code)->toBe('AUTH_FAILED');
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
});

it('builds the production fetcher on an HTTP client without event listeners', function () {
    $fetcher = app(FeedFetcher::class);
    $http = (new ReflectionProperty(FeedFetcher::class, 'http'))->getValue($fetcher);

    expect($http)->toBeInstanceOf(Factory::class)
        ->and($http)->not->toBe(Http::getFacadeRoot())
        ->and($http->getDispatcher())->toBeNull();
});

it('imports an uploaded payload and keeps the stored file', function () {
    $source = FeedSource::factory()->for($this->merchant)->upload()->create();
    $path = FeedPipelineFixtures::storePayload($source, feedCsv());
    FeedPipelineFixtures::fakeFetcher([]);

    $run = app(StartFeedRun::class)->handle($source, FeedRunTrigger::Manual, FeedActor::merchant($this->user), uploadedPayloadPath: $path)->refresh();

    expect($run->status)->toBe(FeedRunStatus::Completed)
        ->and($run->rows_valid)->toBe(3)
        ->and($run->payload_path)->toBe($path)
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and($source->refresh()->status)->toBe(FeedSourceStatus::Active)
        ->and($source->next_run_at)->toBeNull();
});

it('ignores a second delivery of the fetch job once the run moved on', function () {
    $http = serveFeed();
    $run = app(StartFeedRun::class)->handle($this->source, FeedRunTrigger::Manual, FeedActor::merchant($this->user));

    runFetch($run);

    $http->assertSentCount(1);
    expect($run->refresh()->status)->toBe(FeedRunStatus::Completed)
        ->and(FeedItem::query()->count())->toBe(3);
});
