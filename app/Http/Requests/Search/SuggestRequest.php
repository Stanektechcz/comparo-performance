<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `?q=` of the public suggest API: 2–64 characters. The market comes from
 * ResolveMarket (`?market=`, then the market cookie, then the default).
 */
class SuggestRequest extends FormRequest
{
    public const int MIN_LENGTH = 2;

    public const int MAX_LENGTH = 64;

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
            'q' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:'.self::MAX_LENGTH],
        ];
    }

    public function prefix(): string
    {
        return $this->string('q')->toString();
    }
}
