<?php

namespace App\Domain\Feeds\Actions;

use App\Domain\Feeds\Lifecycle\FeedActorKind;
use App\Domain\Platform\Audit\AuditActor;
use App\Models\User;

/**
 * Who performs a feed action, passed explicitly by the caller (the domain
 * never reads the authenticated user): its lifecycle role and its audit identity.
 */
final readonly class FeedActor
{
    private function __construct(
        public FeedActorKind $kind,
        public AuditActor $audit,
    ) {}

    /**
     * A member of the merchant acting on the merchant's own feeds.
     */
    public static function merchant(User $user): self
    {
        return new self(FeedActorKind::Merchant, AuditActor::user($user));
    }

    public static function staff(User $user): self
    {
        return new self(FeedActorKind::Staff, AuditActor::user($user));
    }

    /**
     * @param  string  $component  e.g. `feeds.pipeline`, `feeds.scheduler`
     */
    public static function system(string $component): self
    {
        return new self(FeedActorKind::System, AuditActor::system($component));
    }

    public function userId(): ?int
    {
        return $this->audit->isSystem() ? null : $this->audit->id;
    }
}
