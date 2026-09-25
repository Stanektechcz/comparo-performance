<?php

namespace App\Domain\Feeds;

use App\Domain\Feeds\Validation\FeedIssueSeverity;

/**
 * Feed error taxonomy (docs/architecture/phase-2-feeds-matching.md §3).
 *
 * Run-fatal codes stop the whole run, row-reject codes drop one row and
 * row-warning codes keep the row published. Every code has a merchant-facing,
 * actionable message under `feeds.errors.{CODE}` whose placeholders are exactly
 * {@see self::messageParams()}. Messages never carry resolved IPs or secrets.
 */
enum FeedErrorCode: string
{
    // Run-fatal
    case UnreachableUrl = 'UNREACHABLE_URL';
    case HttpError = 'HTTP_ERROR';
    case FetchTimeout = 'FETCH_TIMEOUT';
    case BlockedDestination = 'BLOCKED_DESTINATION';
    case AuthFailed = 'AUTH_FAILED';
    case PayloadTooLarge = 'PAYLOAD_TOO_LARGE';
    case UnsupportedContentType = 'UNSUPPORTED_CONTENT_TYPE';
    case UnsupportedEncoding = 'UNSUPPORTED_ENCODING';
    case ParserError = 'PARSER_ERROR';
    case EmptyFeed = 'EMPTY_FEED';
    case RowLimitExceeded = 'ROW_LIMIT_EXCEEDED';
    case RejectThresholdExceeded = 'REJECT_THRESHOLD_EXCEEDED';
    case Stalled = 'STALLED';

    // Row-reject
    case MissingSku = 'MISSING_SKU';
    case MissingRequiredField = 'MISSING_REQUIRED_FIELD';
    case InvalidPrice = 'INVALID_PRICE';
    case InvalidCurrency = 'INVALID_CURRENCY';
    case InvalidAvailability = 'INVALID_AVAILABILITY';
    case InvalidUrl = 'INVALID_URL';
    case DuplicateSku = 'DUPLICATE_SKU';
    case SkuOwnedByOtherSource = 'SKU_OWNED_BY_OTHER_SOURCE';
    case FieldTooLong = 'FIELD_TOO_LONG';
    case ImpossibleDiscount = 'IMPOSSIBLE_DISCOUNT';

    // Row-warning
    case InvalidGtin = 'INVALID_GTIN';
    case MissingGtin = 'MISSING_GTIN';
    case UnknownBrand = 'UNKNOWN_BRAND';
    case InvalidStock = 'INVALID_STOCK';
    case InvalidImageUrl = 'INVALID_IMAGE_URL';
    case UrlDomainMismatch = 'URL_DOMAIN_MISMATCH';

    public function severity(): FeedIssueSeverity
    {
        return match ($this) {
            self::UnreachableUrl,
            self::HttpError,
            self::FetchTimeout,
            self::BlockedDestination,
            self::AuthFailed,
            self::PayloadTooLarge,
            self::UnsupportedContentType,
            self::UnsupportedEncoding,
            self::ParserError,
            self::EmptyFeed,
            self::RowLimitExceeded,
            self::RejectThresholdExceeded,
            self::Stalled => FeedIssueSeverity::Fatal,

            self::MissingSku,
            self::MissingRequiredField,
            self::InvalidPrice,
            self::InvalidCurrency,
            self::InvalidAvailability,
            self::InvalidUrl,
            self::DuplicateSku,
            self::SkuOwnedByOtherSource,
            self::FieldTooLong,
            self::ImpossibleDiscount => FeedIssueSeverity::Error,

            self::InvalidGtin,
            self::MissingGtin,
            self::UnknownBrand,
            self::InvalidStock,
            self::InvalidImageUrl,
            self::UrlDomainMismatch => FeedIssueSeverity::Warning,
        };
    }

    public function isRunFatal(): bool
    {
        return $this->severity() === FeedIssueSeverity::Fatal;
    }

    public function rejectsRow(): bool
    {
        return $this->severity() === FeedIssueSeverity::Error;
    }

    /**
     * Translation key of the merchant-facing message.
     */
    public function messageKey(): string
    {
        return 'feeds.errors.'.$this->value;
    }

    /**
     * The parameter names the message expects (and the only ones producers set).
     *
     * @return list<string>
     */
    public function messageParams(): array
    {
        return match ($this) {
            self::HttpError => ['status'],
            self::FetchTimeout => ['seconds'],
            self::PayloadTooLarge => ['limit_mb'],
            self::UnsupportedContentType => ['content_type'],
            self::UnsupportedEncoding => ['encoding'],
            self::ParserError => ['format'],
            self::RowLimitExceeded => ['limit'],
            self::RejectThresholdExceeded => ['percent'],
            self::MissingRequiredField, self::InvalidUrl => ['field'],
            self::InvalidPrice => ['field', 'value'],
            self::InvalidCurrency, self::InvalidAvailability, self::InvalidGtin, self::InvalidStock => ['value'],
            self::DuplicateSku => ['sku', 'first_line'],
            self::SkuOwnedByOtherSource => ['sku'],
            self::FieldTooLong => ['field', 'max'],
            self::ImpossibleDiscount => ['percent'],
            self::UnknownBrand => ['brand'],
            self::UrlDomainMismatch => ['host', 'domain'],
            default => [],
        };
    }
}
