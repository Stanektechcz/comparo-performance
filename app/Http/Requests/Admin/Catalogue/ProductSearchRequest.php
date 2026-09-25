<?php

namespace App\Http\Requests\Admin\Catalogue;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `?q=` of the staff product picker. Access: `staff.access` +
 * `matching.review` (route).
 */
class ProductSearchRequest extends FormRequest
{
    public const int QUERY_MIN = 2;

    public const int QUERY_MAX = 100;

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
            'q' => ['required', 'string', 'min:'.self::QUERY_MIN, 'max:'.self::QUERY_MAX],
        ];
    }

    public function term(): string
    {
        return $this->string('q')->squish()->toString();
    }
}
