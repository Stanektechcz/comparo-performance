<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff correction of a linked listing to another ACTIVE product. A note is
 * required: a rematch moves a published offer. Access: `staff.access` +
 * `matching.review` + `offers.manage` (route).
 */
class ListingRematchRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'min:1', ActiveProductRule::make()],
            'note' => ['required', 'string', 'max:'.ListingDecisionRequest::NOTE_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => ActiveProductRule::MESSAGE,
            'note.required' => 'Explain why the listing is relinked.',
        ];
    }

    public function productId(): int
    {
        return $this->integer('product_id');
    }

    public function note(): string
    {
        return $this->string('note')->trim()->toString();
    }
}
