<?php

namespace App\Nova;

use App\Models\Product as ProductModel;
use App\Models\ProductVariant as ProductVariantModel;
use App\Nova\Actions\GenerateVariants;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Jacobfitzp\NovaCanvas\Canvas;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ultrasimplified\ImageCropper\ImageCropper;

/**
 * Something the shop sells.
 *
 * A print or a record is sold as it comes, so its price and stock are set
 * right here and kept on a single standard variant behind the scenes. A
 * t-shirt comes in colours and sizes, so once it has any of those its
 * prices and stock are set on its variants instead, and these two fields
 * step aside.
 */
class Product extends Resource
{
    /**
     * @var class-string<ProductModel>
     */
    public static $model = ProductModel::class;

    /**
     * @var string
     */
    public static $title = 'name';

    /**
     * @var array
     */
    public static $search = [
        'name',
    ];

    /**
     * @var array<string, string>
     */
    public static $sort = [
        'position' => 'asc',
    ];

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withMin('variants', 'price')->withMax('variants', 'price');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Name')->sortable()->rules('required', 'max:255'),
            Slug::make('Slug')
                ->from('Name')
                ->rules('required', 'max:255', 'alpha_dash')
                ->creationRules('unique:products,slug')
                ->updateRules('unique:products,slug,{{resourceId}}')
                ->hideFromIndex()
                ->help('The product\'s address: /shop/category/this-slug.'),
            BelongsTo::make('Category', 'shopCategory', ShopCategory::class)
                ->nullable()
                ->sortable()
                ->rules('nullable', 'required_if_accepted:is_published'),
            BelongsTo::make('Postage band', 'postageBand', PostageBand::class)
                ->nullable()
                ->hideFromIndex()
                ->rules('nullable', 'required_if_accepted:is_published')
                ->help('What it costs to post one. Needed before it can be published.'),
            ImageCropper::make('Image')->resize(1600)->convert('webp')->disk('do')->indexWidth(120)->prunable()->nullable()
                ->help('The main picture. More, including a picture per colour, go under Pictures below.'),
            Text::make('Summary')->nullable()->hideFromIndex()->rules('nullable', 'max:255')
                ->help('One line for the product\'s card in the shop.'),
            Canvas::make('Description')->nullable()->hideFromIndex(),

            Text::make('Price', fn (): ?string => $this->priceRange())->onlyOnIndex(),
            $this->standardPrice(),
            $this->standardSalePrice(),
            $this->standardStock(),

            Number::make('Position')->min(0)->step(1)->default(0)->rules('required', 'integer', 'min:0')->hideFromIndex(),
            Boolean::make('Published', 'is_published')->sortable(),

            HasMany::make('Colours and sizes', 'variants', ProductVariant::class),
            HasMany::make('Pictures', 'images', ProductImage::class),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            GenerateVariants::make()->showInline(),
        ];
    }

    /**
     * The price of a product sold as it comes, kept on its standard variant.
     */
    private function standardPrice(): Currency
    {
        return Currency::make('Price', 'standard_price')
            ->currency('GBP')
            ->onlyOnForms()
            ->nullable()
            ->rules('nullable', 'numeric', 'min:0')
            ->canSee(fn (): bool => $this->sellsAsItComes())
            ->resolveUsing(function (): ?float {
                $price = $this->standardVariant()?->price;

                return $price === null ? null : $price / 100;
            })
            ->fillUsing(function (NovaRequest $request, ProductModel $product, string $attribute, string $requestAttribute): ?callable {
                $value = $request->input($requestAttribute);

                if (! is_numeric($value)) {
                    return null;
                }

                $pence = (int) round((float) $value * 100);

                return function () use ($product, $pence): void {
                    $variant = $product->variants()->firstOrNew(['colour' => null, 'size' => null]);
                    $variant->price = $pence;
                    $variant->save();
                };
            })
            ->help('For something sold as it comes. Give it colours and sizes below and their prices are set there instead.');
    }

    /**
     * The lower price a product sold as it comes is on sale for. Left
     * empty, or not below the price, it is not on sale.
     */
    private function standardSalePrice(): Currency
    {
        return Currency::make('Sale price', 'standard_sale_price')
            ->currency('GBP')
            ->onlyOnForms()
            ->nullable()
            ->rules('nullable', 'numeric', 'min:0')
            ->canSee(fn (): bool => $this->sellsAsItComes())
            ->resolveUsing(function (): ?float {
                $salePrice = $this->standardVariant()?->sale_price;

                return $salePrice === null ? null : $salePrice / 100;
            })
            ->fillUsing(function (NovaRequest $request, ProductModel $product, string $attribute, string $requestAttribute): callable {
                $value = $request->input($requestAttribute);
                $pence = is_numeric($value) ? (int) round((float) $value * 100) : null;

                return function () use ($product, $pence): void {
                    $product->variants()
                        ->whereNull('colour')
                        ->whereNull('size')
                        ->update(['sale_price' => $pence]);
                };
            })
            ->help('To put it on sale: the lower price it sells for, with the price shown struck through beside it. Leave empty when it is not on sale.');
    }

    /**
     * The stock of a product sold as it comes. Left empty, there is no limit.
     */
    private function standardStock(): Number
    {
        return Number::make('Stock', 'standard_stock')
            ->onlyOnForms()
            ->nullable()
            ->min(0)
            ->step(1)
            ->rules('nullable', 'integer', 'min:0')
            ->canSee(fn (): bool => $this->sellsAsItComes())
            ->resolveUsing(fn (): ?int => $this->standardVariant()?->stock)
            ->fillUsing(function (NovaRequest $request, ProductModel $product, string $attribute, string $requestAttribute): callable {
                $stock = $request->input($requestAttribute);
                $stock = is_numeric($stock) ? (int) $stock : null;

                return function () use ($product, $stock): void {
                    $product->variants()
                        ->whereNull('colour')
                        ->whereNull('size')
                        ->update(['stock' => $stock]);
                };
            })
            ->help('How many are left. Leave empty if there is no limit.');
    }

    /**
     * Whether this product is sold as it comes, with no colours or sizes:
     * true of a new one, and of one whose only variant is standard.
     */
    private function sellsAsItComes(): bool
    {
        if (! $this->resource->exists) {
            return true;
        }

        $variants = $this->resource->variants;

        return $variants->count() <= 1
            && $variants->every(fn (ProductVariantModel $variant): bool => blank($variant->colour) && blank($variant->size));
    }

    /**
     * The single standard variant, if there is one.
     */
    private function standardVariant(): ?ProductVariantModel
    {
        if (! $this->resource->exists) {
            return null;
        }

        return $this->resource->variants->first(
            fn (ProductVariantModel $variant): bool => blank($variant->colour) && blank($variant->size),
        );
    }

    /**
     * "£25.00", or "£20.00 – £25.00" when its variants differ.
     */
    private function priceRange(): ?string
    {
        $lowest = $this->resource->variants_min_price;
        $highest = $this->resource->variants_max_price;

        if ($lowest === null) {
            return null;
        }

        $format = fn (int $pence): string => '£'.number_format($pence / 100, 2);

        return $lowest === $highest
            ? $format((int) $lowest)
            : $format((int) $lowest).' – '.$format((int) $highest);
    }
}
