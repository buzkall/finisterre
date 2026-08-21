<div>
    <div class="card">
        <h2>{{ __('finisterre::finisterre.events.frontend.pick_title') }}</h2>
        <p class="muted">
            {{ __('finisterre::finisterre.events.frontend.pick_help', ['duration' => $event->duration_minutes]) }}
        </p>

        @forelse($slotsByDay as $day => $slots)
            <div class="day-title">{{ $day }}</div>
            <div class="slots">
                @foreach($slots as $slot)
                    <button
                        type="button"
                        class="slot {{ in_array($slot['value'], $selected) ? 'selected' : '' }}"
                        wire:click="toggle('{{ $slot['value'] }}')"
                    >
                        {{ $slot['label'] }}
                        <span class="count">{{ $slot['count'] }}/{{ $total }}</span>
                    </button>
                @endforeach
            </div>
        @empty
            <p class="muted">{{ __('finisterre::finisterre.events.frontend.no_slots') }}</p>
        @endforelse
    </div>

    <div class="save-bar">
        <div class="inner">
            @if($saved)
                <button class="btn" style="background: var(--success)" disabled>
                    {{ __('finisterre::finisterre.events.frontend.saved') }}
                </button>
            @else
                <button class="btn" wire:click="save" wire:loading.attr="disabled">
                    {{ __('finisterre::finisterre.events.frontend.save', ['count' => count($selected)]) }}
                </button>
            @endif
        </div>
    </div>
</div>
