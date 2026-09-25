<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates that merchant_id is one of the authenticated user's own
 * memberships. A foreign merchant id fails validation the same way a
 * nonexistent one would — it never reveals whether the merchant exists.
 */
class SwitchMerchantContextRequest extends FormRequest
{
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
            'merchant_id' => [
                'required',
                'integer',
                Rule::in($this->user()?->merchants()->pluck('merchants.id')->all() ?? []),
            ],
        ];
    }
}
