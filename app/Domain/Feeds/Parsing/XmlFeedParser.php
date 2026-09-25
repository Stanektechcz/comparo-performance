<?php

namespace App\Domain\Feeds\Parsing;

use App\Domain\Feeds\FeedErrorCode;
use DOMElement;
use DOMNode;
use Generator;
use RuntimeException;
use XMLReader;

/**
 * Streaming XML reader (Heureka SHOPITEM, RSS/Google `item`, `product`, Atom `entry`).
 *
 * Security (§8): the document is read with XMLReader and LIBXML_NONET only —
 * never LIBXML_NOENT, LIBXML_DTDLOAD or LIBXML_PARSEHUGE — and any DOCTYPE
 * (which is where ENTITY declarations live) is refused before a single
 * entity can be referenced, so XXE and entity-expansion bombs never run.
 *
 * Each record's child elements become fields keyed by local name (so
 * `g:price` becomes `price`); an element with element children is flattened
 * one level as `PARENT.CHILD`; record attributes become `@name`. The first
 * occurrence of a repeated key wins.
 */
final class XmlFeedParser implements FeedParser
{
    private const int READER_FLAGS = LIBXML_NONET | LIBXML_COMPACT;

    /**
     * @return Generator<int, RawFeedRow>
     */
    public function rows(string $path, ParseOptions $options): Generator
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new XMLReader;

        try {
            // A declared encoding in the XML prolog wins unless the source says otherwise.
            if (! @$reader->open($path, $options->isUtf8() ? null : $options->encoding, self::READER_FLAGS)) {
                throw new RuntimeException('The feed payload file could not be opened.');
            }

            yield from $this->records($reader, $options);
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return Generator<int, RawFeedRow>
     */
    private function records(XMLReader $reader, ParseOptions $options): Generator
    {
        $recordName = $options->recordElement;
        $recordDepth = null;
        $rowCount = 0;
        $advanced = false;

        while ($advanced || $this->read($reader)) {
            $advanced = false;

            if ($reader->nodeType === XMLReader::DOC_TYPE) {
                throw $this->parserError();
            }

            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->depth < 1) {
                continue;
            }

            if ($recordName === null && $this->isRecordCandidate($reader->localName)) {
                $recordName = $reader->localName;
            }

            if ($reader->localName !== $recordName || ($recordDepth !== null && $reader->depth !== $recordDepth)) {
                continue;
            }

            $recordDepth ??= $reader->depth;
            $node = @$reader->expand();

            if (! $node instanceof DOMElement) {
                throw $this->parserError();
            }

            if (++$rowCount > $options->maxRows) {
                throw new FeedParseException(FeedErrorCode::RowLimitExceeded, ['limit' => $options->maxRows], $node->getLineNo());
            }

            yield new RawFeedRow($node->getLineNo(), $this->fields($node));

            // Skip the record's subtree; next() already positions on the following node.
            $advanced = $this->next($reader);
        }

        if ($rowCount === 0) {
            throw new FeedParseException(FeedErrorCode::EmptyFeed);
        }
    }

    private function read(XMLReader $reader): bool
    {
        if (@$reader->read()) {
            return true;
        }

        $this->throwIfLibxmlFailed();

        return false;
    }

    private function next(XMLReader $reader): bool
    {
        if (@$reader->next()) {
            return true;
        }

        $this->throwIfLibxmlFailed();

        return false;
    }

    private function throwIfLibxmlFailed(): void
    {
        $errors = libxml_get_errors();

        foreach ($errors as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                throw $this->parserError($error->line > 0 ? $error->line : null);
            }
        }
    }

    private function parserError(?int $line = null): FeedParseException
    {
        if ($line === null) {
            $last = libxml_get_last_error();
            $line = $last !== false && $last->line > 0 ? $last->line : null;
        }

        return new FeedParseException(FeedErrorCode::ParserError, ['format' => 'XML'], $line);
    }

    private function isRecordCandidate(string $localName): bool
    {
        foreach (ParseOptions::RECORD_ELEMENTS as $candidate) {
            if (strcasecmp($candidate, $localName) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function fields(DOMElement $record): array
    {
        $fields = [];

        foreach ($record->attributes as $attribute) {
            $fields['@'.$attribute->nodeName] = trim($attribute->nodeValue ?? '');
        }

        foreach ($record->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $key = $child->localName ?? $child->nodeName;

            if ($this->hasElementChildren($child)) {
                foreach ($child->childNodes as $grandChild) {
                    if ($grandChild instanceof DOMElement) {
                        $fields[$key.'.'.($grandChild->localName ?? $grandChild->nodeName)] ??= trim($grandChild->textContent);
                    }
                }

                continue;
            }

            $fields[$key] ??= trim($child->textContent);
        }

        return $fields;
    }

    private function hasElementChildren(DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }
}
