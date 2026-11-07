@extends('embed.layout')

@section('content')
    @if ($events->isEmpty())
        {{-- Only ever the dashboard's preview: on a website an empty widget switches itself off. --}}
        <p class="gop-empty">
            No upcoming events just now, so this widget is hidden on your website until there is one to show.
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
