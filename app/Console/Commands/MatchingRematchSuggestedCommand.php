<?php

namespace App\Console\Commands;

use App\Domain\Accounts\Authorization\Permission;
use App\Domain\Compliance\Queries\ListingMarkets;
use App\Domain\Matching\Actions\RematchSuggestedListings;
use App\Domain\Matching\Contracts\ComplianceHoldCheck;
use App\Domain\Platform\Audit\AuditActor;
use App\Models\Merchant;
use App\Models\MerchantProduct;
use App\Models\User;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use LogicException;

/**
 * BACKLOG F-06: re-run matching (forced) for listings demoted to `suggested`
 * while `matching-auto-publish` was off. Each listing is checked against the
 * compliance rules of its own feed market; changes are audited as
 * `matching.rematched` (attributed to --staff, else to this command).
 */
class MatchingRematchSuggestedCommand extends Command
{
    public const string AUDIT_COMPONENT = 'console.matching.rematch_suggested';

    /** Permissions a staff member needs to be named as the operator (same as a single rematch). */
    private const array STAFF_PERMISSIONS = [Permission::AccessStaffConsole, Permission::ReviewMatching, Permission::ManageOffers];

    protected $signature = 'comparo:matching:rematch-suggested
        {--merchant= : only listings of this merchant id}
        {--dry-run : count the demoted listings without re-matching them}
        {--staff= : id of the staff user the operation is attributed to in the audit log}';

    protected $description = 'Re-run matching for listings demoted to suggested while matching-auto-publish was off';

    public function handle(RematchSuggestedListings $rematch, ListingMarkets $markets): int
    {
        $merchantId = $this->merchantId();
        $actor = $this->auditActor();

        if ($merchantId === false || $actor === null) {
            return self::INVALID;
        }

        try {
            $summary = $rematch->handle(
                $actor,
                Date::now()->toImmutable(),
                $this->holdResolver($markets),
                $merchantId,
                (bool) $this->option('dry-run'),
            );
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($summary->dryRun) {
            $this->info("Dry run: {$summary->eligible} demoted suggested listings would be re-matched.");

            return self::SUCCESS;
        }

        $this->info("Re-matched {$summary->eligible} demoted listings: {$summary->linked} linked, {$summary->held} held, {$summary->suggested} still suggested, {$summary->unmatched} unmatched.");

        return self::SUCCESS;
    }

    /**
     * @return int|false|null null for all merchants, false when invalid
     */
    private function merchantId(): int|false|null
    {
        $option = $this->option('merchant');

        if ($option === null || $option === '') {
            return null;
        }

        $id = filter_var($option, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false || ! Merchant::query()->whereKey($id)->exists()) {
            $this->error('The --merchant option must be the id of an existing merchant.');

            return false;
        }

        return $id;
    }

    private function auditActor(): ?AuditActor
    {
        $option = $this->option('staff');

        if ($option === null || $option === '') {
            return AuditActor::system(self::AUDIT_COMPONENT);
        }

        $id = filter_var($option, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $user = $id === false ? null : User::query()->find($id);

        $required = array_map(static fn (Permission $permission): string => $permission->value, self::STAFF_PERMISSIONS);

        if ($user === null || array_filter($required, static fn (string $permission): bool => ! $user->can($permission)) !== []) {
            $this->error('The --staff option must be the id of a staff user with '.implode(', ', $required).'.');

            return null;
        }

        return AuditActor::user($user);
    }

    /**
     * One compliance check per feed source (its market), built on first use.
     *
     * @return Closure(MerchantProduct): ComplianceHoldCheck
     */
    private function holdResolver(ListingMarkets $markets): Closure
    {
        /** @var array<int, ComplianceHoldCheck> $bySource */
        $bySource = [];

        return function (MerchantProduct $listing) use ($markets, &$bySource): ComplianceHoldCheck {
            $key = (int) $listing->feed_source_id;

            return $bySource[$key] ??= $markets->holdForListing($listing);
        };
    }
}
