<?php

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Mapping\FeedField;
use App\Domain\Feeds\Validation\FeedIssueSeverity;

function feedTranslations(): array
{
    return require dirname(__DIR__, 3).'/lang/en/feeds.php';
}

it('classifies every code by the §3 taxonomy', function () {
    $bySeverity = [];

    foreach (FeedErrorCode::cases() as $code) {
        $bySeverity[$code->severity()->value][] = $code->value;
    }

    expect($bySeverity[FeedIssueSeverity::Fatal->value])->toBe([
        'UNREACHABLE_URL', 'HTTP_ERROR', 'FETCH_TIMEOUT', 'BLOCKED_DESTINATION', 'AUTH_FAILED', 'PAYLOAD_TOO_LARGE',
        'UNSUPPORTED_CONTENT_TYPE', 'UNSUPPORTED_ENCODING', 'PARSER_ERROR', 'EMPTY_FEED', 'ROW_LIMIT_EXCEEDED',
        'REJECT_THRESHOLD_EXCEEDED', 'STALLED', 'FETCH_DISABLED', 'INTERNAL_ERROR',
    ])->and($bySeverity[FeedIssueSeverity::Error->value])->toBe([
        'MISSING_SKU', 'MISSING_REQUIRED_FIELD', 'INVALID_PRICE', 'INVALID_CURRENCY', 'INVALID_AVAILABILITY',
        'INVALID_URL', 'DUPLICATE_SKU', 'SKU_OWNED_BY_OTHER_SOURCE', 'FIELD_TOO_LONG', 'IMPOSSIBLE_DISCOUNT',
    ])->and($bySeverity[FeedIssueSeverity::Warning->value])->toBe([
        'INVALID_GTIN', 'MISSING_GTIN', 'UNKNOWN_BRAND', 'INVALID_STOCK', 'INVALID_IMAGE_URL', 'URL_DOMAIN_MISMATCH',
        'MASS_REMOVAL_HELD',
    ])->and(FeedErrorCode::AuthFailed->isRunFatal())->toBeTrue()
        ->and(FeedErrorCode::DuplicateSku->rejectsRow())->toBeTrue()
        ->and(FeedErrorCode::InvalidGtin->rejectsRow())->toBeFalse();
});

it('has a merchant-facing message for every code whose placeholders are exactly its params', function (FeedErrorCode $code) {
    $message = feedTranslations()['errors'][$code->value] ?? null;

    expect($code->messageKey())->toBe('feeds.errors.'.$code->value)
        ->and($message)->toBeString()->not->toBeEmpty();

    preg_match_all('/:([a-z_]+)/', (string) $message, $matches);
    $placeholders = array_values(array_unique($matches[1]));
    sort($placeholders);
    $params = $code->messageParams();
    sort($params);

    expect($placeholders)->toBe($params);
})->with(FeedErrorCode::cases());

it('keeps the reject-threshold message actionable', function () {
    expect(feedTranslations()['errors']['REJECT_THRESHOLD_EXCEEDED'])
        ->toBe('More than :percent% of rows failed validation, so nothing was published. Fix the rejected rows below and run the import again.');
});

it('labels every canonical field', function () {
    expect(array_keys(feedTranslations()['fields']))
        ->toBe(array_map(fn ($field) => $field->value, FeedField::cases()));
});
