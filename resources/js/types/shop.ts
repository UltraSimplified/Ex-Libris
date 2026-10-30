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
