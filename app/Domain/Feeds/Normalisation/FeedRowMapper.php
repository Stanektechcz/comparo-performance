<?php

namespace App\Domain\Feeds\Normalisation;

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Mapping\FieldMapping;
use App\Domain\Feeds\Parsing\RawFeedRow;
use App\Domain\Feeds\Validation\FeedItemValidator;
use App\Domain\Feeds\Validation\Gtin;
use App\Domain\Feeds\Validation\RowRejection;
use App\Domain\Offers\Availability;
use App\Domain\Shared\Money;

/**
 * Maps one raw row onto the feed contract (§3). Pure: no DB, facades, config
 * or clock. Every problem in the row is reported, not only the first one.
 */
final class FeedRowMapper
{
    public const int MAX_TITLE_LENGTH = 255;

    public const int MAX_IDENTIFIER_LENGTH = 128;

    public const int MAX_ATTRIBUTE_LENGTH = 255;

    /** Merchant values echoed in messages are cut to this many characters. */
    private const int MAX_ECHOED_VALUE = 64;

    private const string STOCK_PATTERN = '/^\+?\d{1,9}$/';

    public function map(RawFeedRow $row, FieldMapping $mapping, NormalisationContext $context): NormalisedFeedItem|RowRejection
    {
        $issues = new IssueCollector;
        $read = static function (FeedField $field) use ($row, $mapping): ?string {
            $source = $mapping->sourceFor($field);

            return $source === null ? null : TextCleaner::collapse($row->get($source));
        };

        $sku = $this->identifier($read(FeedField::MerchantSku), FeedField::MerchantSku, $issues);
        $externalId = $this->identifier($read(FeedField::ExternalId), FeedField::ExternalId, $issues);
        $title = $this->title($read(FeedField::Title), $issues);
        $currency = $this->currency($read(FeedField::Currency), $read(FeedField::Price), $context, $issues);
        $price = $currency === null ? null : $this->price($read(FeedField::Price), $currency, $context, $issues);
        $availability = $this->availability($read(FeedField::Availability), $context, $issues);
        $productUrl = $this->productUrl($read(FeedField::ProductUrl), $context, $issues);
        $gtin = $this->gtin($read(FeedField::Gtin), $issues);
        $attributes = [];

        foreach ([FeedField::Brand, FeedField::PackSize, FeedField::Variant, FeedField::Category] as $field) {
            $attributes[$field->value] = $this->attribute($read($field), $field, $issues);
        }

        $referencePrice = $price === null ? null : $this->referencePrice($read(FeedField::ReferencePrice), $price, $context, $issues);
        $stock = $this->stock($read(FeedField::Stock), $issues);
        $imageUrl = $this->imageUrl($read(FeedField::ImageUrl), $issues);

        if ($issues->hasErrors() || $sku === null || $title === null || $price === null || $availability === null || $productUrl === null) {
            return new RowRejection($row->lineNumber, $sku, $issues->errors());
        }

        return new NormalisedFeedItem(
            lineNumber: $row->lineNumber,
            merchantSku: $sku,
            externalId: $externalId,
            title: $title,
            price: $price,
            referencePrice: $referencePrice,
            currency: $price->currency,
            availability: $availability,
            stockQuantity: $stock,
            productUrl: $productUrl,
            imageUrl: $imageUrl,
            gtin: $gtin[0],
            gtinValid: $gtin[1],
            brandRaw: $attributes[FeedField::Brand->value],
            packRaw: $attributes[FeedField::PackSize->value],
            variantRaw: $attributes[FeedField::Variant->value],
            categoryRaw: $attributes[FeedField::Category->value],
            shippingHint: $this->shippingHint($read(FeedField::ShippingHint), $price->currency, $context),
            sourceUpdatedAt: SourceDateParser::parse($read(FeedField::UpdatedAt)),
            warnings: $issues->warnings(),
        );
    }

    private function identifier(?string $value, FeedField $field, IssueCollector $issues): ?string
    {
        if ($value === null) {
            if ($field === FeedField::MerchantSku) {
                $issues->add(FeedErrorCode::MissingSku, $field);
            }

            return null;
        }

        if (mb_strlen($value) > self::MAX_IDENTIFIER_LENGTH) {
            $issues->add(FeedErrorCode::FieldTooLong, $field, ['field' => $field->value, 'max' => self::MAX_IDENTIFIER_LENGTH]);
        }

        return $value;
    }

    private function title(?string $value, IssueCollector $issues): ?string
    {
        $title = TextCleaner::clean($value);

        if ($title === null) {
            $issues->add(FeedErrorCode::MissingRequiredField, FeedField::Title, ['field' => FeedField::Title->value]);

            return null;
        }

        return TextCleaner::truncate($title, self::MAX_TITLE_LENGTH);
    }

    private function attribute(?string $value, FeedField $field, IssueCollector $issues): ?string
    {
        $clean = TextCleaner::clean($value);

        if ($clean !== null && mb_strlen($clean) > self::MAX_ATTRIBUTE_LENGTH) {
            $issues->add(FeedErrorCode::FieldTooLong, $field, ['field' => $field->value, 'max' => self::MAX_ATTRIBUTE_LENGTH]);
        }

        return $clean;
    }

    /**
     * Currency column, else an ISO code written in the price ("43,50 EUR"),
     * else the source's default currency.
     */
    private function currency(?string $value, ?string $rawPrice, NormalisationContext $context, IssueCollector $issues): ?string
    {
        $code = $value !== null ? strtoupper($value) : null;

        if ($code === null && $rawPrice !== null && preg_match('/(?<![A-Za-z])([A-Z]{3})(?![A-Za-z])/', $rawPrice, $match) === 1) {
            $code = $match[1];
        }

        $code ??= $context->defaultCurrency;

        if ($code === null) {
            $issues->add(FeedErrorCode::MissingRequiredField, FeedField::Currency, ['field' => FeedField::Currency->value]);

            return null;
        }

        if (! $context->isKnownCurrency($code)) {
            $issues->add(FeedErrorCode::InvalidCurrency, FeedField::Currency, ['value' => $this->echo($value ?? $code)]);

            return null;
        }

        return $code;
    }

    private function price(?string $raw, string $currency, NormalisationContext $context, IssueCollector $issues): ?Money
    {
        if ($raw === null) {
            $issues->add(FeedErrorCode::MissingRequiredField, FeedField::Price, ['field' => FeedField::Price->value]);

            return null;
        }

        $amount = DecimalMoneyParser::parse($raw, $context->minorUnitsFor($currency));

        if ($amount !== null && $amount->currencyCode !== null && $amount->currencyCode !== $currency) {
            $issues->add(FeedErrorCode::InvalidCurrency, FeedField::Price, ['value' => $amount->currencyCode]);

            return null;
        }

        if ($amount === null || $amount->minor <= 0) {
            $issues->add(FeedErrorCode::InvalidPrice, FeedField::Price, ['field' => FeedField::Price->value, 'value' => $this->echo($raw)]);

            return null;
        }

        return Money::of($amount->minor, $currency);
    }

    /**
     * An unparseable reference price, or one not above the price, is dropped
     * silently (it only drives the "was" display); an implausible discount rejects.
     */
    private function referencePrice(?string $raw, Money $price, NormalisationContext $context, IssueCollector $issues): ?Money
    {
        $amount = $raw === null ? null : DecimalMoneyParser::parse($raw, $context->minorUnitsFor($price->currency));

        if ($amount === null || $amount->minor <= $price->minor || ($amount->currencyCode ?? $price->currency) !== $price->currency) {
            return null;
        }

        if (FeedItemValidator::isImpossibleDiscount($price->minor, $amount->minor, $context->maxDiscountBasisPoints)) {
            $issues->add(FeedErrorCode::ImpossibleDiscount, FeedField::ReferencePrice, [
                'percent' => FeedItemValidator::discountPercent($price->minor, $amount->minor),
            ]);

            return null;
        }

        return Money::of($amount->minor, $price->currency);
    }

    private function shippingHint(?string $raw, string $currency, NormalisationContext $context): ?Money
    {
        $amount = $raw === null ? null : DecimalMoneyParser::parse($raw, $context->minorUnitsFor($currency));

        if ($amount === null || ($amount->currencyCode ?? $currency) !== $currency) {
            return null;
        }

        return Money::of($amount->minor, $currency);
    }

    private function availability(?string $raw, NormalisationContext $context, IssueCollector $issues): ?Availability
    {
        if ($raw === null) {
            $issues->add(FeedErrorCode::MissingRequiredField, FeedField::Availability, ['field' => FeedField::Availability->value]);

            return null;
        }

        $availability = AvailabilityNormaliser::normalise($raw, $context->availabilityMap);

        if ($availability === null) {
            $issues->add(FeedErrorCode::InvalidAvailability, FeedField::Availability, ['value' => $this->echo($raw)]);
        }

        return $availability;
    }

    private function stock(?string $raw, IssueCollector $issues): ?int
    {
        if ($raw === null) {
            return null;
        }

        if (preg_match(self::STOCK_PATTERN, $raw) !== 1) {
            $issues->add(FeedErrorCode::InvalidStock, FeedField::Stock, ['value' => $this->echo($raw)]);

            return null;
        }

        return (int) ltrim($raw, '+');
    }

    private function productUrl(?string $raw, NormalisationContext $context, IssueCollector $issues): ?string
    {
        if ($raw === null) {
            $issues->add(FeedErrorCode::MissingRequiredField, FeedField::ProductUrl, ['field' => FeedField::ProductUrl->value]);

            return null;
        }

        if (! FeedItemValidator::isValidHttpUrl($raw)) {
            $issues->add(FeedErrorCode::InvalidUrl, FeedField::ProductUrl, ['field' => FeedField::ProductUrl->value]);

            return null;
        }

        if ($context->merchantDomain !== null && ! FeedItemValidator::hostMatchesDomain($raw, $context->merchantDomain)) {
            $issues->add(FeedErrorCode::UrlDomainMismatch, FeedField::ProductUrl, [
                'host' => $this->echo((string) FeedItemValidator::host($raw)),
                'domain' => $context->merchantDomain,
            ]);
        }

        return $raw;
    }

    private function imageUrl(?string $raw, IssueCollector $issues): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (! FeedItemValidator::isValidHttpUrl($raw)) {
            $issues->add(FeedErrorCode::InvalidImageUrl, FeedField::ImageUrl);

            return null;
        }

        return $raw;
    }

    /**
     * @return array{0: string|null, 1: bool} digits (kept even when invalid), check-digit validity
     */
    private function gtin(?string $raw, IssueCollector $issues): array
    {
        if ($raw === null) {
            $issues->add(FeedErrorCode::MissingGtin, FeedField::Gtin);

            return [null, false];
        }

        $digits = Gtin::normalise($raw);
        $valid = $digits !== null && Gtin::isValid($digits);

        if (! $valid) {
            $issues->add(FeedErrorCode::InvalidGtin, FeedField::Gtin, ['value' => $this->echo($raw)]);
        }

        return [$digits, $valid];
    }

    private function echo(string $value): string
    {
        return TextCleaner::truncate($value, self::MAX_ECHOED_VALUE);
    }
}
