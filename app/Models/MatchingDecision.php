<?php

namespace App\Models;

use App\Domain\Matching\MatchDecisionKind;
use App\Models\Concerns\AppendOnly;
use Database\Factories\MatchingDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One matching decision about a merchant listing. Append-only: a correction is
 * a new row whose `supersedes_id` points at the decision it replaces (a linear
 * chain — supersedes_id is unique). decided_by_user_id deliberately has no
 * foreign key (ON DELETE SET NULL would be an UPDATE the trigger rejects).
 *
 * @property int $id
 * @property int $merchant_product_id
 * @property int $merchant_id
 * @property int|null $feed_run_id
 * @property MatchDecisionKind $kind
 * @property int|null $product_id
 * @property int|null $previous_product_id
 * @property int|null $matching_policy_id
 * @property int|null $score
 * @property array<int|string, mixed>|null $components
 * @property int|null $decided_by_user_id
 * @property string|null $reason
 * @property string|null $note
 * @property int|null $supersedes_id
 * @property Carbon $decided_at
 * @property Carbon $created_at
 * @property-read MerchantProduct $merchantProduct
 */
#[Fillable([
    'merchant_product_id', 'merchant_id', 'feed_run_id', 'kind', 'product_id', 'previous_product_id',
    'matching_policy_id', 'score', 'components', 'decided_by_user_id', 'reason', 'note', 'supersedes_id', 'decided_at',
])]
class MatchingDecision extends Model
{
    use AppendOnly;

    /** @use HasFactory<MatchingDecisionFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MatchDecisionKind::class,
            'score' => 'integer',
            'components' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MerchantProduct, $this>
     */
    public function merchantProduct(): BelongsTo
    {
        return $this->belongsTo(MerchantProduct::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<FeedRun, $this>
     */
    public function feedRun(): BelongsTo
    {
        return $this->belongsTo(FeedRun::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function previousProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'previous_product_id');
    }

    /**
     * @return BelongsTo<MatchingPolicy, $this>
     */
    public function matchingPolicy(): BelongsTo
    {
        return $this->belongsTo(MatchingPolicy::class);
    }

    /**
     * The user who decided (no FK: the user row may since have been deleted).
     *
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * @return BelongsTo<MatchingDecision, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * @return HasOne<MatchingDecision, $this>
     */
    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }
}
