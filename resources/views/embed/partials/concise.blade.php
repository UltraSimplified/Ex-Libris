@if ($settings->showImages)
    <a class="gop-thumb" href="{{ $event['page'] }}" rel="noopener" tabindex="-1" aria-hidden="true">
        @if ($event['image'])
            <img src="{{ $event['image'] }}" alt="" width="548" height="462" loading="lazy">
        @else
            <span class="gop-thumb-date">{{ $event['start']->format('j') }}<small>{{ $event['start']->format('M') }}</small></span>
        @endif
    </a>
@endif
<span class="gop-main">
    <span class="gop-when">{{ $event['when'] }}@if ($event['time']) · {{ $event['time'] }}@endif</span>
    <a class="gop-title" href="{{ $event['page'] }}" rel="noopener">{{ $event['title'] }}</a>
    @if ($settings->showVenue && $widget->spansVenues() && $event['venue'])
        <span class="gop-meta">{{ $event['venue'] }}</span>
    @endif
</span>
@include('embed.partials.button')
