<?php

namespace App\Models;

use App\Domain\Compliance\ComplianceStatus;
use Database\Factories\ProductComplianceRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $country_id
 * @property ComplianceStatus $status
 * @property string|null $reason
 * @property string|null $source
 * @property int|null $reviewed_by_user_id
 * @property string|null $reviewer_label
 * @property Carbon|null $reviewed_at
 * @property-read Country $country
 */
#[Fillable(['product_id', 'country_id', 'status', 'reason', 'source', 'reviewed_by_user_id', 'reviewer_label', 'reviewed_at'])]
class ProductComplianceRule extends Model
{
    /** @use HasFactory<ProductComplianceRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplianceStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
