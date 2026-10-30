@if ($event['sold_out'])
    <span class="gop-button is-sold-out">Sold out</span>
@elseif ($event['tickets'])
    <a class="gop-button" href="{{ $event['tickets'] }}" rel="noopener">{{ $settings->buttonText }}</a>
@else
    <a class="gop-button is-quiet" href="{{ $event['page'] }}" rel="noopener">Details</a>
@endif
