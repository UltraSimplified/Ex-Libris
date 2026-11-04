<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * What a product's page tells Facebook, X, WhatsApp and search engines about
 * it. Its one-line summary if it has one, otherwise the opening of its
 * description.
 */
class ProductMeta
{
    public static function for(Product $product): PageMeta
    {
        $description = self::description($product);
        $image = $product->imageUrl();
        $url = url()->current();

        return new PageMeta(
            title: $product->name,
            description: $description,
            image: $image,
            url: $url,
            type: 'product',
            jsonLd: array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $description,
                'image' => $image,
                'url' => $url,
                'offers' => self::offers($product, $url),
            ]),
        );
    }

    private static function description(Product $product): string
    {
        $words = filled($product->summary)
            ? $product->summary
            : html_entity_decode(SitePageMeta::plain((string) $product->description), ENT_QUOTES | ENT_HTML5);

        return filled($words)
            ? Str::limit(Str::squish($words), 200)
            : "{$product->name}, from the Good on Paper shop.";
    }

    /**
     * Its price for search engines to show. Left off while checkout is closed,
     * as it would be offering something nobody can yet buy.
     *
     * @return array<string, mixed>|null
     */
    private static function offers(Product $product, string $url): ?array
    {
        if (! config('shop.checkout_enabled')) {
            return null;
        }

        $prices = $product->variants->map(fn (ProductVariant $variant): int => $variant->currentPrice());
        $format = fn (int $pence): string => number_format($pence / 100, 2, '.', '');

        $price = $prices->min() === $prices->max()
            ? ['@type' => 'Offer', 'price' => $format($prices->min())]
            : ['@type' => 'AggregateOffer', 'lowPrice' => $format($prices->min()), 'highPrice' => $format($prices->max()), 'offerCount' => $prices->count()];

        return [
            ...$price,
            'priceCurrency' => 'GBP',
            'url' => $url,
            'availability' => 'https://schema.org/'.($product->isSoldOut() ? 'OutOfStock' : 'InStock'),
        ];
    }
}
