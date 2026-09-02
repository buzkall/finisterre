<div class="flex flex-wrap items-center gap-2">
    <x-filament::badge :color="$getRecord()->status->getColor()">
        {{ $getRecord()->status->getLabel() }}
    </x-filament::badge>

    <x-filament::badge :color="$getRecord()->priority->getColor()">
        {{ $getRecord()->priority->getLabel() }}
    </x-filament::badge>

    @if ($showDueDate && $getRecord()->due_at)
        <x-filament::badge color="gray" icon="heroicon-o-calendar">
            {{ $getRecord()->due_at->format('d/m/y') }}
        </x-filament::badge>
    @endif

    <x-filament::badge color="gray" icon="heroicon-o-user">
        {{ $getRecord()->assigneeName() ?? __('finisterre::finisterre.unassigned') }}
    </x-filament::badge>

    @foreach ($getRecord()->tags as $tag)
        <x-filament::badge color="success">
            #{{ $tag->name }}
        </x-filament::badge>
    @endforeach
</div>
