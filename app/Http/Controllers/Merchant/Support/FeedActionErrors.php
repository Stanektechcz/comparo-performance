<?php

namespace App\Http\Controllers\Merchant\Support;

use App\Domain\Feeds\Exceptions\FeedRunAlreadyActive;
use App\Domain\Feeds\Exceptions\FeedRunNotAllowed;
use App\Domain\Feeds\Exceptions\InvalidFeedTransition;
use App\Models\FeedSource;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Turns refused feed actions into friendly, merchant-facing validation errors
 * (Inertia shows them next to the form) instead of 500s. Domain messages are
 * never echoed when they could contain internals; nothing was written when
 * these exceptions are thrown.
 */
final class FeedActionErrors
{
    public const string RUN = 'run';

    public const string STATUS = 'status';

    public const string FEED = 'feed';

    private const int SECONDS_PER_MINUTE = 60;

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $action
     * @return TResult
     *
     * @throws ValidationException
     */
    public static function guardRun(FeedSource $source, callable $action, string $key = self::RUN): mixed
    {
        try {
            return $action();
        } catch (FeedRunAlreadyActive $exception) {
            throw ValidationException::withMessages([
                $key => "Run #{$exception->activeRunId} of this feed is still in progress. Wait for it to finish or cancel it first.",
            ]);
        } catch (FeedRunNotAllowed $exception) {
            throw ValidationException::withMessages([$key => self::notAllowedMessage($source, $exception)]);
        }
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $action
     * @return TResult
     *
     * @throws ValidationException
     */
    public static function guardTransition(callable $action, string $key, string $message): mixed
    {
        try {
            return $action();
        } catch (InvalidFeedTransition) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }

    /**
     * Domain guards (FeedSourceData, FeedSourceAttributes, UpdateFeedCredentials)
     * throw InvalidArgumentException with fixed messages that never contain the
     * URL or secrets; they are shown under the given key.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $action
     * @return TResult
     *
     * @throws ValidationException
     */
    public static function guardInput(callable $action, string $key = self::FEED): mixed
    {
        try {
            return $action();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$key => $exception->getMessage()]);
        }
    }

    public static function notAllowedMessage(FeedSource $source, FeedRunNotAllowed $exception): string
    {
        if ($exception->retryAfterSeconds !== null) {
            $minutes = max(1, (int) ceil($exception->retryAfterSeconds / self::SECONDS_PER_MINUTE));

            return "A manual run was started recently. You can start the next one in {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').'.';
        }

        if (! $source->status->allowsManualRun()) {
            return "This feed is {$source->status->value}. Resume it before starting a run.";
        }

        return 'Upload a feed file to run this feed.';
    }
}
