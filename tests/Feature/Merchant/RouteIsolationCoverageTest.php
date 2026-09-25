<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Merchant\MerchantRouteMap;

it('covers every merchant.* route in the isolation suite', function () {
    $registered = collect(Route::getRoutes()->getRoutes())
        ->map(static fn (RoutingRoute $route): ?string => $route->getName())
        ->filter(static fn (?string $name): bool => $name !== null && str_starts_with($name, 'merchant.'))
        ->values()
        ->all();

    $missing = array_values(array_diff($registered, array_keys(MerchantRouteMap::ROUTES)));
    $stale = array_values(array_diff(array_keys(MerchantRouteMap::ROUTES), $registered));

    expect($missing)->toBe([], 'Add these routes to MerchantRouteMap (and so to the isolation suite): '.implode(', ', $missing))
        ->and($stale)->toBe([], 'These isolation entries no longer exist as routes: '.implode(', ', $stale));
});

it('declares the parameters each tenant route really takes', function () {
    foreach (MerchantRouteMap::ROUTES as $name => $definition) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route?->parameterNames())->toBe($definition['ids'], "Route {$name} parameters differ from the isolation map.")
            ->and(in_array(strtoupper($definition['method']), $route?->methods() ?? [], true))->toBeTrue("Route {$name} method differs.");
    }
});

it('never binds tenant models implicitly and runs every route behind the merchant middleware stack', function () {
    foreach (array_keys(MerchantRouteMap::ROUTES) as $name) {
        $route = Route::getRoutes()->getByName($name);
        $middleware = $route?->gatherMiddleware() ?? [];

        expect($middleware)->toContain('auth', 'verified', 'feature:merchant-feeds', 'merchant.context');

        foreach ($route?->signatureParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $class = $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null;

            expect($class === null || ! is_subclass_of($class, Model::class))
                ->toBeTrue("Route {$name} binds the model {$class} implicitly.");
        }
    }
});

it('throttles every merchant mutation', function () {
    foreach (MerchantRouteMap::ROUTES as $name => $definition) {
        if ($definition['method'] === 'get') {
            continue;
        }

        $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];

        expect(collect($middleware)->contains(static fn (mixed $item): bool => is_string($item) && str_starts_with($item, 'throttle:')))
            ->toBeTrue("Route {$name} is not throttled.");
    }
});
