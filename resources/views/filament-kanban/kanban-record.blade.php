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

</div>
