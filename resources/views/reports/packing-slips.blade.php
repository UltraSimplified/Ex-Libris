{{--
    Packing slips, rendered to PDF by dompdf: one page per order. A posted
    order leads with where it is going, big enough to read across the room;
    a collection order leads with the customer's name and reference, to tape
    to the bag on the shelf. dompdf lays out tables, not flexbox or grid.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Packing slips</title>
    <style>
        @page {
            margin: 16mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #111;
        }

        .slip {
            page-break-after: always;
        }

        .slip:last-child {
            page-break-after: auto;
        }

        .masthead {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 0.05em;
        }

        .reference {
            font-size: 28pt;
            font-weight: bold;
            margin: 8mm 0 2mm;
        }

        .how {
            font-size: 12pt;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8mm;
        }

        .address {
            font-size: 18pt;
            line-height: 1.3;
            border: 1.5pt solid #111;
            padding: 6mm;
            margin-bottom: 10mm;
        }

        .warning {
            border: 1.5pt solid #b91c1c;
            color: #b91c1c;
            font-weight: bold;
            padding: 3mm;
            margin-bottom: 6mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            border-bottom: 1pt solid #111;
            padding: 2mm 0;
        }

        td {
            border-bottom: 0.5pt solid #999;
            padding: 3mm 0;
            vertical-align: top;
        }

        .qty {
            width: 15mm;
            text-align: center;
        }

        .tick {
            width: 12mm;
        }

        .box {
            display: inline-block;
            width: 5mm;
            height: 5mm;
            border: 1pt solid #111;
        }

        .footer {
            margin-top: 10mm;
            font-size: 9pt;
            color: #555;
        }
    </style>
</head>
<body>
@foreach ($orders as $order)
    <div class="slip">
        <div class="masthead">GOOD ON PAPER</div>

        <div class="reference">{{ $order->reference }}</div>
        <div class="how">
            {{ $order->fulfilment === $collection ? 'For collection at '.$collectionName : 'To post' }}
            @if ($order->paid_at)
                &middot; paid {{ $order->paid_at->format('j M Y') }}
            @endif
        </div>

        @if ($order->oversold)
            <div class="warning">Oversold: check there is stock for every line before packing.</div>
        @endif

        <div class="address">
            @if ($order->fulfilment === $collection)
                {{ $order->name }}<br>
                {{ $order->phone }}
            @else
                {!! implode('<br>', array_map('e', \App\Models\Order::addressLines($order->shipping_address))) !!}
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th class="tick"></th>
                    <th>Item</th>
                    <th class="qty">Qty</th>
                    <th>Packing</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td class="tick"><span class="box"></span></td>
                        <td>
                            <strong>{{ $item->product_name }}</strong>
                            @if ($item->variant_name !== 'Standard')
                                <br>{{ $item->variant_name }}
                            @endif
                        </td>
                        <td class="qty">{{ $item->quantity }}</td>
                        <td>{{ $item->postage_band }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            {{ $order->name }} &middot; {{ $order->email }}
            @if ($order->phone)
                &middot; {{ $order->phone }}
            @endif
        </div>
    </div>
@endforeach
</body>
</html>
