<a class="gop-poster" href="{{ $event['tickets'] ?? $event['page'] }}" rel="noopener">
    @if ($event['image'] && $settings->showImages)
        <img src="{{ $event['image'] }}" alt="" width="548" height="462" loading="lazy">
    @else
        <span class="gop-poster-placeholder" aria-hidden="true"></span>
    @endif
    <span class="gop-poster-labels">
        <span class="gop-poster-title">{{ $event['title'] }}</span>
        <time class="gop-poster-date" datetime="{{ $event['start']->toDateString() }}">
            {{ $event['sold_out'] ? 'Sold out · ' : '' }}{{ $event['under_way'] ? 'Until '.$event['end']->format('D jS M') : $event['start']->format('D jS M') }}
        </time>
    </span>
</a>
