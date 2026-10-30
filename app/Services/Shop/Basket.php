<?php

namespace App\Services\Shop;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShopSettings;
use App\Support\Shop\BasketLine;
use App\Support\Shop\Fulfilment;
use App\Support\Shop\PostageQuote;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What a visitor means to buy, kept in their session so nobody has to sign
 * in to fill one.
 *
 * Only variant ids and quantities are kept. Prices, stock and whether a
 * product is still on sale are read afresh every time, so a basket can
 * never hold on to a price the office has since changed.
 */
class Basket
{
    /**
     * The most of any one thing a basket will hold.
     */
    public const MAX_PER_LINE = 10;

    private const ITEMS = 'shop.basket.items';

    private const FULFILMENT = 'shop.basket.fulfilment';

    public function __construct(private Session $session) {}

    /**
     * Add some of a variant, on top of any already in the basket.
     */
    public function add(ProductVariant $variant, int $quantity): void
    {
        $items = $this->items();

        $this->put($variant, ($items[$variant->id] ?? 0) + $quantity);
    }

    /**
     * Set how many of a variant the basket holds. Nought takes it out.
     */
    public function put(ProductVariant $variant, int $quantity): void
    {
        $items = $this->items();
        $quantity = min($quantity, self::MAX_PER_LINE, $variant->stock ?? self::MAX_PER_LINE);

        if ($quantity > 0) {
            $items[$variant->id] = $quantity;
        } else {
            unset($items[$variant->id]);
        }

        $this->session->put(self::ITEMS, $items);
    }

    /**
     * Take a variant out of the basket.
     */
    public function remove(ProductVariant $variant): void
    {
        $this->put($variant, 0);
    }

    /**
     * Empty the basket.
     */
    public function clear(): void
    {
        $this->session->forget([self::ITEMS, self::FULFILMENT]);
    }

    /**
     * How many things are in the basket, counting each one.
     */
    public function count(): int
    {
        return array_sum($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    /**
     * How the order will reach the customer. Posted, until they choose
     * otherwise.
     */
    public function fulfilment(): Fulfilment
    {
        return Fulfilment::tryFrom((string) $this->session->get(self::FULFILMENT)) ?? Fulfilment::Post;
    }

    public function setFulfilment(Fulfilment $fulfilment): void
    {
        $this->session->put(self::FULFILMENT, $fulfilment->value);
    }

    /**
     * The basket's lines, in the order things were added.
     *
     * Anything no longer on sale - unpublished, deleted, moved off the
     * shelves - is dropped, from the session as well, so it cannot be
     * checked out. Something sold out is kept and marked, so the customer
     * can see what happened to it.
     *
     * @return Collection<int, BasketLine>
     */
    public function lines(): Collection
    {
        $items = $this->items();

        if ($items === []) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->whereKey(array_keys($items))
            ->whereHas('product', fn (Builder $product) => $product->onSale())
            ->with(['product.postageBand', 'product.shopCategory'])
            ->get()
            ->keyBy('id');

        $kept = array_intersect_key($items, $variants->all());

        if (count($kept) !== count($items)) {
            $this->session->put(self::ITEMS, $kept);
        }

        return collect($kept)
            ->map(fn (int $quantity, int $id): BasketLine => new BasketLine($variants[$id], $quantity))
            ->values();
    }

    /**
     * What the lines come to before postage, in pence.
     *
     * @param  Collection<int, BasketLine>  $lines
     */
    public static function subtotal(Collection $lines): int
    {
        return $lines->sum(fn (BasketLine $line): int => $line->subtotal());
    }

    /**
     * What postage the lines pay, given how the order reaches the customer.
     *
     * @param  Collection<int, BasketLine>  $lines
     */
    public static function postage(Collection $lines, Fulfilment $fulfilment): PostageQuote
    {
        return Postage::quote(
            $lines->map(fn (BasketLine $line): array => $line->postageItem()),
            $fulfilment,
            ShopSettings::current()->postage_cap,
        );
    }

    /**
     * Whether a variant may go in a basket: its product on sale, and some
     * left.
     */
    public static function canHold(ProductVariant $variant): bool
    {
        return $variant->isInStock()
            && Product::onSale()->whereKey($variant->product_id)->exists();
    }

    /**
     * @return array<int, int>
     */
    private function items(): array
    {
        return array_map('intval', (array) $this->session->get(self::ITEMS, []));
    }
}
