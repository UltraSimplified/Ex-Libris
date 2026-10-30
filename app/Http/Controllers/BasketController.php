<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\AddToBasketRequest;
use App\Http\Requests\Shop\ChooseFulfilmentRequest;
use App\Http\Requests\Shop\UpdateBasketLineRequest;
use App\Models\ProductVariant;
use App\Models\ShopSettings;
use App\Services\Shop\Basket;
use App\Support\Shop\BasketLine;
use App\Support\Shop\Fulfilment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The basket: what is in it, how many, and whether it is posted or
 * collected, with the postage worked out before the customer goes to pay.
 */
class BasketController extends Controller
{
    public function __construct(private Basket $basket) {}

    /**
     * The basket with its totals.
     */
    public function show(): Response
    {
        $lines = $this->basket->lines();
        $fulfilment = $this->basket->fulfilment();
        $postage = Basket::postage($lines, $fulfilment);
        $subtotal = Basket::subtotal($lines);
        $settings = ShopSettings::current();

        return Inertia::render('shop/Basket', [
            'title' => 'Basket',
            'lines' => $lines->map(fn (BasketLine $line): array => [
                'variant_id' => $line->variant->id,
                'product' => $line->variant->product->name,
                'product_slug' => $line->variant->product->slug,
                'category_slug' => $line->variant->product->shopCategory->slug,
                'variant' => $line->variant->displayName(),
                'image' => $line->variant->product->image,
                'unit_price' => $line->variant->currentPrice(),
                'struck_price' => $line->variant->struckPrice(),
                'quantity' => $line->quantity,
                'subtotal' => $line->subtotal(),
                'available' => $line->isAvailable(),
                'max_quantity' => min(Basket::MAX_PER_LINE, $line->variant->stock ?? Basket::MAX_PER_LINE),
            ]),
            'fulfilment' => $fulfilment->value,
            'fulfilmentOptions' => collect(Fulfilment::cases())->map(fn (Fulfilment $option): array => [
                'value' => $option->value,
                'label' => $option->label(),
            ]),
            'subtotal' => $subtotal,
            'postage' => $postage->toArray(),
            'total' => $subtotal + $postage->amount,
            'postageCap' => $settings->postage_cap,
            'collection' => [
                'name' => $settings->collection_name,
                'address' => $settings->collectionAddressLines(),
                'hours' => $settings->collection_hours,
            ],
        ]);
    }

    /**
     * Put some of a variant in the basket.
     */
    public function store(AddToBasketRequest $request): RedirectResponse
    {
        $variant = ProductVariant::query()->findOrFail($request->integer('variant_id'));

        if (! Basket::canHold($variant)) {
            throw ValidationException::withMessages([
                'variant_id' => 'Sorry, that has sold out.',
            ]);
        }

        $this->basket->add($variant, $request->integer('quantity'));

        return to_route('basket.show');
    }

    /**
     * Change how many of a variant the basket holds.
     */
    public function update(UpdateBasketLineRequest $request, ProductVariant $variant): RedirectResponse
    {
        $this->basket->put($variant, $request->integer('quantity'));

        return to_route('basket.show');
    }

    /**
     * Take a variant out of the basket.
     */
    public function destroy(ProductVariant $variant): RedirectResponse
    {
        $this->basket->remove($variant);

        return to_route('basket.show');
    }

    /**
     * Choose between posting and collecting.
     */
    public function fulfilment(ChooseFulfilmentRequest $request): RedirectResponse
    {
        $this->basket->setFulfilment($request->enum('fulfilment', Fulfilment::class));

        return to_route('basket.show');
    }
}
