<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Domain\Feeds\Mapping\FieldMapping;
use App\Http\Requests\Merchant\ResolvesMerchantFeed;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * The mapping screen, optionally previewing an unsaved draft mapping
 * (`?draft[title]=name&…`). Every member may preview; nothing is written.
 */
class PreviewFeedMappingRequest extends FormRequest
{
    use ResolvesMerchantFeed;

    public function authorize(): bool
    {
        return $this->allowsOnFeed('view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'draft' => ['sometimes', 'array', SaveFeedMappingRequest::knownFieldsRule()],
            'draft.*' => ['nullable', 'string', 'max:'.SaveFeedMappingRequest::SOURCE_KEY_MAX],
        ];
    }

    /**
     * The draft to preview, or null for the saved / suggested mapping.
     */
    public function draft(): ?FieldMapping
    {
        $draft = $this->validated('draft');

        if (! is_array($draft)) {
            return null;
        }

        try {
            return FieldMapping::fromArray(array_filter($draft, static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
