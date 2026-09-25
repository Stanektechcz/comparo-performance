<?php

namespace App\Domain\Platform\Audit;

use App\Http\Middleware\AssignCorrelationId;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;

/**
 * Writes one append-only audit_logs row per privileged action.
 *
 * Call it INSIDE the caller's database transaction, right next to the change
 * it describes: if the action rolls back, the audit row rolls back with it,
 * and an action can never commit without its audit row.
 *
 * - before/after pass through {@see AuditRedactor}; secrets never reach the table.
 *   Use {@see AuditChanges::fromModel()} to build them for model updates.
 * - The subject is stored by its morph class (the model's FQCN unless a morph
 *   map entry exists — no morph map is enforced) and integer key.
 * - correlation_id comes from the request Context ({@see AssignCorrelationId});
 *   ip_address/user_agent come from hidden Context values. Outside a request
 *   (console, scheduler) they are null; queued jobs inherit the values of the
 *   request that dispatched them, because Laravel propagates Context into jobs.
 * - A system actor's component name is stored in `after` under
 *   {@see self::SYSTEM_COMPONENT_KEY} (audit_logs has no dedicated column).
 * - The audit log is pseudonymous: pass ids, never emails or names.
 */
final class AuditLogger
{
    public const string SYSTEM_COMPONENT_KEY = '_actor_component';

    private const int CORRELATION_ID_MAX = 64;

    private const int IP_ADDRESS_MAX = 45;

    private const int USER_AGENT_MAX = 512;

    public function __construct(private readonly AuditRedactor $redactor) {}

    /**
     * @param  array<array-key, mixed>  $before  State before the action (only the relevant keys).
     * @param  array<array-key, mixed>  $after  State after the action (only the relevant keys).
     */
    public function record(
        AuditAction $action,
        AuditActor $actor,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
    ): AuditLog {
        if ($actor->isSystem()) {
            $after[self::SYSTEM_COMPONENT_KEY] = $actor->component;
        }

        return AuditLog::query()->create([
            'actor_id' => $actor->id,
            'actor_type' => $actor->type,
            'action' => $action->value,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject === null ? null : $this->subjectKey($subject),
            'before' => $before === [] ? null : $this->redactor->redact($before),
            'after' => $after === [] ? null : $this->redactor->redact($after),
            'correlation_id' => $this->contextString(Context::get('correlation_id'), self::CORRELATION_ID_MAX),
            'ip_address' => $this->contextString(Context::getHidden('ip_address'), self::IP_ADDRESS_MAX),
            'user_agent' => $this->contextString(Context::getHidden('user_agent'), self::USER_AGENT_MAX),
        ]);
    }

    private function subjectKey(Model $subject): int
    {
        $key = $subject->getKey();

        if (is_int($key) || (is_string($key) && ctype_digit($key))) {
            return (int) $key;
        }

        throw new InvalidArgumentException(sprintf(
            'Audit subjects must be persisted with an integer key; %s has a %s key.',
            $subject::class,
            get_debug_type($key),
        ));
    }

    private function contextString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr(str_replace("\0", '', mb_scrub($value, 'UTF-8')), 0, $maxLength);
    }
}
