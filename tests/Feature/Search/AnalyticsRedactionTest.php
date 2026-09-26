<?php

use App\Domain\Search\Analytics\BotDetector;
use App\Domain\Search\Analytics\QueryRedactor;
use App\Domain\Search\Analytics\SessionHasher;

/**
 * Privacy primitives of search analytics (A-24): what a stored query can
 * contain, how sessions are pseudonymised and which agents count as bots.
 */
it('folds, collapses and redacts personal data but keeps product identifiers', function (string $raw, string $stored) {
    $redacted = (new QueryRedactor)->redact($raw);

    expect($redacted->text)->toBe($stored)
        ->and($redacted->hash)->toBe(hash('sha256', $stored));
})->with([
    'case and whitespace' => ['  Whey   PROTEIN ', 'whey protein'],
    'diacritics' => ['Créatine Monohydrát', 'creatine monohydrat'],
    'e-mail address' => ['mail Jane.Doe+shop@Example.COM please', 'mail [email] please'],
    'international phone' => ['+49 171 5551234', '[phone]'],
    'phone without separators' => ['+491715551234', '[phone]'],
    'national phone and text' => ['0171 5551234 whey', '[phone] whey'],
    'area code in brackets' => ['call (030) 123 4567', 'call [phone]'],
    'dashed phone' => ['030-123-4567', '[phone]'],
    'card-like number groups' => ['4111 1111 1111 1111', '[phone]'],
    '7-digit run' => ['order 1234567', 'order [number]'],
    '9-digit run' => ['123456789', '[number]'],
    '16-digit run' => ['4111111111111111', '[number]'],
    'EAN-13 kept' => ['4006381333931', '4006381333931'],
    'EAN-8 kept' => ['96385074', '96385074'],
    'UPC-A kept' => ['012345678905', '012345678905'],
    'GTIN-14 kept' => ['10012345678902', '10012345678902'],
    'EAN-13 in text kept' => ['whey 4006381333931 vanilla', 'whey 4006381333931 vanilla'],
    'EAN-13 with a wrong check digit' => ['4006381333932', '[number]'],
    'EAN-8 with a wrong check digit' => ['96385075', '[number]'],
    'unseparated international phone, 12 digits' => ['491715551234', '[number]'],
    'unseparated international phone, 13 digits' => ['4930123456789', '[number]'],
    'unseparated phone with 00 prefix, 14 digits' => ['00491715551234', '[number]'],
    'unseparated 8-digit local phone' => ['01715551', '[number]'],
    'unseparated phone in text' => ['call 420776123456 now', 'call [number] now'],
    'pack sizes kept' => ['creatine 500 g 1000 mg', 'creatine 500 g 1000 mg'],
    'short numbers kept' => ['bcaa 2:1:1', 'bcaa 2:1:1'],
    'sku with letters kept' => ['TST-AB1234567', 'tst-ab1234567'],
]);

it('caps stored queries at 100 characters', function () {
    $redacted = (new QueryRedactor)->redact(str_repeat('protein ', 40));

    expect(mb_strlen($redacted->text))->toBeLessThanOrEqual(QueryRedactor::MAX_LENGTH)
        ->and($redacted->text)->toStartWith('protein protein')
        ->and($redacted->text)->not->toEndWith(' ');
});

it('hashes sessions per UTC day so the hash rotates daily', function () {
    $hasher = new SessionHasher('base64:test-application-key');
    $session = str_repeat('a', 40);

    $morning = $hasher->hash($session, new DateTimeImmutable('2026-09-25 00:05:00', new DateTimeZone('UTC')));
    $evening = $hasher->hash($session, new DateTimeImmutable('2026-09-25 23:55:00', new DateTimeZone('UTC')));
    $nextDay = $hasher->hash($session, new DateTimeImmutable('2026-09-26 00:05:00', new DateTimeZone('UTC')));
    $otherKey = (new SessionHasher('base64:another-key'))->hash($session, new DateTimeImmutable('2026-09-25 00:05:00', new DateTimeZone('UTC')));

    expect($morning)->toMatch('/^[0-9a-f]{64}$/')
        ->toBe($evening)
        ->not->toBe($nextDay)
        ->not->toBe($otherKey)
        ->not->toContain($session)
        ->and($hasher->hash(null, new DateTimeImmutable))->toBeNull()
        ->and($hasher->hash('', new DateTimeImmutable))->toBeNull();
});

it('flags crawlers, HTTP libraries, headless browsers and missing agents as bots', function (?string $agent, bool $isBot) {
    expect((new BotDetector)->isBot($agent))->toBe($isBot);
})->with([
    'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
    'Bingbot' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', true],
    'curl' => ['curl/8.4.0', true],
    'python requests' => ['python-requests/2.31', true],
    'headless Chrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36', true],
    'link preview' => ['facebookexternalhit/1.1', true],
    'uptime monitor' => ['UptimeRobot/2.0', true],
    'no agent' => [null, true],
    'blank agent' => ['   ', true],
    'desktop Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', false],
    'iPhone Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', false],
    'Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', false],
]);
