<div
        id="{{ $record->getKey() }}"
        wire:click="recordClicked('{{ $record->getKey() }}', {{ @json_encode($record) }})"
        class="record bg-white dark:bg-gray-700 rounded-lg px-4 py-2 cursor-grab font-medium text-gray-600 dark:text-gray-200"
        @if($record->timestamps && now()->diffInSeconds($record->{$record::UPDATED_AT}) < 3)
            x-data
        x-init="
            $el.classList.add('animate-pulse-twice', 'bg-primary-100', 'dark:bg-primary-800')
            $el.classList.remove('bg-white', 'dark:bg-gray-700')
            setTimeout(() => {
                $el.classList.remove('bg-primary-100', 'dark:bg-primary-800')
                $el.classList.add('bg-white', 'dark:bg-gray-700')
            }, 3000)
        "
        @endif
>
    <div class="flex items-center justify-between">
        <div>{{ $record->{static::$recordTitleAttribute} }}</div>

        <div class="space-y-2">
            <div class="p-1 border rounded-lg text-xs text-center {{ $record->priority->color() }}">
                {{ $record->priority->getLabel() }}
            </div>
            @if($record->due_at)
                <div class="flex justify-end">
                    <div class="text-center p-1 border rounded-lg text-xs text-gray-500 dark:text-gray-400">
                        {{ $record->due_at->format('M j') }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="flex justify-between items-center py-1 text-xs text-gray-500 dark:text-gray-400">
        <div class="flex gap-2 items-center">
            <div class="flex">
                {{ $record->media->count() }}
                <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4"/>
            </div>

            <div class="flex">
                {{ $record->comments->count() }}
                <x-filament::icon icon="heroicon-o-chat-bubble-oval-left" class="h-4 w-4"/>
            </div>

            <div>
                @foreach($record->tags as $tag)
                    <div class="p-1 border rounded-lg text-center bg-primary-500 text-white w-auto">
                        #{{ $tag->name }}
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            {{ $record->updated_at->format('d/m/y H:i:s') }}
        </div>
    </div>
</div>
