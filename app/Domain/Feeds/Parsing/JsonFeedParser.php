<?php

namespace App\Domain\Feeds\Parsing;

use App\Domain\Feeds\FeedErrorCode;
use Generator;
use JsonException;
use RuntimeException;

/**
 * JSON feed reader. JSON cannot be streamed without a dependency, so the file
 * is size-capped first (`maxJsonBytes`) and decoded with a depth limit of 16.
 *
 * Accepted shapes: `[...]`, `{"items": [...]}` and `{"products": [...]}`.
 * Row numbers are the 1-based position of the item. Nested objects are
 * flattened one level as `parent.child`; deeper values and lists are ignored.
 * Numbers become strings via their shortest round-trip representation
 * (`43.50` becomes "43.5"), so decimal prices keep their exact digits.
 */
final class JsonFeedParser implements FeedParser
{
    private const int MAX_DEPTH = 16;

    /**
     * @return Generator<int, RawFeedRow>
     */
    public function rows(string $path, ParseOptions $options): Generator
    {
        $items = $this->items($path, $options);

        if ($items === []) {
            throw new FeedParseException(FeedErrorCode::EmptyFeed);
        }

        if (count($items) > $options->maxRows) {
            throw new FeedParseException(FeedErrorCode::RowLimitExceeded, ['limit' => $options->maxRows]);
        }

        foreach (array_values($items) as $index => $item) {
            yield new RawFeedRow($index + 1, is_array($item) ? $this->flatten($item) : []);
        }
    }

    /**
     * @return array<mixed>
     */
    private function items(string $path, ParseOptions $options): array
    {
        $size = @filesize($path);

        if ($size === false) {
            throw new RuntimeException('The feed payload file could not be opened.');
        }

        if ($size > $options->maxJsonBytes) {
            throw new FeedParseException(FeedErrorCode::PayloadTooLarge, [
                'limit_mb' => intdiv($options->maxJsonBytes, 1024 * 1024),
            ]);
        }

        $contents = (string) @file_get_contents($path);

        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        try {
            $decoded = json_decode($options->toUtf8($contents), true, self::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw new FeedParseException(FeedErrorCode::ParserError, ['format' => 'JSON']);
        }

        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        foreach (['items', 'products'] as $wrapper) {
            if (is_array($decoded) && is_array($decoded[$wrapper] ?? null) && array_is_list($decoded[$wrapper])) {
                return $decoded[$wrapper];
            }
        }

        throw new FeedParseException(FeedErrorCode::ParserError, ['format' => 'JSON']);
    }

    /**
     * @param  array<mixed>  $item
     * @return array<string, string>
     */
    private function flatten(array $item): array
    {
        $fields = [];

        foreach ($item as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    continue;
                }

                foreach ($value as $childKey => $childValue) {
                    $scalar = $this->scalar($childValue);

                    if ($scalar !== null) {
                        $fields[$key.'.'.$childKey] ??= $scalar;
                    }
                }

                continue;
            }

            $scalar = $this->scalar($value);

            if ($scalar !== null) {
                $fields[(string) $key] ??= $scalar;
            }
        }

        return $fields;
    }

    private function scalar(mixed $value): ?string
    {
        return match (true) {
            is_string($value) => trim($value),
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => (string) json_encode($value), // serialize_precision -1: shortest round-trip
            default => null,
        };
    }
}
