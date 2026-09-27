<?php

namespace App\Http\Presenters;

use App\Domain\Shared\Money;
use App\Models\Product;
use App\Models\RatingAggregate;

/**
 * Server-owned SEO head (title, description, canonical, robots, hreflang,
 * OpenGraph, JSON-LD). Rendered by Blade on the first response and taken over
 * by Inertia <Head> on the client (same head-keys).
 */
final class SeoPresenter
{
    private const string BRAND = 'Comparo';

    /**
     * @param  list<array<string, mixed>>  $jsonLd
     * @return array{title: string, description: string, canonical: string, robots: string, alternates: list<array{hreflang: string, href: string}>, openGraph: array<string, string>, jsonLd: list<array<string, mixed>>}
     */
    public function page(string $title, string $description, string $canonical, array $jsonLd = [], string $robots = 'index,follow'): array
    {
        $fullTitle = str_contains($title, self::BRAND) ? $title : "{$title} · ".self::BRAND;

        return [
            'title' => $fullTitle,
            'description' => mb_strimwidth($description, 0, 160, '…'),
            'canonical' => $canonical,
            'robots' => $robots,
            // Market-specific hreflang arrives with the international SEO phase.
            'alternates' => [['hreflang' => 'x-default', 'href' => $canonical]],
            'openGraph' => [
                'title' => $fullTitle,
                'description' => mb_strimwidth($description, 0, 200, '…'),
                'type' => 'website',
                'url' => $canonical,
                'site_name' => 'Comparo Performance',
            ],
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * @param  array{offers: list<array<string, mixed>>, offerSummary: array<string, mixed>, compliance: array<string, mixed>}  $offers
     * @return array<string, mixed>
     */
    public function product(Product $product, array $offers): array
    {
        $canonical = route('products.show', $product->slug);
        $description = $product->short_description
            ?? "Compare {$product->name} by {$product->brand->name}: total landed price with shipping, working coupons and shop trust.";

        $productLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'brand' => ['@type' => 'Brand', 'name' => $product->brand->name],
            'category' => $product->category->name,
            'description' => $description,
            'sku' => $product->reference,
            'url' => $canonical,
            'offers' => $this->aggregateOffer($offers),
            'aggregateRating' => $this->aggregateRating($product),
        ], static fn (mixed $value): bool => $value !== null);

        return $this->page(
            title: "{$product->name} by {$product->brand->name} — compare prices",
            description: $description,
            canonical: $canonical,
            jsonLd: [$productLd, $this->breadcrumbs([
                ['Home', route('home')],
                [$product->category->name, route('categories.show', $product->category->slug)],
                [$product->name, $canonical],
            ])],
        );
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(static fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item[0],
                'item' => $item[1],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * A-31/A-37: only a real rating (aggregated from approved reviews, never
     * an imported demo or manual one) with at least the minimum number of
     * reviews is published as structured data.
     *
     * @return array{'@type': string, ratingValue: string, reviewCount: int}|null
     */
    private function aggregateRating(Product $product): ?array
    {
        if ($product->rating_source !== RatingAggregate::SOURCE_AGGREGATED
            || $product->weighted_rating === null
            || $product->rating_count < (int) config('comparo.thresholds.rating_min_reviews')) {
            return null;
        }

        return [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) $product->weighted_rating,
            'reviewCount' => $product->rating_count,
        ];
    }

    /**
     * Offers are only described when the product is purchasable in the market.
     *
     * @param  array{offers: list<array<string, mixed>>, compliance: array<string, mixed>}  $offers
     * @return array<string, mixed>|null
     */
    private function aggregateOffer(array $offers): ?array
    {
        if (! ($offers['compliance']['purchasable'] ?? false) || $offers['offers'] === []) {
            return null;
        }

        $totals = array_map(static fn (array $offer): Money => Money::of($offer['price']['total']['minor'], $offer['price']['total']['currency']), $offers['offers']);
        usort($totals, static fn (Money $a, Money $b): int => $a->minor <=> $b->minor);

        return [
            '@type' => 'AggregateOffer',
            'priceCurrency' => $totals[0]->currency,
            'lowPrice' => MoneyPresenter::decimal($totals[0]),
            'highPrice' => MoneyPresenter::decimal($totals[count($totals) - 1]),
            'offerCount' => count($totals),
        ];
    }
}
