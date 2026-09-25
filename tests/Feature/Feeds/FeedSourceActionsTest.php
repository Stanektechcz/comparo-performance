<?php

use App\Domain\Feeds\Actions\ChangeFeedSourceStatus;
use App\Domain\Feeds\Actions\CreateFeedSource;
use App\Domain\Feeds\Actions\FeedActor;
use App\Domain\Feeds\Actions\FeedSourceData;
use App\Domain\Feeds\Actions\SaveFeedMapping;
use App\Domain\Feeds\Actions\StoreFeedUpload;
use App\Domain\Feeds\Actions\UpdateFeedCredentials;
use App\Domain\Feeds\Actions\UpdateFeedSource;
use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\FeedSourceStatus;
use App\Domain\Feeds\FeedTransport;
use App\Domain\Feeds\Mapping\FieldMapping;
use App\Domain\Platform\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\FeedMapping;
use App\Models\FeedSource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Feeds\FeedPipelineFixtures;

function feedSourceData(array $overrides = []): FeedSourceData
{
    return new FeedSourceData(...[
        'name' => 'Main feed',
        'format' => FeedFormat::Csv,
        'transport' => FeedTransport::Url,
        'url' => FeedPipelineFixtures::FEED_URL,
        'currency' => 'EUR',
        'intervalMinutes' => 360,
        ...$overrides,
    ]);
}

function feedAuditRow(AuditAction $action): AuditLog
{
    return AuditLog::query()->where('action', $action->value)->sole();
}

beforeEach(function () {
    FeedPipelineFixtures::currencies();
    $this->merchant = FeedPipelineFixtures::merchant();
    $this->user = User::factory()->create();
    $this->actor = FeedActor::merchant($this->user);
});

it('creates a draft source and audits its settings with the URL token masked', function () {
    $country = Country::factory()->create(['code' => 'DE']);

    $source = app(CreateFeedSource::class)->handle($this->merchant->id, feedSourceData(['marketCountryCode' => 'de']), $this->actor);

    expect($source->status)->toBe(FeedSourceStatus::Draft)
        ->and($source->merchant_id)->toBe($this->merchant->id)
        ->and($source->country_id)->toBe($country->id)
        ->and($source->next_run_at)->toBeNull();

    $audit = feedAuditRow(AuditAction::FeedSourceCreated);
    expect($audit->actor_id)->toBe($this->user->id)
        ->and($audit->after['url'])->toBe('https://peaksupps.de/feeds/products.csv?…')
        ->and(json_encode($audit->after))->not->toContain('s3cret-feed-token');
});

it('refuses feed URLs outside the public internet without echoing them', function (string $url) {
    expect(fn () => feedSourceData(['url' => $url]))
        ->toThrow(InvalidArgumentException::class, 'The feed URL is not an allowed public http(s) address.');
})->with([
    'loopback' => 'http://127.0.0.1/feed.csv',
    'metadata' => 'http://169.254.169.254/latest',
    'file scheme' => 'file:///etc/passwd',
    'odd port' => 'https://peaksupps.de:8443/feed.csv',
]);

it('refuses unknown currencies and markets', function () {
    expect(fn () => app(CreateFeedSource::class)->handle($this->merchant->id, feedSourceData(['currency' => 'XYZ']), $this->actor))
        ->toThrow(InvalidArgumentException::class, 'The feed currency is not a supported currency.')
        ->and(fn () => app(CreateFeedSource::class)->handle($this->merchant->id, feedSourceData(['marketCountryCode' => 'ZZ']), $this->actor))
        ->toThrow(InvalidArgumentException::class, 'The feed market is not a known market.');
});

it('audits only the changed settings and forgets the checksum when parsing changes', function () {
    $source = FeedSource::factory()->active()->for($this->merchant)->create(['name' => 'Main feed', 'url' => FeedPipelineFixtures::FEED_URL]);

    app(UpdateFeedSource::class)->handle($source, feedSourceData(['encoding' => 'Windows-1250', 'intervalMinutes' => 360]), $this->actor);

    $audit = feedAuditRow(AuditAction::FeedSourceUpdated);
    expect($audit->before)->toBe(['encoding' => 'UTF-8', 'delimiter' => ','])
        ->and($audit->after)->toBe(['encoding' => 'Windows-1250', 'delimiter' => null])
        ->and($source->refresh()->last_checksum)->toBeNull();
});

it('writes nothing for an update without changes', function () {
    $source = app(CreateFeedSource::class)->handle($this->merchant->id, feedSourceData(), $this->actor);

    app(UpdateFeedSource::class)->handle($source, feedSourceData(), $this->actor);

    expect(AuditLog::query()->where('action', AuditAction::FeedSourceUpdated->value)->exists())->toBeFalse();
});

it('saves mappings as new versions, moves the current flag and audits the change', function () {
    $source = FeedSource::factory()->active()->for($this->merchant)->create();
    $save = app(SaveFeedMapping::class);

    $first = $save->handle($source, FieldMapping::suggest(['sku', 'name', 'price', 'availability', 'url']), $this->actor);
    $second = $save->handle($source, FieldMapping::suggest(['sku', 'name', 'price', 'availability', 'link']), $this->actor, 'link column');
    $same = $save->handle($source, FieldMapping::suggest(['sku', 'name', 'price', 'availability', 'link']), $this->actor);

    expect([$first->version, $second->version])->toBe([1, 2])
        ->and($same->id)->toBe($second->id)
        ->and($first->refresh()->is_current)->toBeFalse()
        ->and($second->refresh()->is_current)->toBeTrue()
        ->and($second->created_by_user_id)->toBe($this->user->id)
        ->and(FeedMapping::query()->count())->toBe(2)
        ->and($source->refresh()->last_checksum)->toBeNull();

    $audits = AuditLog::query()->where('action', AuditAction::FeedSourceMappingChanged->value)->orderBy('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits[1]->before['version'])->toBe(1)
        ->and($audits[1]->after['field_map']['product_url'])->toBe('link');
});

it('stores credentials encrypted and never serialises, audits or logs them', function () {
    Log::spy();
    $source = FeedSource::factory()->for($this->merchant)->create();

    app(UpdateFeedCredentials::class)->handle($source, ['type' => 'basic', 'username' => 'feed-user', 'password' => 'hunter2-secret', 'extra' => 'dropped'], $this->actor);

    $source->refresh();
    $raw = FeedSource::query()->whereKey($source->id)->toBase()->value('credentials');
    $audit = feedAuditRow(AuditAction::FeedSourceCredentialsChanged);

    expect($source->credentials)->toBe(['type' => 'basic', 'username' => 'feed-user', 'password' => 'hunter2-secret'])
        ->and($source->hasCredentials())->toBeTrue()
        ->and((string) $raw)->not->toContain('hunter2-secret')
        ->and(json_encode($source->toArray()))->not->toContain('hunter2-secret')
        ->and($source->toJson())->not->toContain('feed-user')
        ->and($audit->after)->toBe(['credentials_changed' => true])
        ->and($audit->before)->toBeNull();
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('error');
});

it('clears credentials and rejects incomplete ones without echoing secrets', function () {
    $source = FeedSource::factory()->for($this->merchant)->withCredentials(['type' => 'bearer', 'token' => 'tok-123'])->create();
    $update = app(UpdateFeedCredentials::class);

    try {
        $update->handle($source, ['type' => 'basic', 'password' => 'only-the-password'], $this->actor);
        $this->fail('Incomplete credentials were accepted.');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->not->toContain('only-the-password');
    }

    $update->handle($source, null, $this->actor);

    expect($source->refresh()->hasCredentials())->toBeFalse();
});

it('lets the merchant pause and resume, audited with the reason', function () {
    $source = FeedSource::factory()->active()->for($this->merchant)->create();
    $change = app(ChangeFeedSourceStatus::class);

    $change->handle($source, FeedSourceStatus::Paused, $this->actor, 'holiday');
    expect($source->refresh()->status)->toBe(FeedSourceStatus::Paused)
        ->and($source->next_run_at)->toBeNull();

    $this->travelTo('2026-09-25 12:00:00');
    $change->handle($source, FeedSourceStatus::Active, $this->actor);

    expect($source->refresh()->status)->toBe(FeedSourceStatus::Active)
        ->and($source->next_run_at?->toDateTimeString())->toBe('2026-09-25 12:00:00');

    $audits = AuditLog::query()->where('action', AuditAction::FeedSourceStatusChanged->value)->orderBy('id')->get();
    expect($audits->map(fn (AuditLog $log) => [$log->before['status'], $log->after['status'], $log->after['reason']])->all())
        ->toBe([['active', 'paused', 'holiday'], ['paused', 'active', null]]);
});

it('reserves disabling for staff', function () {
    $source = FeedSource::factory()->active()->for($this->merchant)->create();

    expect(fn () => app(ChangeFeedSourceStatus::class)->handle($source, FeedSourceStatus::Disabled, $this->actor))
        ->toThrow(InvalidFeedTransition::class)
        ->and($source->refresh()->status)->toBe(FeedSourceStatus::Active);

    app(ChangeFeedSourceStatus::class)->handle($source, FeedSourceStatus::Disabled, FeedActor::staff(User::factory()->create()), 'abuse');

    expect($source->refresh()->status)->toBe(FeedSourceStatus::Disabled)
        ->and($source->status_reason)->toBe('abuse');
});

it('stores uploads privately under the merchant directory with a generated name', function () {
    Storage::fake('local');
    Storage::fake('public');
    $file = UploadedFile::fake()->createWithContent('../../evil.php', FeedPipelineFixtures::csv([FeedPipelineFixtures::row('PEA-1')]));

    $path = app(StoreFeedUpload::class)->handle($this->merchant->id, FeedFormat::Csv, $file);

    expect($path)->toMatch('#^feeds/'.$this->merchant->id.'/[0-9a-f\-]{36}\.csv$#')
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses uploads above the payload limit', function () {
    Storage::fake('local');
    config(['comparo.feeds.max_payload_bytes' => 10]);

    expect(fn () => app(StoreFeedUpload::class)->handle($this->merchant->id, FeedFormat::Csv, UploadedFile::fake()->createWithContent('feed.csv', str_repeat('x', 11))))
        ->toThrow(InvalidArgumentException::class)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
