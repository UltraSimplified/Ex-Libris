@extends('embed.layout')

@section('content')
    @if ($events->isEmpty())
        <p class="gop-empty">
            No upcoming events just now.
            <a href="{{ $ownerPage }}" rel="noopener">See what else is on</a>
        </p>
    @else
        <ul class="gop-events" role="list">
            @foreach ($events as $event)
                <li class="gop-event @if ($event['sold_out']) is-sold-out @endif">
                    @switch($widget->density->value)
                        @case('compact')
                            @include('embed.partials.compact')
                            @break
                        @case('concise')
                            @include('embed.partials.concise')
                            @break
                        @case('large')
                            @include('embed.partials.large')
                            @break
                        @default
                            @include('embed.partials.detailed')
                    @endswitch
                </li>
            @endforeach
        </ul>

        @if ($more)
            <p class="gop-more">
                <a href="{{ $ownerPage }}" rel="noopener">More events</a>
            </p>
        @endif
    @endif
@endsection
