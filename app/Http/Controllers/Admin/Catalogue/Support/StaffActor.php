<?php

namespace App\Http\Controllers\Admin\Catalogue\Support;

use App\Domain\Matching\Actions\MatchingActor;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The authenticated staff member as a matching actor. Routes already require
 * `auth` + `staff.access` + `matching.review`; this only types the user.
 */
final class StaffActor
{
    public static function from(Request $request): MatchingActor
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new HttpException(403);
        }

        return MatchingActor::staff($user);
    }
}
