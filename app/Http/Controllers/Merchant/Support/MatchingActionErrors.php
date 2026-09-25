<?php

namespace App\Http\Controllers\Merchant\Support;

use App\Domain\Matching\Exceptions\ListingOutsideMerchantScope;
use App\Domain\Matching\Exceptions\MatchDecisionNotAllowed;
use App\Domain\Matching\Exceptions\NoActiveMatchingPolicy;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns a refused merchant matching action into a friendly validation error
 * under `decision` (never a 500). The domain's own scope guard is defence in
 * depth behind the scoped lookup and answers 404 like any foreign id.
 */
final class MatchingActionErrors
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
        } catch (ListingOutsideMerchantScope) {
            throw new NotFoundHttpException;
        } catch (NoActiveMatchingPolicy) {
            throw ValidationException::withMessages([self::KEY => 'Matching is temporarily unavailable. Try again later.']);
        } catch (MatchDecisionNotAllowed $exception) {
            throw ValidationException::withMessages([self::KEY => self::message($exception)]);
        }
    }

    private static function message(MatchDecisionNotAllowed $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'no suggested product') => 'There is no pending suggestion to confirm any more. Reload the page.',
            str_contains($message, 'nothing to reject'), str_contains($message, 'no product or suggestion') => 'Nothing is linked or suggested to reject.',
            str_contains($message, 'relinking is a staff rematch') => 'This listing is already linked to another product. Contact Comparo support to move it.',
            str_contains($message, 'not an active canonical product') => 'Choose an active catalogue product.',
            str_contains($message, 'cannot be proposed') => 'Only unmatched or suggested listings can be proposed as a new product.',
            str_contains($message, 'no title') => 'This listing has no title to propose a product from. Add a title in your feed.',
            default => 'This action is not possible in the listing’s current state. Reload the page.',
        };
    }
}
