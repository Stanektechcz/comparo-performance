<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\Fetching\FeedCredentials;
use App\Domain\Platform\Audit\AuditAction;
use App\Domain\Platform\Audit\AuditLogger;
use App\Models\FeedSource;
use Illuminate\Support\Facades\DB;

/**
 * Sets or clears a feed's access credentials.
 *
 * Input is the credentials array ({type: basic, username, password} |
 * {type: bearer, token} | {type: header, name, value}); it is validated through
 * {@see FeedCredentials::fromArray()} and only the recognised keys are stored,
 * encrypted by the model cast. `credentials` is not mass assignable, so it is
 * set explicitly. The audit row only records `{credentials_changed: true}`.
 */
final class UpdateFeedCredentials
{
    /** @var array<string, list<string>> */
    private const array KEYS = [
        'basic' => ['username', 'password'],
        'bearer' => ['token'],
        'header' => ['name', 'value'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>|null  $credentials  null clears them
     *
     * @throws \InvalidArgumentException for incomplete or invalid credentials (message without secrets)
     */
    public function handle(FeedSource $source, #[\SensitiveParameter] ?array $credentials, FeedActor $actor): FeedSource
    {
        $stored = $credentials === null ? null : $this->normalise($credentials);

        return DB::transaction(function () use ($source, $stored, $actor): FeedSource {
            $locked = FeedSource::query()->lockForUpdate()->findOrFail($source->id);
            $locked->forceFill(['credentials' => $stored])->save();

            $this->audit->record(AuditAction::FeedSourceCredentialsChanged, $actor->audit, $locked, after: [
                'credentials_changed' => true,
            ]);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function normalise(#[\SensitiveParameter] array $credentials): array
    {
        $type = FeedCredentials::fromArray($credentials)->type;
        $stored = ['type' => $type->value];

        foreach (self::KEYS[$type->value] as $key) {
            $stored[$key] = (string) $credentials[$key];
        }

        return $stored;
    }
}
