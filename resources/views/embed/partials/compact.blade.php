<time class="gop-date" datetime="{{ $event['start']->toDateString() }}">
    @if ($event['under_way'])
        <span class="gop-date-label">Until</span> {{ $event['end']->format('j M') }}
    @else
        {{ $event['start']->format('D j M') }}
    @endif
</time>
<span class="gop-main">
    <a class="gop-title" href="{{ $event['page'] }}" rel="noopener">{{ $event['title'] }}</a>
    @if ($settings->showVenue && $widget->spansVenues() && $event['venue'])
        <span class="gop-meta">{{ $event['venue'] }}</span>
    @endif
</span>
@include('embed.partials.button')
