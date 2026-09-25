<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Domain\Feeds\Fetching\FeedAuthType;
use App\Http\Requests\Merchant\ResolvesMerchantFeed;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * New feed access credentials, or `none` to remove them. Owner only; the
 * route also requires a fresh password confirmation. Secrets are never
 * flashed back to the form or echoed in messages.
 */
class UpdateFeedCredentialsRequest extends FormRequest
{
    use ResolvesMerchantFeed;

    public const string NONE = 'none';

    public const int SECRET_MAX = 1024;

    public const string HEADER_NAME_PATTERN = '/^[A-Za-z0-9!#$%&\'*+.^_`|~\-]{1,64}$/';

    /** Header names a merchant may not set (mirrors FeedCredentials). */
    public const array FORBIDDEN_HEADERS = [
        'host', 'content-length', 'transfer-encoding', 'connection', 'upgrade', 'te', 'trailer',
        'proxy-authorization', 'expect', 'keep-alive',
    ];

    public function authorize(): bool
    {
        return $this->allowsOnFeed('manageCredentials');
    }

    /**
     * Redirect back with the errors only: no submitted input (secrets) is
     * ever flashed into the session.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator, redirect($this->getRedirectUrl())->withErrors($validator->errors()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = [self::NONE, ...array_map(static fn (FeedAuthType $type): string => $type->value, FeedAuthType::cases())];

        return [
            'type' => ['required', 'string', Rule::in($types)],
            'username' => ['exclude_unless:type,basic', 'required', 'string', 'max:255', 'not_regex:/[\r\n\x00:]/'],
            'password' => ['exclude_unless:type,basic', 'required', 'string', 'max:'.self::SECRET_MAX, 'not_regex:/[\r\n\x00]/'],
            'token' => ['exclude_unless:type,bearer', 'required', 'string', 'max:'.self::SECRET_MAX, 'not_regex:/[\r\n\x00]/'],
            'header_name' => ['exclude_unless:type,header', 'required', 'string', 'max:64', 'regex:'.self::HEADER_NAME_PATTERN, $this->allowedHeaderRule()],
            'header_value' => ['exclude_unless:type,header', 'required', 'string', 'max:'.self::SECRET_MAX, 'not_regex:/[\r\n\x00]/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.not_regex' => 'The user name may not contain a colon or line breaks.',
            'password.not_regex' => 'Credentials may not contain line breaks.',
            'token.not_regex' => 'Credentials may not contain line breaks.',
            'header_value.not_regex' => 'Credentials may not contain line breaks.',
            'header_name.regex' => 'Use a plain HTTP header name such as X-Api-Key.',
        ];
    }

    private function allowedHeaderRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && in_array(strtolower($value), self::FORBIDDEN_HEADERS, true)) {
                $fail('This header controls the connection and cannot be used for credentials.');
            }
        };
    }

    /**
     * The credentials array for UpdateFeedCredentials, or null to clear them.
     *
     * @return array<string, string>|null
     */
    public function credentials(): ?array
    {
        return match ($this->string('type')->toString()) {
            FeedAuthType::Basic->value => [
                'type' => FeedAuthType::Basic->value,
                'username' => $this->string('username')->toString(),
                'password' => $this->string('password')->toString(),
            ],
            FeedAuthType::Bearer->value => ['type' => FeedAuthType::Bearer->value, 'token' => $this->string('token')->toString()],
            FeedAuthType::Header->value => [
                'type' => FeedAuthType::Header->value,
                'name' => $this->string('header_name')->toString(),
                'value' => $this->string('header_value')->toString(),
            ],
            default => null,
        };
    }
}
