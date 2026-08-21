@if(filled($event->video_call_url) && $event->status === \Arzcode\Finisterre\Enums\EventStatusEnum::Scheduled)
    <div class="card">
        <h2>{{ __('finisterre::finisterre.events.frontend.video_call') }}</h2>
        @if($event->videoCallIsEmbeddable() && $event->videoCallIsOpen())
            <iframe
                class="video-embed"
                src="{{ $event->video_call_url }}"
                allow="camera; microphone; fullscreen; speaker; display-capture"
            ></iframe>
        @elseif($event->videoCallIsOpen())
            <a class="btn" href="{{ $event->video_call_url }}" target="_blank" rel="noopener">
                {{ __('finisterre::finisterre.events.frontend.join_call') }}
            </a>
        @else
            <p class="muted">{{ __('finisterre::finisterre.events.frontend.call_not_open') }}</p>
        @endif
    </div>
@endif
