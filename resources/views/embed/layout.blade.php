<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $ownerName ? 'Upcoming events at '.$ownerName : 'Upcoming events' }}</title>
    {{-- Everything leads out of the frame, never into it. --}}
    <base target="_blank">
    <style>
        {{-- Hex colours checked by Brand and a font stack from WidgetFont, so printed raw: escaping would break the CSS. --}}
        .gop-widget {
            @foreach ($brand->cssVariables() as $property => $value)
            {{ $property }}: {!! $value !!};
            @endforeach
            --gop-font: {!! $widget->font->stack() !!};
        }
        {!! file_get_contents(resource_path('css/embed.css')) !!}
    </style>
</head>
<body>
    <div class="gop-widget density-{{ $widget->density->value }}">
        @yield('content')

        <p class="gop-credit">
            <a href="{{ $ownerPage }}" rel="noopener">Listings by <strong>Good on Paper</strong></a>
        </p>
    </div>

    <script>
        window.gopWidget = {{ Js::from([
            'key' => $widget->key,
            'mode' => $widget->size_mode->value,
            'width' => $widget->width,
            'height' => $widget->height,
        ]) }};
        {!! file_get_contents(resource_path('js/embed-frame.js')) !!}
    </script>
</body>
</html>
