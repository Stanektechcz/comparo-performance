<?php

use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditActor;
use App\Domain\Platform\Audit\AuditChanges;
use App\Domain\Platform\Audit\AuditLogger;
use App\Domain\Platform\Audit\AuditRedactor;
use App\Http\Middleware\AssignCorrelationId;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

function auditLogger(): AuditLogger
{
    return app(AuditLogger::class);
}

it('records a user action with the subject morph class and the correlation id', function () {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create();
    Context::add('correlation_id', 'corr-123');

    $log = auditLogger()->record(
        AuditAction::FeedSourceStatusChanged,
        AuditActor::user($user),
        $merchant,
        ['status' => 'active'],
        ['status' => 'paused'],
    );

    $stored = AuditLog::query()->findOrFail($log->id);

    expect($stored->actor_type)->toBe('user')
        ->and($stored->actor_id)->toBe($user->id)
        ->and($stored->action)->toBe('feed_source.status_changed')
        ->and($stored->auditable_type)->toBe($merchant->getMorphClass())
        ->and($stored->auditable_type)->toBe(Merchant::class)
        ->and($stored->auditable_id)->toBe($merchant->id)
        ->and($stored->before)->toBe(['status' => 'active'])
        ->and($stored->after)->toBe(['status' => 'paused'])
        ->and($stored->correlation_id)->toBe('corr-123');
});

it('records a system action without a request', function () {
    $log = auditLogger()->record(AuditAction::FeedRunCancelled, AuditActor::system('feeds.scheduler'));

    $stored = AuditLog::query()->findOrFail($log->id);

    expect($stored->actor_type)->toBe('system')
        ->and($stored->actor_id)->toBeNull()
        ->and($stored->auditable_type)->toBeNull()
        ->and($stored->auditable_id)->toBeNull()
        ->and($stored->before)->toBeNull()
        ->and($stored->after)->toBe([AuditLogger::SYSTEM_COMPONENT_KEY => 'feeds.scheduler'])
        ->and($stored->correlation_id)->toBeNull()
        ->and($stored->ip_address)->toBeNull()
        ->and($stored->user_agent)->toBeNull();
});

it('refuses a system actor without a component name', function () {
    AuditActor::system('  ');
})->throws(InvalidArgumentException::class);

it('refuses an unsaved subject', function () {
    auditLogger()->record(AuditAction::FeedSourceCreated, AuditActor::system('test'), new Merchant);
})->throws(InvalidArgumentException::class);

it('never stores secrets, however deeply they are nested', function () {
    auditLogger()->record(
        AuditAction::FeedSourceCredentialsChanged,
        AuditActor::system('test'),
        before: ['credentials' => ['password' => 'hunter2-secret-value']],
        after: [
            'feed' => ['url' => 'https://example.test/feed.csv', 'api_key' => 'key-AAA111'],
            'headers' => ['Authorization' => 'Bearer tok-BBB222', 'Cookie' => 'session=CCC333'],
            'client_secret' => 'sec-DDD444',
            'accessToken' => 'tok-EEE555',
            'apikey' => 'key-FFF666',
            'credentials_changed' => true,
        ],
    );

    $raw = DB::table('audit_logs')->select(['before', 'after'])->first();
    $stored = AuditLog::query()->firstOrFail();

    foreach (['hunter2-secret-value', 'key-AAA111', 'tok-BBB222', 'CCC333', 'sec-DDD444', 'tok-EEE555', 'key-FFF666'] as $secret) {
        expect($raw->before.$raw->after)->not->toContain($secret);
    }

    expect($stored->before)->toBe(['credentials' => AuditRedactor::MASK])
        ->and($stored->after['feed'])->toBe(['url' => 'https://example.test/feed.csv', 'api_key' => AuditRedactor::MASK])
        ->and($stored->after['headers'])->toBe(['Authorization' => AuditRedactor::MASK, 'Cookie' => AuditRedactor::MASK])
        ->and($stored->after['credentials_changed'])->toBeTrue();
});

it('redacts secrets inside objects and survives odd input', function () {
    $object = new stdClass;
    $object->password = 'obj-secret';
    $object->name = 'feed';

    $deep = ['token' => 'deep-secret'];
    for ($level = 0; $level < 40; $level++) {
        $deep = ['level' => $deep];
    }

    $redacted = (new AuditRedactor)->redact([
        'object' => $object,
        'collection' => collect(['secret' => 'col-secret', 'kept' => 1]),
        'action' => AuditAction::MatchingDecided,
        'nan' => NAN,
        'invalid_utf8' => "caf\xC3",
        'resource' => fopen('php://memory', 'r'),
        'list' => [['password' => 'x'], 2],
        'deep' => $deep,
        7 => 'numeric key',
    ]);

    expect($redacted['object'])->toBe(['password' => AuditRedactor::MASK, 'name' => 'feed'])
        ->and($redacted['collection'])->toBe(['secret' => AuditRedactor::MASK, 'kept' => 1])
        ->and($redacted['action'])->toBe('matching.decided')
        ->and($redacted['nan'])->toBe('NAN')
        ->and(mb_check_encoding($redacted['invalid_utf8'], 'UTF-8'))->toBeTrue()
        ->and($redacted['resource'])->toBe('[unserializable]')
        ->and($redacted['list'])->toBe([['password' => AuditRedactor::MASK], 2])
        ->and(json_encode($redacted, JSON_THROW_ON_ERROR))->not->toContain('deep-secret')
        ->and($redacted[7])->toBe('numeric key');
});

it('extracts only the changed attributes of a model, without timestamps', function () {
    $user = User::factory()->create(['name' => 'Before Name']);

    $user->name = 'After Name';
    $pending = AuditChanges::fromModel($user);

    $user->save();
    $saved = AuditChanges::fromModel($user);

    $expected = ['before' => ['name' => 'Before Name'], 'after' => ['name' => 'After Name']];

    expect($pending)->toBe($expected)
        ->and($saved)->toBe($expected);
});

it('masks hidden attributes and decodes JSON casts so nested secrets are redacted', function () {
    $user = User::factory()->create();
    $user->password = 'new-password';

    expect(AuditChanges::fromModel($user))
        ->toBe(['before' => ['password' => AuditRedactor::MASK], 'after' => ['password' => AuditRedactor::MASK]]);

    $model = new class extends Model
    {
        protected $casts = ['settings' => 'array'];
    };
    $model->setRawAttributes(['settings' => json_encode(['url' => 'a', 'token' => 't1'])], sync: true);
    $model->setAttribute('settings', ['url' => 'b', 'token' => 't2']);

    $changes = AuditChanges::fromModel($model);

    expect($changes['after']['settings'])->toBe(['url' => 'b', 'token' => 't2'])
        ->and((new AuditRedactor)->redact($changes['after']))->toBe(['settings' => ['url' => 'b', 'token' => AuditRedactor::MASK]]);
});

it('leaves no audit row when the surrounding transaction rolls back', function () {
    $merchant = Merchant::factory()->create();

    try {
        DB::transaction(function () use ($merchant) {
            auditLogger()->record(AuditAction::FeedSourceDeleted, AuditActor::system('test'), $merchant);

            throw new RuntimeException('action failed');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(AuditLog::query()->count())->toBe(0);
});

it('captures the client ip, user agent and correlation id of the request', function () {
    $user = User::factory()->create();
    Route::get('/__audit-probe', function () use ($user) {
        auditLogger()->record(AuditAction::FeedRunStartedManually, AuditActor::user($user));

        return response()->noContent();
    });
    $correlationId = (string) Str::uuid();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->withHeaders(['User-Agent' => 'PestAgent/1.0 '.str_repeat('x', 600), AssignCorrelationId::HEADER => $correlationId])
        ->get('/__audit-probe')
        ->assertNoContent()
        ->assertHeader(AssignCorrelationId::HEADER, $correlationId);

    $stored = AuditLog::query()->firstOrFail();

    expect($stored->ip_address)->toBe('203.0.113.7')
        ->and($stored->user_agent)->toStartWith('PestAgent/1.0 ')
        ->and(mb_strlen((string) $stored->user_agent))->toBe(512)
        ->and($stored->correlation_id)->toBe($correlationId)
        ->and(Context::getHidden('ip_address'))->toBe('203.0.113.7')
        ->and(Context::get('ip_address'))->toBeNull();
});
