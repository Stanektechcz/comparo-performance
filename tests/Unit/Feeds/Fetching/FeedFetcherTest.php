<?php

use App\Domain\Feeds\FeedErrorCode;
use App\Domain\Feeds\Fetching\FeedCredentials;
use App\Domain\Feeds\Fetching\FeedFetcher;
use App\Domain\Feeds\Fetching\FeedFetchException;
use App\Domain\Feeds\Fetching\FeedFetchRequest;
use App\Domain\Feeds\Fetching\HostResolver;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;

const FEED_URL = 'https://peaksupps.de/feeds/heureka.xml?token=s3cret-token';
const FEED_XML = '<?xml version="1.0"?><SHOP><SHOPITEM><ITEM_ID>PEA-186</ITEM_ID><PRODUCTNAME>Whey Isolate 90</PRODUCTNAME></SHOPITEM></SHOP>';

/**
 * A resolver answering from a fixed table (unknown names do not resolve).
 *
 * @param  array<string, list<string>>  $table
 */
function fakeResolver(array $table): HostResolver
{
    return new class($table) implements HostResolver
    {
        /** @var list<string> */
        public array $asked = [];

        /**
         * @param  array<string, list<string>>  $table
         */
        public function __construct(private array $table) {}

        public function resolve(string $host): array
        {
            $this->asked[] = $host;

            return $this->table[$host] ?? [];
        }
    };
}

beforeEach(function () {
    $this->http = new Factory;
    $this->http->preventStrayRequests();
    $this->transferOptions = [];
    $this->http->globalMiddleware(function (callable $handler) {
        return function ($request, array $options) use ($handler) {
            $this->transferOptions[] = $options;

            return $handler($request, $options);
        };
    });
    $this->resolver = fakeResolver([
        'peaksupps.de' => ['93.184.216.34'],
        'cdn.peaksupps.de' => ['93.184.216.35', '2606:4700:4700::1111'],
        'mirror.example.com' => ['151.101.1.1'],
        'rebind.example.com' => ['93.184.216.36', '10.0.0.5'],
        'internal.example.com' => ['169.254.169.254'],
    ]);
    $this->fetcher = new FeedFetcher($this->http, $this->resolver);
    $this->destination = tempnam(sys_get_temp_dir(), 'feed');
    @unlink($this->destination);
});

afterEach(function () {
    @unlink($this->destination);
});

function fetchFailure(Closure $fetch): FeedFetchException
{
    try {
        $fetch();
    } catch (FeedFetchException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a FeedFetchException.');
}

it('streams the feed to disk with its size and sha256, pinned to the vetted address', function () {
    $this->http->fake(['peaksupps.de/*' => Factory::response(FEED_XML, 200, ['Content-Type' => 'application/xml; charset=utf-8'])]);

    $result = $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination));

    expect($result->path)->toBe($this->destination)
        ->and(file_get_contents($this->destination))->toBe(FEED_XML)
        ->and($result->bytes)->toBe(strlen(FEED_XML))
        ->and($result->sha256)->toBe(hash('sha256', FEED_XML))
        ->and($result->contentType)->toBe('application/xml')
        ->and($result->status)->toBe(200)
        ->and($result->redirects)->toBe(0)
        ->and($result->decompressed)->toBeFalse()
        ->and($this->transferOptions[0]['curl'][CURLOPT_RESOLVE])->toBe(['peaksupps.de:443:93.184.216.34'])
        ->and($this->transferOptions[0]['allow_redirects'])->toBeFalse()
        ->and($this->transferOptions[0]['connect_timeout'])->toBe(5)
        ->and($this->transferOptions[0]['timeout'])->toBe(120)
        ->and($this->transferOptions[0]['proxy'])->toBe(['no' => ['*']])
        ->and($this->transferOptions[0]['decode_content'])->toBeFalse();

    $this->http->assertSentCount(1);
});

it('refuses a host that resolves to a private address without sending anything', function () {
    $this->http->fake();

    $exception = fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest('https://internal.example.com/feed.xml', $this->destination)));

    expect($exception->errorCode)->toBe(FeedErrorCode::BlockedDestination);
    $this->http->assertNothingSent();
});

it('refuses DNS answers mixing public and private addresses (rebinding)', function () {
    $this->http->fake();

    expect(fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest('https://rebind.example.com/feed.xml', $this->destination)))->errorCode)
        ->toBe(FeedErrorCode::BlockedDestination);
    $this->http->assertNothingSent();
});

it('reports a name that does not resolve as UNREACHABLE_URL', function () {
    $this->http->fake();

    expect(fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest('https://nx.example.com/feed.xml', $this->destination)))->errorCode)
        ->toBe(FeedErrorCode::UnreachableUrl);
});

it('refuses literal private and metadata addresses before any request', function (string $url) {
    $this->http->fake();

    expect(fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest($url, $this->destination)))->errorCode)
        ->toBe(FeedErrorCode::BlockedDestination);
    $this->http->assertNothingSent();
})->with(['http://169.254.169.254/latest/meta-data/', 'http://[::1]/', 'http://[::ffff:127.0.0.1]/', 'http://2130706433/', 'http://user@127.0.0.1/', 'file:///etc/passwd']);

it('re-validates every redirect hop and refuses one to a private address', function (string $location) {
    $this->http->fake(['peaksupps.de/*' => Factory::response('', 302, ['Location' => $location])]);

    expect(fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination)))->errorCode)
        ->toBe(FeedErrorCode::BlockedDestination);
    $this->http->assertSentCount(1);
})->with([
    'IPv4 literal' => 'http://10.0.0.1/feed.xml',
    'metadata' => 'http://169.254.169.254/latest/meta-data/',
    'name resolving private' => 'https://internal.example.com/feed.xml',
    'odd port' => 'https://peaksupps.de:8443/feed.xml',
    'https to http downgrade' => 'http://peaksupps.de/feed.xml',
    'non-http scheme' => 'gopher://peaksupps.de/',
]);

it('follows up to three redirects, resolving relative locations and re-pinning each hop', function () {
    $this->http->fake([
        'https://peaksupps.de/feeds/heureka.xml*' => Factory::response('', 301, ['Location' => '/feeds/v2.xml']),
        'https://peaksupps.de/feeds/v2.xml' => Factory::response('', 302, ['Location' => 'https://cdn.peaksupps.de/v2.xml']),
        'https://cdn.peaksupps.de/v2.xml' => Factory::response('', 307, ['Location' => 'https://mirror.example.com/final.xml']),
        'https://mirror.example.com/final.xml' => Factory::response(FEED_XML, 200, ['Content-Type' => 'text/xml']),
    ]);

    $result = $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination));

    expect($result->redirects)->toBe(3)
        ->and(file_get_contents($this->destination))->toBe(FEED_XML)
        ->and(array_map(fn (array $options) => $options['curl'][CURLOPT_RESOLVE][0], $this->transferOptions))->toBe([
            'peaksupps.de:443:93.184.216.34',
            'peaksupps.de:443:93.184.216.34',
            'cdn.peaksupps.de:443:93.184.216.35',
            'mirror.example.com:443:151.101.1.1',
        ]);
});

it('stops after three redirects with HTTP_ERROR', function () {
    $this->http->fake(['peaksupps.de/*' => Factory::response('', 302, ['Location' => 'https://peaksupps.de/loop.xml'])]);

    $exception = fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination)));

    expect($exception->errorCode)->toBe(FeedErrorCode::HttpError)
        ->and($exception->params)->toBe(['status' => 302]);
    $this->http->assertSentCount(4);
});

it('aborts at the byte cap with PAYLOAD_TOO_LARGE and removes the partial file', function () {
    $this->http->fake(['peaksupps.de/*' => Factory::response(str_repeat('x', 4096), 200, ['Content-Type' => 'text/csv'])]);

    $exception = fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination, maxBytes: 1000)));

    expect($exception->errorCode)->toBe(FeedErrorCode::PayloadTooLarge)
        ->and(file_exists($this->destination))->toBeFalse();
});

it('gunzips gzip payloads, hashing and capping the decoded bytes', function () {
    $csv = "sku;title\nPEA-186;Whey Isolate 90\n";
    $this->http->fake(['peaksupps.de/*' => Factory::response(gzencode($csv), 200, ['Content-Type' => 'application/gzip'])]);

    $result = $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination));

    expect(file_get_contents($this->destination))->toBe($csv)
        ->and($result->sha256)->toBe(hash('sha256', $csv))
        ->and($result->bytes)->toBe(strlen($csv))
        ->and($result->decompressed)->toBeTrue();
});

it('refuses a gzip bomb once the decoded size passes the cap', function () {
    $this->http->fake(['peaksupps.de/*' => Factory::response(gzencode(str_repeat('a', 5 * 1024 * 1024)), 200, ['Content-Encoding' => 'gzip', 'Content-Type' => 'text/csv'])]);

    $exception = fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination, maxBytes: 1024 * 1024)));

    expect($exception->errorCode)->toBe(FeedErrorCode::PayloadTooLarge)
        ->and($exception->params)->toBe(['limit_mb' => 1])
        ->and(file_exists($this->destination))->toBeFalse();
});

it('reports a corrupt gzip payload as PARSER_ERROR', function () {
    $this->http->fake(['peaksupps.de/*' => Factory::response("\x1F\x8B\x08\x00corrupt", 200, ['Content-Type' => 'application/gzip'])]);

    $exception = fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination)));

    expect($exception->errorCode)->toBe(FeedErrorCode::ParserError)
        ->and($exception->params)->toBe(['format' => 'gzip'])
        ->and(file_exists($this->destination))->toBeFalse();
});

it('refuses content types outside the allow-list', function (string $contentType) {
    $this->http->fake(['peaksupps.de/*' => Factory::response('<html></html>', 200, ['Content-Type' => $contentType])]);

    $exception = fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination)));

    expect($exception->errorCode)->toBe(FeedErrorCode::UnsupportedContentType)
        ->and(file_exists($this->destination))->toBeFalse();
})->with(['text/html; charset=utf-8', 'image/png', 'application/pdf']);

it('accepts the allowed content types', function (string $contentType) {
    $this->http->fake(['peaksupps.de/*' => Factory::response(FEED_XML, 200, ['Content-Type' => $contentType])]);

    expect($this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination))->status)->toBe(200);
})->with(['application/xml', 'text/xml', 'application/rss+xml', 'text/csv', 'text/plain; charset=windows-1250', 'application/json', 'application/octet-stream', 'application/x-gzip']);

it('sends credentials to the feed origin', function (FeedCredentials $credentials, string $header, string $value) {
    $this->http->fake(['peaksupps.de/*' => Factory::response(FEED_XML, 200, ['Content-Type' => 'application/xml'])]);

    $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination, $credentials));

    $this->http->assertSent(fn (Request $request) => $request->hasHeader($header, $value));
})->with([
    'basic' => [FeedCredentials::basic('peaksupps', 'hunter2'), 'Authorization', 'Basic '.base64_encode('peaksupps:hunter2')],
    'bearer' => [FeedCredentials::fromArray(['type' => 'bearer', 'token' => 'tok-123']), 'Authorization', 'Bearer tok-123'],
    'header' => [FeedCredentials::header('X-Feed-Key', 'k-456'), 'X-Feed-Key', 'k-456'],
]);

it('does not forward credentials to another origin after a redirect', function () {
    $this->http->fake([
        'https://peaksupps.de/*' => Factory::response('', 302, ['Location' => 'https://mirror.example.com/final.xml']),
        'https://mirror.example.com/*' => Factory::response(FEED_XML, 200, ['Content-Type' => 'application/xml']),
    ]);

    $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination, FeedCredentials::bearer('tok-123')));

    $this->http->assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://peaksupps.de') && $request->hasHeader('Authorization'));
    $this->http->assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://mirror.example.com') && $request->hasHeader('Authorization'));
});

it('maps HTTP failures to feed error codes', function (int $status, FeedErrorCode $code, array $params) {
    $this->http->fake(['peaksupps.de/*' => Factory::response('nope', $status)]);

    $exception = fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination)));

    expect($exception->errorCode)->toBe($code)
        ->and($exception->params)->toBe($params)
        ->and(file_exists($this->destination))->toBeFalse();
})->with([
    [401, FeedErrorCode::AuthFailed, []],
    [403, FeedErrorCode::AuthFailed, []],
    [404, FeedErrorCode::HttpError, ['status' => 404]],
    [500, FeedErrorCode::HttpError, ['status' => 500]],
    [304, FeedErrorCode::HttpError, ['status' => 304]],
]);

it('maps connection failures and timeouts', function (?string $message, FeedErrorCode $code) {
    $this->http->fake(['peaksupps.de/*' => Factory::failedConnection($message)]);

    expect(fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination)))->errorCode)->toBe($code);
})->with([
    'refused' => ['cURL error 7: Failed to connect', FeedErrorCode::UnreachableUrl],
    'timeout' => ['cURL error 28: Operation timed out after 120001 milliseconds', FeedErrorCode::FetchTimeout],
]);

it('never leaks the URL, token, address or credentials in exceptions', function () {
    $this->http->fake(['peaksupps.de/*' => Factory::failedConnection('cURL error 7: Failed to connect to peaksupps.de port 443 (93.184.216.34)')]);

    $exception = fetchFailure(fn () => $this->fetcher->fetch(new FeedFetchRequest(FEED_URL, $this->destination, FeedCredentials::basic('peaksupps', 'hunter2'))));
    $dump = print_r(new FeedFetchRequest(FEED_URL, $this->destination, FeedCredentials::basic('peaksupps', 'hunter2')), true);

    expect($exception->getMessage())->toBe('Feed fetch failed: UNREACHABLE_URL.')
        ->and($exception->getPrevious())->toBeNull()
        ->and($dump)->not->toContain('hunter2')
        ->and($dump)->not->toContain('s3cret-token');
});

it('validates credential input', function (Closure $build) {
    $build();
})->with([
    'forbidden header' => fn () => FeedCredentials::header('Host', 'evil.example'),
    'header injection' => fn () => FeedCredentials::header('X-Key', "a\r\nX-Other: b"),
    'empty bearer' => fn () => FeedCredentials::bearer(' '),
    'unknown type' => fn () => FeedCredentials::fromArray(['type' => 'oauth']),
    'missing password' => fn () => FeedCredentials::fromArray(['type' => 'basic', 'username' => 'u']),
])->throws(InvalidArgumentException::class);
