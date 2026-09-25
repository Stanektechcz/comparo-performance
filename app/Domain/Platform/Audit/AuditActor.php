<?php

namespace App\Domain\Platform\Audit;

use App\Models\User;
use InvalidArgumentException;

/**
 * Who performed an audited action: a signed-in user or a system component
 * (scheduler, queued job, console command).
 *
 * The audit log is pseudonymous: only the user id is stored, never the email.
 */
final readonly class AuditActor
{
    public const string TYPE_USER = 'user';

    public const string TYPE_SYSTEM = 'system';

    private function __construct(
        public string $type,
        public ?int $id,
        public ?string $component,
    ) {}

    public static function user(User $user): self
    {
        return new self(self::TYPE_USER, $user->id, null);
    }

    /**
     * @param  string  $component  Short identifier of the acting component, e.g. `feeds.scheduler`.
     */
    public static function system(string $component): self
    {
        $component = trim($component);

        if ($component === '') {
            throw new InvalidArgumentException('A system audit actor needs a component name.');
        }

        return new self(self::TYPE_SYSTEM, null, mb_substr($component, 0, 96));
    }

    public function isSystem(): bool
    {
        return $this->type === self::TYPE_SYSTEM;
    }
}
