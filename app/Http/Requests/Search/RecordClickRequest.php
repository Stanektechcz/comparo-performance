<?php

namespace App\Http\Requests\Search;

use App\Domain\Search\Local\SearchableType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of POST /search/clicks. Never fails the request: an invalid click is
 * simply not recorded, and the endpoint answers 204 either way so it reveals
 * nothing about searches, sessions or validation.
 */
class RecordClickRequest extends FormRequest
{
    public const int MAX_POSITION = 100;

    private bool $invalid = false;

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
            'search_id' => ['required', 'string', 'regex:/^[0-9a-hjkmnp-tv-z]{26}$/i'],
            'entity_type' => ['required', 'string', Rule::enum(SearchableType::class)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'position' => ['required', 'integer', 'min:1', 'max:'.self::MAX_POSITION],
        ];
    }

    public function isValidClick(): bool
    {
        return ! $this->invalid;
    }

    /**
     * @return array{search_id: string, entity_type: string, entity_id: int, position: int}
     */
    public function click(): array
    {
        return [
            'search_id' => strtolower($this->string('search_id')->toString()),
            'entity_type' => $this->string('entity_type')->toString(),
            'entity_id' => $this->integer('entity_id'),
            'position' => $this->integer('position'),
        ];
    }

    /**
     * Swallowed on purpose (see the class comment).
     */
    protected function failedValidation(Validator $validator): void
    {
        $this->invalid = true;
    }
}
