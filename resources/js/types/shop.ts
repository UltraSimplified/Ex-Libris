/** An order as its customer is shown it. Money is in pence. */
export interface ShopOrder {
    reference: string;
    status: string;
    status_label: string;
    fulfilment: 'post' | 'collection';
    email: string | null;
    items: {
        id: number;
        product: string;
        variant: string;
        /** The product's picture as it was bought, on the CDN. */
        image: string | null;
        quantity: number;
        subtotal: number;
    }[];
    subtotal: number;
    shipping: number;
    total: number;
    shipping_address: string[];
    tracking_number: string | null;
    paid_at: string | null;
}
