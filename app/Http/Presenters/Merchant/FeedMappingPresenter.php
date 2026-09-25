<?php

namespace App\Http\Presenters\Merchant;

use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Queries\FeedPreview;
use App\Domain\Feeds\Queries\FeedPreviewRow;
use App\Models\FeedSource;

/**
 * The mapping screen: every canonical field with its label and required
 * marker, the source columns found in the latest payload, the current (or
 * suggested, or draft) mapping and the normalised sample rows with their
 * translated issues.
 */
final class FeedMappingPresenter
{
    private const int VALUE_MAX = 200;

    /**
     * @return array<string, mixed>
     */
    public function present(FeedSource $source, FeedPreview $preview, bool $isDraft): array
    {
        return [
            'fields' => self::fields($source),
            'preview' => [
                'available' => $preview->available,
                'headers' => $preview->headers,
                'mapping' => $preview->mapping,
                'mappingSuggested' => $preview->mappingSuggested,
                'isDraft' => $isDraft,
                'missingRequired' => array_map(
                    static fn (string $field): array => ['key' => $field, 'label' => (string) FeedMessages::fieldLabel($field)],
                    $preview->missingRequired,
                ),
                'rows' => array_map(self::row(...), $preview->rows),
                'error' => $preview->errorCode === null ? null : [
                    'code' => $preview->errorCode,
                    'message' => FeedMessages::message($preview->errorCode, $preview->errorParams),
                    'line' => $preview->errorLine,
                ],
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, required: bool, hint: ?string}>
     */
    public static function fields(FeedSource $source): array
    {
        return array_map(static fn (FeedField $field): array => [
            'key' => $field->value,
            'label' => (string) FeedMessages::fieldLabel($field->value),
            'required' => $field->isRequired() && $field !== FeedField::Currency,
            'hint' => $field === FeedField::Currency
                ? "Optional: rows without a currency use the feed currency ({$source->currency})."
                : null,
        ], FeedField::cases());
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(FeedPreviewRow $row): array
    {
        $values = [];

        foreach ($row->values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $text = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
            $values[] = [
                'key' => (string) $key,
                'label' => (string) FeedMessages::fieldLabel((string) $key),
                'value' => mb_strlen($text) > self::VALUE_MAX ? mb_substr($text, 0, self::VALUE_MAX).'…' : $text,
            ];
        }

        return [
            'lineNumber' => $row->lineNumber,
            'status' => $row->status->value,
            'sku' => $row->merchantSku,
            'values' => $values,
            'issues' => array_map(static fn (array $issue): array => [
                'code' => $issue['code'],
                'severity' => $issue['severity'],
                'field' => FeedMessages::fieldLabel($issue['field']),
                'message' => FeedMessages::message($issue['code'], $issue['params']),
            ], $row->issues),
        ];
    }
}
