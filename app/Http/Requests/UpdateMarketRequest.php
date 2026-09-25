<?php

namespace App\Http\Requests;

use App\Domain\Platform\Markets\MarketResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketRequest extends FormRequest
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
            'market' => ['required', 'string', 'size:2', Rule::in(array_keys(app(MarketResolver::class)->all()))],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('market'))) {
            $this->merge(['market' => strtoupper($this->input('market'))]);
        }
    }
}
