<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Mapping\FieldMapping;
use App\Http\Requests\Merchant\ResolvesMerchantFeed;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new field-mapping version: canonical field => source column / element /
 * key. Every required field must be mapped, except the currency, which falls
 * back to the feed's own currency. Owner/manager only.
 */
class SaveFeedMappingRequest extends FormRequest
{
    use ResolvesMerchantFeed;

    public const int SOURCE_KEY_MAX = 255;

    public function authorize(): bool
    {
        return $this->allowsOnFeed('update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'mapping' => ['required', 'array', self::knownFieldsRule()],
            'mapping.*' => ['nullable', 'string', 'max:'.self::SOURCE_KEY_MAX],
        ];

        foreach (self::requiredFields() as $field) {
            $rules['mapping.'.$field->value] = ['required', 'string', 'max:'.self::SOURCE_KEY_MAX];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (self::requiredFields() as $field) {
            $label = (string) __('feeds.fields.'.$field->value);
            $messages['mapping.'.$field->value.'.required'] = "Choose the column that holds the {$label} (required).";
        }

        return $messages;
    }

    public function mapping(): FieldMapping
    {
        /** @var array<string, mixed> $input */
        $input = (array) $this->validated('mapping');

        return FieldMapping::fromArray(array_filter(
            array_map(static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null, $input),
            static fn (?string $value): bool => $value !== null,
        ));
    }

    /**
     * Required by the feed contract; the currency may come from the feed's default.
     *
     * @return list<FeedField>
     */
    public static function requiredFields(): array
    {
        return array_values(array_filter(
            FeedField::cases(),
            static fn (FeedField $field): bool => $field->isRequired() && $field !== FeedField::Currency,
        ));
    }

    public static function knownFieldsRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_keys($value) as $field) {
                if (FeedField::tryFrom((string) $field) === null) {
                    $fail('The mapping contains an unknown field.');

                    return;
                }
            }
        };
    }
}
