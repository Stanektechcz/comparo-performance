<?php

namespace App\Http\Requests\Merchant\Matching;

use App\Http\Requests\Admin\Catalogue\ActiveProductRule;
use App\Http\Requests\Merchant\ResolvesMerchantListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A merchant decision on one of its own listings: confirm the suggestion,
 * choose another ACTIVE product, or reject (owner/manager). The listing is
 * resolved in the active merchant's scope before anything is validated.
 */
class ListingDecisionRequest extends FormRequest
{
    use ResolvesMerchantListing;

    public const string CONFIRM = 'confirm';

    public const string CHOOSE = 'choose';

    public const string REJECT = 'reject';

    public const int NOTE_MAX = 1000;

    public function authorize(): bool
    {
        return $this->allowsOnListing('decide');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in([self::CONFIRM, self::CHOOSE, self::REJECT])],
            'product_id' => ['exclude_unless:action,'.self::CHOOSE, 'required', 'integer', 'min:1', ActiveProductRule::make()],
            'note' => ['nullable', 'string', 'max:'.self::NOTE_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Choose a catalogue product first.',
            'product_id.exists' => ActiveProductRule::MESSAGE,
        ];
    }

    public function action(): string
    {
        return $this->string('action')->toString();
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
