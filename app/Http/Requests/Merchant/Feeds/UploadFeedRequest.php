<?php

namespace App\Http\Requests\Merchant\Feeds;

use App\Domain\Feeds\FeedTransport;
use App\Domain\Feeds\Pipeline\FeedStorage;
use App\Http\Requests\Merchant\ResolvesMerchantFeed;
use Closure;
use finfo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * A feed file for an upload feed (owner/manager): extension + size
 * (`comparo.feeds.max_payload_bytes`) + a finfo MIME sniff of the content.
 * The client file name is never used for storage.
 */
class UploadFeedRequest extends FormRequest
{
    use ResolvesMerchantFeed;

    /** @var list<string> */
    public const array EXTENSIONS = ['csv', 'tsv', 'txt', 'xml', 'json', 'gz'];

    /** @var list<string> MIME types finfo may report for an accepted feed file */
    public const array MIME_TYPES = [
        'text/plain', 'text/csv', 'application/csv', 'text/tab-separated-values', 'text/x-csv',
        'text/xml', 'application/xml', 'application/json', 'application/gzip', 'application/x-gzip',
    ];

    private const string TYPES_MESSAGE = 'Upload a CSV, TSV, TXT, XML or JSON file (optionally gzip-compressed).';

    public function authorize(): bool
    {
        return $this->allowsOnFeed('run');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = max(1, intdiv(app(FeedStorage::class)->maxPayloadBytes(), 1024));

        return [
            'file' => [
                'required',
                File::types(self::EXTENSIONS)->max($maxKilobytes),
                self::sniffedMimeRule(),
                $this->uploadTransportRule(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a feed file to upload.',
            'file.mimes' => self::TYPES_MESSAGE,
            'file.extensions' => self::TYPES_MESSAGE,
        ];
    }

    public function upload(): UploadedFile
    {
        $file = $this->file('file');

        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }

    private static function sniffedMimeRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile || ! $value->isValid()) {
                return;
            }

            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($value->getRealPath());

            if (! is_string($mime) || ! in_array(strtolower($mime), self::MIME_TYPES, true)) {
                $fail('The file content is not a text, XML, JSON or gzip feed.');
            }
        };
    }

    private function uploadTransportRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->feed()->transport !== FeedTransport::Upload) {
                $fail('This feed is fetched from its URL. Files can only be uploaded to upload feeds.');
            }
        };
    }
}
