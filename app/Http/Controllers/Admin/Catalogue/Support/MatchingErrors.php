<?php

namespace App\Http\Controllers\Admin\Catalogue\Support;

use App\Domain\Matching\Exceptions\ListingOutsideMerchantScope;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\Exceptions\NoActiveMatchingPolicy;
use Illuminate\Validation\ValidationException;

/**
 * Turns a refused matching action into a validation-style error under the
 * `decision` key (Inertia shows it next to the form), never a 500. Nothing
 * was written when these exceptions are thrown.
 */
final class MatchingErrors
{
    public const string KEY = 'decision';

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $action
     * @return TResult
     *
     * @throws ValidationException
     */
    public static function guard(callable $action): mixed
    {
        try {
            return $action();
        } catch (MatchDecisionNotAllowed|ListingOutsideMerchantScope|NoActiveMatchingPolicy $exception) {
            throw ValidationException::withMessages([self::KEY => $exception->getMessage()]);
        }
    }
}
