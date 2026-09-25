<?php

namespace App\Http\Resources\Merchant;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A merchant's view of its own offer. Explicit whitelist: no internal risk,
 * no trust inputs, no rank internals, no commercial terms.
 *
 * @mixin Offer
 */
class MerchantOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => ['slug' => $this->product->slug, 'name' => $this->product->name],
            'variant' => $this->variant_label,
            'pack' => $this->pack_label,
            'price' => ['amount' => $this->price_minor, 'currency' => $this->currency],
            'reference_price' => $this->reference_price_minor,
            'availability' => $this->availability->value,
            'url' => $this->url,
            'link_status' => $this->link_status->value,
            'price_under_review' => $this->anomaly !== null,
            'active' => $this->is_active,
            'updated_at' => $this->source_updated_at->toIso8601String(),
        ];
    }
}
