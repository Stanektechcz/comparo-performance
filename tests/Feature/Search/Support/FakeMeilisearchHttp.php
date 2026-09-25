<?php

namespace Tests\Feature\Search\Support;

use Closure;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Meilisearch\Client;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 stand-in for a Meilisearch server: records every request and
 * answers task-creating calls with an enqueued task, task polls with
 * `succeeded` (or a configured failure), and searches with a configurable
 * handler. Lets the Meilisearch adapter be tested without a server.
 */
final class FakeMeilisearchHttp implements ClientInterface
{
    /** @var list<array{method: string, path: string, body: mixed}> */
    public array $requests = [];

    /** @var array<int, array{code: string, message: string}> task uid => failure */
    public array $failures = [];

    /** @var ?Closure(string, mixed): array<string, mixed> */
    public ?Closure $searchHandler = null;

    private int $nextTask = 1;

    public function client(): Client
    {
        $factory = new HttpFactory;

        return new Client('http://meilisearch.test', 'test-key-not-a-secret', $this, $factory, [], $factory);
    }

    /**
     * The next task created will fail with this error code.
     */
    public function failNextTask(string $code, string $message = 'failed'): void
    {
        $this->failures[$this->nextTask] = ['code' => $code, 'message' => $message];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $raw = (string) $request->getBody();
        $body = $raw === '' ? null : json_decode($raw, true);
        $this->requests[] = ['method' => $method, 'path' => $path, 'body' => $body];

        if ($method === 'GET' && preg_match('#^/tasks/(\d+)$#', $path, $matches) === 1) {
            $uid = (int) $matches[1];
            $failure = $this->failures[$uid] ?? null;

            return self::json(200, $failure === null
                ? ['uid' => $uid, 'status' => 'succeeded']
                : ['uid' => $uid, 'status' => 'failed', 'error' => $failure]);
        }

        if ($method === 'POST' && (str_ends_with($path, '/search') || $path === '/multi-search')) {
            return self::json(200, $this->searchHandler === null ? ['results' => [], 'hits' => []] : ($this->searchHandler)($path, $body));
        }

        return self::json(202, ['taskUid' => $this->nextTask++, 'status' => 'enqueued']);
    }

    /**
     * @return list<array{method: string, path: string, body: mixed}>
     */
    public function requestsTo(string $method, string $pathPattern): array
    {
        return array_values(array_filter(
            $this->requests,
            static fn (array $request): bool => $request['method'] === $method && preg_match($pathPattern, $request['path']) === 1,
        ));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }
}
