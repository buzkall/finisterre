@extends('finisterre::events.layout')

@section('title', $event->title)

@section('content')
    @if(session('finisterre-registered'))
        <div class="flash">{{ session('finisterre-registered') }}</div>
    @endif

    <div class="card">
        <span class="badge {{ $event->status === \Arzcode\Finisterre\Enums\EventStatusEnum::Scheduled ? 'badge-success' : '' }}">
            {{ $event->status->getLabel() }}
        </span>
        <h1>{{ $event->title }}</h1>

        @if($event->scheduled_start_at)
            <p><strong>{{ $event->scheduled_start_at->isoFormat('LLLL') }}</strong>
                <span class="muted">({{ $event->duration_minutes }} {{ __('finisterre::finisterre.events.frontend.minutes') }})</span>
            </p>
        @else
            <p class="muted">{{ __('finisterre::finisterre.events.frontend.date_being_decided') }}</p>
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

    @include('finisterre::events.partials.video-call')

    @if($attendee)
        <div class="card">
            <a class="btn" href="{{ $attendee->personalUrl() }}">
                {{ __('finisterre::finisterre.events.frontend.my_attendee_page') }}
            </a>
        </div>
    @elseif($event->open_registration && $event->status->acceptsAvailability())
        <div class="card">
            <h2>{{ __('finisterre::finisterre.events.frontend.register') }}</h2>
            <form method="POST" action="{{ $event->publicUrl() }}/register">
                @csrf
                <div class="field">
                    <label for="name">{{ __('finisterre::finisterre.events.guest_name') }}</label>
                    <input id="name" name="name" value="{{ old('name') }}" required>
                    @error('name') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="email">{{ __('finisterre::finisterre.events.guest_email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                    @error('email') <div class="error">{{ $message }}</div> @enderror
                </div>
                <button class="btn" type="submit">{{ __('finisterre::finisterre.events.frontend.register_cta') }}</button>
            </form>
        </div>
    @endif
@endsection
