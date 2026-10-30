<article class="gop-card">
    @if ($settings->showImages)
        <a class="gop-card-image" href="{{ $event['page'] }}" rel="noopener" tabindex="-1" aria-hidden="true">
            @if ($event['image'])
                <img src="{{ $event['image'] }}" alt="" width="548" height="462" loading="lazy">
            @else
                <span class="gop-card-placeholder">{{ $event['start']->format('D') }}<strong>{{ $event['start']->format('j') }}</strong>{{ $event['start']->format('M') }}</span>
            @endif
            @if ($event['category'] || $event['sold_out'])
                <span class="gop-badges">
                    @if ($event['sold_out'])
                        <span class="gop-badge is-sold-out">Sold out</span>
                    @endif
                    @if ($event['category'])
                        <span class="gop-badge">{{ $event['category'] }}</span>
                    @endif
                </span>
            @endif
        </a>
    @endif
    <div class="gop-card-body">
        <p class="gop-when">
            <time datetime="{{ $event['start']->toDateString() }}">{{ $event['when'] }}</time>
            @if ($event['time'])
                <span class="gop-dash" aria-hidden="true">–</span> {{ $event['time'] }}
            @endif
        </p>
        <h3 class="gop-title"><a href="{{ $event['page'] }}" rel="noopener">{{ $event['title'] }}</a></h3>
        @if ($event['dates_note'])
            <p class="gop-meta">{{ $event['dates_note'] }}</p>
        @endif
        @if ($settings->showVenue && $widget->isForPromoter() && $event['venue'])
            <p class="gop-meta">{{ $event['venue'] }}</p>
        @endif
        @if ($settings->showPrice && $event['price'])
            <p class="gop-price">{{ $event['price'] }}</p>
        @endif
        <div class="gop-card-foot">
            @include('embed.partials.button')
        </div>
    </div>
</article>
