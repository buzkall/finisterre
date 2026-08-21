@extends('finisterre::events.layout')

@section('title', $event->title)

@section('content')
    <div class="card">
        <span class="badge {{ $event->status === \Arzcode\Finisterre\Enums\EventStatusEnum::Scheduled ? 'badge-success' : '' }}">
            {{ $event->status->getLabel() }}
        </span>
        <h1>{{ $event->title }}</h1>
        <p class="muted">
            {{ __('finisterre::finisterre.events.frontend.hello', ['name' => $attendee->displayName()]) }}
        </p>

        @if($event->scheduled_start_at)
            <p><strong>{{ $event->scheduled_start_at->isoFormat('LLLL') }}</strong>
                <span class="muted">({{ $event->duration_minutes }} {{ __('finisterre::finisterre.events.frontend.minutes') }})</span>
            </p>
        @endif

        @if(filled($event->description))
            <div class="prose">{!! $event->description !!}</div>
        @endif
    </div>

    @if(filled($event->public_agenda))
        <div class="card">
            <h2>{{ __('finisterre::finisterre.events.agenda') }}</h2>
            <div class="prose">{!! $event->public_agenda !!}</div>
        </div>
    @endif

    @if(auth()->check() && filled($event->private_agenda))
        <div class="card">
            <h2>{{ __('finisterre::finisterre.events.private_agenda') }}</h2>
            <div class="prose">{!! $event->private_agenda !!}</div>
        </div>
    @endif

    @include('finisterre::events.partials.video-call')

    @if($event->status->acceptsAvailability())
        @livewire('finisterre-availability-picker', ['attendee' => $attendee])
    @elseif($event->status === \Arzcode\Finisterre\Enums\EventStatusEnum::Cancelled)
        <div class="card">
            <p class="muted">{{ __('finisterre::finisterre.events.frontend.cancelled') }}</p>
        </div>
    @endif
@endsection
