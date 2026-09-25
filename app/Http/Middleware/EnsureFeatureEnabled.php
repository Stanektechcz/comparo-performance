<?php

namespace App\Http\Middleware;

use App\Domain\Platform\Features\Feature;
use App\Domain\Platform\Features\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Route middleware alias `feature`, usage `feature:merchant-feeds`.
 * A disabled flag behaves as if the route did not exist (404), never a 403
 * that would confirm the route exists.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureFlags $flags) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $flag = Feature::tryFrom($feature);

        if ($flag === null || ! $this->flags->enabled($flag)) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
