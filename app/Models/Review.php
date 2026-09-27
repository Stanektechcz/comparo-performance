<?php

namespace App\Models;

use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\ReviewSubjectType;
use App\Domain\Reviews\VerificationMethod;
use App\Domain\Reviews\VerificationStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A product or shop review (docs/architecture/phase-4-reviews-orders.md §2).
 * Exactly one subject (product_id XOR merchant_id, CHECK); user_id is nulled
 * on erasure ("Former member"). Only `approved` is public. Credibility is
 * frozen at decision time by the pure Credibility engine.
 *
 * @property int $id
 * @property ReviewSubjectType $subject_type
 * @property int|null $product_id
 * @property int|null $merchant_id
 * @property int|null $purchased_from_merchant_id
 * @property int|null $user_id
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property list<string>|null $pros
 * @property list<string>|null $cons
 * @property bool|null $recommends
 * @property ReviewStatus $status
 * @property string|null $status_reason_code
 * @property VerificationStatus $verification_status
 * @property VerificationMethod|null $verification_method
 * @property int|null $verified_order_id
 * @property int|null $credibility_score
 * @property string|null $credibility_level
 * @property string|null $credibility_weight
 * @property string|null $credibility_version
 * @property int $helpful_count
 * @property int $not_helpful_count
 * @property Carbon $submitted_at
 * @property Carbon|null $published_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'subject_type', 'product_id', 'merchant_id', 'purchased_from_merchant_id', 'user_id', 'rating', 'title', 'body',
    'pros', 'cons', 'recommends', 'status', 'status_reason_code', 'verification_status', 'verification_method',
    'verified_order_id', 'credibility_score', 'credibility_level', 'credibility_weight', 'credibility_version',
    'helpful_count', 'not_helpful_count', 'submitted_at', 'published_at', 'withdrawn_at',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subject_type' => ReviewSubjectType::class,
            'rating' => 'integer',
            'pros' => 'array',
            'cons' => 'array',
            'recommends' => 'boolean',
            'status' => ReviewStatus::class,
            'verification_status' => VerificationStatus::class,
            'verification_method' => VerificationMethod::class,
            'credibility_score' => 'integer',
            'credibility_weight' => 'decimal:3',
            'helpful_count' => 'integer',
            'not_helpful_count' => 'integer',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function isPublic(): bool
    {
        return $this->status->isPublic();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The reviewed shop (merchant subject).
     *
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function purchasedFrom(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'purchased_from_merchant_id');
    }

    /**
     * The author; null once the account was erased.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function verifiedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'verified_order_id');
    }

    /**
     * @return HasMany<ReviewSubRating, $this>
     */
    public function subRatings(): HasMany
    {
        return $this->hasMany(ReviewSubRating::class);
    }

    /**
     * @return HasOne<ReviewSignal, $this>
     */
    public function signal(): HasOne
    {
        return $this->hasOne(ReviewSignal::class);
    }

    /**
     * @return HasMany<ReviewModerationEvent, $this>
     */
    public function moderationEvents(): HasMany
    {
        return $this->hasMany(ReviewModerationEvent::class)->orderBy('id');
    }

    /**
     * @return HasMany<ReviewVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }

    /**
     * @return HasOne<ReviewReply, $this>
     */
    public function reply(): HasOne
    {
        return $this->hasOne(ReviewReply::class);
    }

    /**
     * @return HasMany<ContentReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ContentReport::class);
    }

    /**
     * @return HasMany<PurchaseProof, $this>
     */
    public function purchaseProofs(): HasMany
    {
        return $this->hasMany(PurchaseProof::class);
    }
}
