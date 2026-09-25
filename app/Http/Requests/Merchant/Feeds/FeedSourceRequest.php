<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Domain\Feeds\Actions\FeedSourceData;
use App\Domain\Feeds\FeedFormat;
use App\Domain\Feeds\FeedTransport;
use App\Domain\Feeds\Fetching\DestinationGuard;
use App\Domain\Feeds\Fetching\FeedFetchException;
use App\Models\FeedSource;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settings of a merchant feed source (create and update). Subclasses
 * authorise through FeedSourcePolicy (update: after resolving the source in
 * the active merchant's scope). The feed URL may carry tokens:
 * validation messages never echo it and the edit form never pre-fills it.
 */
abstract class FeedSourceRequest extends FormRequest
{
    /** Transports a merchant may choose (API push and staff uploads are not portal options). */
    public const array TRANSPORTS = [FeedTransport::Url->value, FeedTransport::Upload->value];

    /** @var array<string, string> form value => label */
    public const array ENCODINGS = [
        'UTF-8' => 'UTF-8 (recommended)',
        'Windows-1250' => 'Windows-1250 (Central European)',
        'Windows-1252' => 'Windows-1252 (Western European)',
        'ISO-8859-1' => 'ISO-8859-1 (Latin-1)',
        'ISO-8859-2' => 'ISO-8859-2 (Latin-2)',
    ];

    /** @var array<string, string> form value => CSV delimiter; empty = detect */
    public const array DELIMITERS = ['comma' => ',', 'semicolon' => ';', 'tab' => "\t", 'pipe' => '|'];

    /** @var list<int> schedule options in minutes; empty = manual runs only */
    public const array INTERVALS = [60, 180, 360, 720, 1440];

    public const int NAME_MAX = 96;

    public const int URL_MAX = 2048;

    abstract public function authorize(): bool;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'format' => ['required', 'string', Rule::enum(FeedFormat::class)],
            'transport' => ['required', 'string', Rule::in(self::TRANSPORTS)],
            'url' => ['exclude_unless:transport,url', ...$this->urlPresenceRules(), 'string', 'max:'.self::URL_MAX, $this->publicUrlRule()],
            'currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'country' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')],
            'encoding' => ['required', 'string', Rule::in(array_keys(self::ENCODINGS))],
            'delimiter' => ['exclude_unless:format,csv', 'nullable', 'string', Rule::in(array_keys(self::DELIMITERS))],
            'record_element' => ['exclude_if:format,csv', 'nullable', 'string', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_.\-]{0,63}$/'],
            'interval_minutes' => ['exclude_unless:transport,url', 'nullable', 'integer', Rule::in(self::INTERVALS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.required' => 'Enter the address of your feed.',
            'record_element.regex' => 'Use a plain element or key name such as item or SHOPITEM.',
            'currency.exists' => 'Choose a supported currency.',
            'country.exists' => 'Choose a supported market.',
        ];
    }

    /**
     * The domain input for the settings. `$existing` supplies the stored URL
     * when an update leaves the URL field empty, and the availability mapping
     * (not editable in the portal yet).
     */
    public function toData(?FeedSource $existing = null): FeedSourceData
    {
        $transport = FeedTransport::from($this->string('transport')->toString());
        $format = FeedFormat::from($this->string('format')->toString());
        $url = null;

        if ($transport->isFetched()) {
            $url = $this->filled('url') ? $this->string('url')->trim()->toString() : $existing?->url;
        }

        return new FeedSourceData(
            name: $this->string('name')->trim()->toString(),
            format: $format,
            transport: $transport,
            url: $url,
            currency: $this->string('currency')->toString(),
            marketCountryCode: $this->filled('country') ? $this->string('country')->toString() : null,
            encoding: $this->string('encoding')->toString(),
            delimiter: $format === FeedFormat::Csv && $this->filled('delimiter') ? self::DELIMITERS[$this->string('delimiter')->toString()] : null,
            recordElement: $format !== FeedFormat::Csv && $this->filled('record_element') ? $this->string('record_element')->trim()->toString() : null,
            intervalMinutes: $transport->isFetched() && $this->filled('interval_minutes') ? $this->integer('interval_minutes') : null,
            availabilityMap: $existing->availability_map ?? [],
        );
    }

    /**
     * @return list<string>
     */
    abstract protected function urlPresenceRules(): array;

    private function publicUrlRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            try {
                (new DestinationGuard)->parse(is_string($value) ? trim($value) : '');
            } catch (FeedFetchException) {
                $fail('Use a public http:// or https:// address on the standard port (80 or 443), without a user name or password in the address.');
            }
        };
    }
}
