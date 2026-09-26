<?php

namespace App\Models;

use App\Domain\Verification\ProofMethod;
use App\Domain\Verification\ProofStatus;
use Database\Factories\PurchaseProofFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evidence that a user bought from a merchant (A-35, A-38). evidence holds
 * metadata only; the order reference is an HMAC, the receipt a sha256, and
 * the receipt file on the private disk is purged right after the decision
 * (≤ 30 days). order_id is the order created from this evidence.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $review_id
 * @property int|null $merchant_id
 * @property int|null $order_id
 * @property ProofMethod $method
 * @property ProofStatus $status
 * @property array<string, mixed>|null $evidence
 * @property string|null $order_reference_hash
 * @property string|null $receipt_sha256
 * @property string|null $receipt_path
 * @property Carbon|null $receipt_purged_at
 * @property int|null $decided_by_user_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_code
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'review_id', 'merchant_id', 'order_id', 'method', 'status', 'evidence', 'order_reference_hash',
    'receipt_sha256', 'receipt_path', 'receipt_purged_at', 'decided_by_user_id', 'decided_at', 'decision_code',
    'expires_at',
])]
#[Hidden(['order_reference_hash', 'receipt_sha256', 'receipt_path'])]
class PurchaseProof extends Model
{
    /** @use HasFactory<PurchaseProofFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => ProofMethod::class,
            'status' => ProofStatus::class,
            'evidence' => 'array',
            'receipt_purged_at' => 'datetime',
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function hasStoredReceipt(): bool
    {
        return $this->receipt_path !== null && $this->receipt_purged_at === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
