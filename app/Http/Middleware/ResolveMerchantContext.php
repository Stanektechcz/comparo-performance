<?php

namespace App\Http\Middleware;

use App\Domain\Merchants\MerchantContext;
use App\Domain\Merchants\MerchantRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the request's MerchantContext: session('merchant.active_id') → the
 * user's memberships → the lowest-id membership as a fallback. A user with
 * no membership is aborted with 403 before any merchant route runs.
 */
class ResolveMerchantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        /** @var list<array{id: int, slug: string, name: string, role: MerchantRole}> $memberships */
        $memberships = $user->merchants()
            ->orderBy('merchants.id')
            ->get(['merchants.id', 'merchants.slug', 'merchants.name'])
            ->map(static fn ($merchant): array => [
                'id' => (int) $merchant->id,
                'slug' => (string) $merchant->slug,
                'name' => (string) $merchant->name,
                'role' => MerchantRole::from((string) $merchant->getRelationValue('pivot')->getAttribute('role')),
            ])
            ->values()
            ->all();

        abort_if($memberships === [], 403);

        $activeId = $request->session()->get('merchant.active_id');
        $active = collect($memberships)->firstWhere('id', $activeId) ?? $memberships[0];

        app()->instance(MerchantContext::class, new MerchantContext(
            merchantId: $active['id'],
            merchantSlug: $active['slug'],
            merchantName: $active['name'],
            role: $active['role'],
            available: $memberships,
        ));

        return $next($request);
    }
}
