<?php

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Fetching\DestinationGuard;
use App\Domain\Feeds\Fetching\FeedFetchException;

function guardFailure(Closure $call): ?FeedErrorCode
{
    try {
        $call();
    } catch (FeedFetchException $exception) {
        return $exception->errorCode;
    }

    return null;
}

it('accepts public http(s) URLs on ports 80 and 443', function (string $url, string $host, int $port, ?string $literal) {
    $destination = (new DestinationGuard)->parse($url);

    expect($destination->host)->toBe($host)
        ->and($destination->port)->toBe($port)
        ->and($destination->literalAddress)->toBe($literal);
})->with([
    ['https://peaksupps.de/feeds/heureka.xml?token=abc', 'peaksupps.de', 443, null],
    ['http://proteinpoint.cz/export.csv', 'proteinpoint.cz', 80, null],
    ['https://Feeds.PeakSupps.de:443/x', 'feeds.peaksupps.de', 443, null],
    ['http://peaksupps.de:80/x', 'peaksupps.de', 80, null],
    ['https://peaksupps.de./x', 'peaksupps.de', 443, null],
    ['http://93.184.216.34/feed.xml', '93.184.216.34', 80, '93.184.216.34'],
    ['https://[2606:4700:4700::1111]/feed.xml', '2606:4700:4700::1111', 443, '2606:4700:4700::1111'],
    ['https://müller-sport.de/feed.xml', 'xn--mller-sport-thb.de', 443, null],
]);

it('refuses unsafe URLs with BLOCKED_DESTINATION', function (string $url) {
    expect(guardFailure(fn () => (new DestinationGuard)->parse($url)))->toBe(FeedErrorCode::BlockedDestination);
})->with([
    'file' => 'file:///etc/passwd',
    'gopher' => 'gopher://peaksupps.de:70/_GET',
    'ftp' => 'ftp://peaksupps.de/feed.xml',
    'data' => 'data:text/plain,hello',
    'dict' => 'dict://peaksupps.de:2628/',
    'no scheme' => 'peaksupps.de/feed.xml',
    'port 8080' => 'http://peaksupps.de:8080/feed.xml',
    'port 22' => 'https://peaksupps.de:22/',
    'userinfo' => 'http://user@127.0.0.1/',
    'userinfo on public host' => 'https://user:secret@peaksupps.de/feed.xml',
    'decimal IPv4' => 'http://2130706433/',
    'hex IPv4' => 'http://0x7f.0.0.1/',
    'hex IPv4 whole' => 'http://0x7f000001/',
    'octal IPv4' => 'http://0177.0.0.1/',
    'short IPv4' => 'http://127.1/',
    'leading-zero IPv4' => 'http://010.0.0.1/',
    'loopback IPv4' => 'http://127.0.0.1/',
    'metadata IPv4' => 'http://169.254.169.254/latest/meta-data/',
    'loopback IPv6' => 'http://[::1]/',
    'mapped IPv6' => 'http://[::ffff:127.0.0.1]/',
    'mapped IPv6 hex' => 'http://[::ffff:7f00:1]/',
    'zone id' => 'http://[fe80::1%25eth0]/',
    'localhost' => 'http://localhost/feed.xml',
    'localhost subdomain' => 'http://feeds.localhost/',
    'dot-local' => 'http://printer.local/',
    'internal' => 'http://metadata.google.internal/',
    'backslash' => 'http://peaksupps.de\\@127.0.0.1/',
    'whitespace' => "http://peaksupps.de/\nfeed.xml",
    'empty host' => 'http:///feed.xml',
]);

it('classifies addresses in every blocked range', function (string $address) {
    expect((new DestinationGuard)->isBlockedAddress($address))->toBeTrue();
})->with([
    '0.0.0.0', '0.1.2.3', '10.0.0.1', '10.255.255.255', '100.64.0.1', '100.127.255.255', '127.0.0.1', '127.255.0.1',
    '169.254.169.254', '172.16.0.1', '172.31.255.255', '192.0.0.8', '192.0.2.10', '192.168.1.1', '198.18.0.1',
    '198.19.255.255', '198.51.100.7', '203.0.113.9', '224.0.0.251', '239.255.255.250', '240.0.0.1', '255.255.255.255',
    '::', '::1', 'fc00::1', 'fd12:3456::1', 'fe80::1', 'febf::1', 'ff02::1', '::ffff:127.0.0.1', '::ffff:8.8.8.8',
    '::ffff:169.254.169.254', '64:ff9b::7f00:1', '2001:db8::1', '100::1', '2001::1', '2002:7f00:1::1', '2002:a00:1::',
    'not-an-ip', '',
]);

it('allows public addresses', function (string $address) {
    expect((new DestinationGuard)->isBlockedAddress($address))->toBeFalse();
})->with(['93.184.216.34', '8.8.8.8', '1.1.1.1', '100.63.255.255', '100.128.0.0', '172.15.255.255', '172.32.0.1', '2606:4700:4700::1111', '2a00:1450:4001::200e', '2002:5db8:d822::1']);

it('refuses a host when ANY resolved address is private (DNS rebinding)', function () {
    $guard = new DestinationGuard;

    expect(guardFailure(fn () => $guard->assertPublic(['93.184.216.34', '10.0.0.5'])))->toBe(FeedErrorCode::BlockedDestination)
        ->and(guardFailure(fn () => $guard->assertPublic(['2606:4700:4700::1111', '::1'])))->toBe(FeedErrorCode::BlockedDestination)
        ->and(guardFailure(fn () => $guard->assertPublic([])))->toBe(FeedErrorCode::UnreachableUrl)
        ->and(guardFailure(fn () => $guard->assertPublic(['93.184.216.34', '2606:4700:4700::1111'])))->toBeNull();
});

it('never puts the URL or an address in the exception message', function () {
    try {
        (new DestinationGuard)->parse('http://user:hunter2@10.0.0.1/?token=s3cret');
    } catch (FeedFetchException $exception) {
        expect($exception->getMessage())->toBe('Feed fetch failed: BLOCKED_DESTINATION.')
            ->and($exception->getMessage())->not->toContain('10.0.0.1');
    }
});
