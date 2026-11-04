<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShopCategory;
use App\Models\ShopSettings;
use App\Support\ProductMeta;
use App\Support\SiteMeta;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shop as the public sees it: its shelves, what is on each, and each
 * product with its colours and sizes.
 *
 * Only what is on sale is shown. A shelf with nothing on sale is left off
 * the shop's front page, and a product that is not on sale is not found,
 * however it was reached.
 */
class ShopController extends Controller
{
    /**
     * The shelves with something on them.
     */
    public function index(): Response
    {
        $categories = ShopCategory::query()
            ->published()
            ->ordered()
            ->withCount(['products' => fn (Builder $products) => $products->onSale()])
            ->get()
            ->filter(fn (ShopCategory $category): bool => $category->products_count > 0)
            ->map(fn (ShopCategory $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image' => $category->image,
                'products_count' => $category->products_count,
            ])
            ->values();

        return Inertia::render('Shop', [
            'title' => 'Shop',
            'categories' => $categories,
            'meta' => SiteMeta::shop()->toArray(),
        ]);
    }

    /**
     * What is on one shelf.
     */
    public function category(ShopCategory $shopCategory): Response
    {
        abort_unless($shopCategory->is_published, 404);

        $products = $shopCategory->products()
            ->onSale()
            ->ordered()
            ->with('variants')
            ->get()
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'slug' => $product->slug,
                'summary' => $product->summary,
                'image' => $product->image,
                'price_from' => $product->variants->min(fn (ProductVariant $variant): int => $variant->currentPrice()),
                'price_to' => $product->variants->max(fn (ProductVariant $variant): int => $variant->currentPrice()),
                'struck_price' => $product->variants->sortBy(fn (ProductVariant $variant): int => $variant->currentPrice())->first()?->struckPrice(),
                'sold_out' => $product->isSoldOut(),
            ]);

        return Inertia::render('shop/Category', [
            'title' => $shopCategory->name,
            'category' => [
                'name' => $shopCategory->name,
                'slug' => $shopCategory->slug,
                'description' => $shopCategory->description,
            ],
            'products' => $products,
            'meta' => SiteMeta::shopCategory($shopCategory)->toArray(),
        ]);
    }

    /**
     * One product, with every colour and size it comes in.
     */
    public function product(ShopCategory $shopCategory, Product $product): Response
    {
        abort_unless(Product::onSale()->whereKey($product->id)->exists(), 404);

        $product->load(['variants', 'images', 'postageBand']);

        return Inertia::render('shop/Product', [
            'title' => $product->name,
            'category' => [
                'name' => $shopCategory->name,
                'slug' => $shopCategory->slug,
            ],
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
                'summary' => $product->summary,
                'description' => $product->description,
                'image' => $product->image,
                'images' => $product->images->map(fn (ProductImage $image): array => [
                    'id' => $image->id,
                    'path' => $image->path,
                    'colour' => $image->colour,
                    'alt_text' => $image->alt_text,
                ]),
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'colour' => $variant->colour,
                    'colour_hex' => $variant->colour_hex,
                    'size' => $variant->size,
                    'name' => $variant->displayName(),
                    'price' => $variant->currentPrice(),
                    'struck_price' => $variant->struckPrice(),
                    'in_stock' => $variant->isInStock(),
                ]),
            ],
            'delivery' => $this->delivery($product),
            'meta' => ProductMeta::for($product)->toArray(),
        ]);
    }

    /**
     * What it costs to post the product, and where it can be collected
     * free instead.
     *
     * @return array{band: string, amount: int, cap: int, collection: string}
     */
    private function delivery(Product $product): array
    {
        $settings = ShopSettings::current();

        return [
            'band' => $product->postageBand->name,
            'amount' => $product->postageBand->amount,
            'cap' => $settings->postage_cap,
            'collection' => $settings->collection_name,
        ];
    }
}
