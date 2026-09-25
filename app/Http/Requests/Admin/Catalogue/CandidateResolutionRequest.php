<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff resolve a new-product proposal: it is an existing ACTIVE product, or
 * it is rejected. Creating a product from it is Phase 8 (A-15).
 * Access: `staff.access` + `matching.review` (route).
 */
class CandidateResolutionRequest extends FormRequest
{
    public const string LINK_EXISTING = 'link_existing';

    public const string REJECT = 'reject';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in([self::LINK_EXISTING, self::REJECT])],
            'product_id' => ['exclude_unless:action,'.self::LINK_EXISTING, 'required', 'integer', 'min:1', ActiveProductRule::make()],
            'note' => ['nullable', 'string', 'max:'.ListingDecisionRequest::NOTE_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['product_id.exists' => ActiveProductRule::MESSAGE];
    }

    public function linksExisting(): bool
    {
        return $this->string('action')->toString() === self::LINK_EXISTING;
    }

    public function productId(): int
    {
        return $this->integer('product_id');
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->trim()->toString() : null;
    }
}
