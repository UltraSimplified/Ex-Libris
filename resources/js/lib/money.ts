const pounds = new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency: 'GBP',
});

/**
 * An amount held in pence, written in pounds: 1250 is £12.50.
 */
export function formatPence(pence: number): string {
    return pounds.format(pence / 100);
}

/**
 * A price, or the span of prices a product's colours and sizes run across:
 * "£25.00", or "£20.00 – £25.00".
 */
export function formatPriceRange(from: number, to: number): string {
    return from === to
        ? formatPence(from)
        : `${formatPence(from)} – ${formatPence(to)}`;
}
