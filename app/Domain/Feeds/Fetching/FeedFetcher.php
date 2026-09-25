<?php

namespace App\Domain\Feeds\Fetching;

use App\Domain\Feeds\FeedErrorCode;
use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Throwable;

/**
 * SSRF-guarded feed download (§8).
 *
 * Every hop: vet the URL, resolve all addresses and refuse if ANY is not
 * public, pin the connection to the vetted address (CURLOPT_RESOLVE, so DNS
 * rebinding cannot swap it), send without automatic redirects, then follow at
 * most `maxRedirects` Location headers manually (https→http refused).
 * Credentials go only to the original origin. The body is streamed to disk
 * with a byte cap and sha256 ({@see PayloadWriter}); environment proxies are
 * bypassed so the pinned address is the one actually connected to.
 */
final class FeedFetcher
{
    /** @var list<string> */
    private const array ALLOWED_MEDIA_TYPES = [
        'application/xml', 'text/xml', 'application/rss+xml', 'application/atom+xml',
        'text/csv', 'application/csv', 'text/comma-separated-values', 'text/tab-separated-values',
        'application/vnd.ms-excel', // commonly (mis)used by web servers for .csv
        'text/plain', 'application/json', 'application/octet-stream', 'binary/octet-stream',
        'application/gzip', 'application/x-gzip',
    ];

    private const string USER_AGENT = 'ComparoFeedFetcher/1.0 (+https://comparo.example/merchants/feeds)';

    public function __construct(
        private readonly Factory $http,
        private readonly HostResolver $resolver,
        private readonly DestinationGuard $guard = new DestinationGuard,
        private readonly PayloadWriter $writer = new PayloadWriter,
    ) {}

    /**
     * @throws FeedFetchException
     */
    public function fetch(FeedFetchRequest $request): FetchResult
    {
        $destination = $this->guard->parse($request->url);
        $origin = $destination->origin();

        for ($redirects = 0; ; $redirects++) {
            $response = $this->send($destination, $request, $destination->origin() === $origin);
            $status = $response->status();

            if ($status < 300 || $status >= 400) {
                return $this->store($response, $request, $redirects);
            }

            $location = $response->header('Location');

            if ($location === '' || $redirects >= $request->maxRedirects) {
                throw new FeedFetchException(FeedErrorCode::HttpError, ['status' => $status]);
            }

            $destination = $this->redirectTarget($destination, $location);
        }
    }

    private function redirectTarget(Destination $current, string $location): Destination
    {
        try {
            $next = $this->guard->parse((string) UriResolver::resolve(new Uri($current->url), new Uri($location)));
        } catch (InvalidArgumentException) {
            throw new FeedFetchException(FeedErrorCode::BlockedDestination);
        }

        if ($current->scheme === 'https' && $next->scheme === 'http') {
            throw new FeedFetchException(FeedErrorCode::BlockedDestination);
        }

        return $next;
    }

    /**
     * Resolve, vet and pin one hop, then send it.
     */
    private function send(Destination $destination, FeedFetchRequest $request, bool $withCredentials): Response
    {
        $addresses = $destination->literalAddress !== null
            ? [$destination->literalAddress]
            : $this->resolver->resolve($destination->host);

        $this->guard->assertPublic($addresses);

        $tooLarge = false;
        $pending = $this->http
            ->withOptions($this->transferOptions($destination, $addresses[0], $request->maxBytes, $tooLarge))
            ->timeout($request->timeoutSeconds)
            ->connectTimeout($request->connectTimeoutSeconds)
            ->withoutRedirecting()
            ->withUserAgent(self::USER_AGENT)
            ->accept('application/xml, text/xml, text/csv, application/json, text/plain;q=0.9, */*;q=0.5');

        if ($withCredentials && $request->credentials !== null) {
            $pending = $request->credentials->applyTo($pending);
        }

        try {
            return $pending->get($destination->url);
        } catch (ConnectionException|RequestException $exception) {
            throw $this->transferFailure($exception, $tooLarge, $request);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transferOptions(Destination $destination, string $pinnedAddress, int $maxBytes, bool &$tooLarge): array
    {
        $options = [
            'decode_content' => false, // no Accept-Encoding; gzip is decoded (and capped) by PayloadWriter
            'http_errors' => false,
            'proxy' => ['no' => ['*']],
            // Abort as soon as the announced or received size exceeds the cap.
            'progress' => static function (int $downloadTotal, int $downloaded) use ($maxBytes, &$tooLarge): bool {
                $tooLarge = $downloadTotal > $maxBytes || $downloaded > $maxBytes;

                return $tooLarge;
            },
        ];

        if ($destination->literalAddress === null) {
            $options['curl'] = [CURLOPT_RESOLVE => [$destination->resolveEntry($pinnedAddress)]];
        }

        return $options;
    }

    private function transferFailure(Throwable $exception, bool $tooLarge, FeedFetchRequest $request): FeedFetchException
    {
        if ($tooLarge) {
            return new FeedFetchException(FeedErrorCode::PayloadTooLarge, ['limit_mb' => max(1, intdiv($request->maxBytes, 1024 * 1024))]);
        }

        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof ConnectTimeoutException || $cause instanceof NetworkTimeoutException || $cause instanceof ResponseTimeoutException
                || str_contains($cause->getMessage(), 'cURL error 28')) {
                return new FeedFetchException(FeedErrorCode::FetchTimeout, ['seconds' => $request->timeoutSeconds]);
            }
        }

        if ($exception instanceof RequestException) {
            return $this->statusFailure($exception->response->status()) ?? new FeedFetchException(FeedErrorCode::UnreachableUrl);
        }

        return new FeedFetchException(FeedErrorCode::UnreachableUrl);
    }

    private function statusFailure(int $status): ?FeedFetchException
    {
        return match (true) {
            $status === 401, $status === 403 => new FeedFetchException(FeedErrorCode::AuthFailed),
            $status >= 400, $status < 200 => new FeedFetchException(FeedErrorCode::HttpError, ['status' => $status]),
            default => null,
        };
    }

    private function store(Response $response, FeedFetchRequest $request, int $redirects): FetchResult
    {
        $failure = $this->statusFailure($response->status());

        if ($failure !== null) {
            throw $failure;
        }

        $mediaType = $this->mediaType($response->header('Content-Type'));

        if ($mediaType !== null && ! $this->isAllowedMediaType($mediaType)) {
            throw new FeedFetchException(FeedErrorCode::UnsupportedContentType, ['content_type' => mb_substr($mediaType, 0, 100)]);
        }

        $written = $this->writer->write($response->toPsrResponse()->getBody(), $request->destinationPath, $request->maxBytes);

        return new FetchResult(
            path: $request->destinationPath,
            bytes: $written['bytes'],
            sha256: $written['sha256'],
            contentType: $mediaType,
            status: $response->status(),
            redirects: $redirects,
            decompressed: $written['decompressed'],
        );
    }

    private function mediaType(string $contentType): ?string
    {
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $mediaType === '' ? null : $mediaType;
    }

    private function isAllowedMediaType(string $mediaType): bool
    {
        return in_array($mediaType, self::ALLOWED_MEDIA_TYPES, true)
            || str_ends_with($mediaType, '+xml')
            || str_ends_with($mediaType, '+json');
    }
}
