<?php

namespace App\Domain\Platform\Queues;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;

/**
 * The queue connection long-running jobs (feed import, matching, pricing)
 * dispatch onto (`comparo.queues.long_running_connection`).
 *
 * A worker re-delivers a job still running after the connection's
 * `retry_after`, so that value must exceed the longest job timeout (the
 * feed parser runs up to 900 s; the default connections retry after 90 s).
 * {@see self::guard()} runs at boot: a misconfiguration is logged as
 * critical, and throws in local/testing so it is fixed before it ships.
 */
final class LongRunningQueue
{
    /**
     * Default long-running connection per QUEUE_CONNECTION: its dedicated
     * `-long` variant (config/queue.php, retry_after QUEUE_LONG_RETRY_AFTER).
     *
     * @var array<string, string>
     */
    public const array DEFAULT_CONNECTIONS = [
        'sync' => 'sync',
        'database' => 'database-long',
        'redis' => 'redis-long',
    ];

    /** Drivers that never re-deliver a running job. */
    private const array INLINE_DRIVERS = ['sync', 'null', 'deferred', 'background'];

    public function __construct(private readonly Repository $config) {}

    /**
     * The config default when QUEUE_LONG_CONNECTION is not set; any other
     * connection keeps its own name (and must then be long-running itself).
     */
    public static function defaultConnectionFor(string $queueConnection): string
    {
        return self::DEFAULT_CONNECTIONS[$queueConnection] ?? $queueConnection;
    }

    public function connection(): string
    {
        return (string) $this->config->get('comparo.queues.long_running_connection');
    }

    /**
     * Why the long-running connection is unsafe for jobs running up to
     * `$longestJobTimeout` seconds, or null when it is safe.
     */
    public function misconfiguration(int $longestJobTimeout): ?string
    {
        $name = $this->connection();
        $settings = $this->config->get("queue.connections.{$name}");

        if (! is_array($settings)) {
            return "The long-running queue connection [{$name}] is not configured.";
        }

        if (in_array($settings['driver'] ?? null, self::INLINE_DRIVERS, true)) {
            return null;
        }

        $retryAfter = $settings['retry_after'] ?? null;

        if (! is_numeric($retryAfter) || (int) $retryAfter <= $longestJobTimeout) {
            return "The long-running queue connection [{$name}] must set retry_after above {$longestJobTimeout} seconds (the longest feed job timeout); it is [".(is_numeric($retryAfter) ? (int) $retryAfter : 'unset').'].';
        }

        return null;
    }

    /**
     * Log a misconfiguration as critical; with `$strict` also throw.
     *
     * @throws LongRunningQueueMisconfigured when strict and misconfigured
     */
    public function guard(int $longestJobTimeout, bool $strict): void
    {
        $problem = $this->misconfiguration($longestJobTimeout);

        if ($problem === null) {
            return;
        }

        Log::critical($problem, ['connection' => $this->connection(), 'longest_job_timeout' => $longestJobTimeout]);

        if ($strict) {
            throw new LongRunningQueueMisconfigured($problem);
        }
    }
}
