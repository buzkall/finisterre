<a href="{{ $editUrl }}" class="block hover:bg-gray-50 dark:hover:bg-white/5 -m-2 p-2 rounded-lg transition space-y-2">
    <div class="flex flex-wrap justify-between items-center gap-2">
        <div class="flex flex-wrap items-center gap-2">
            @if($priority)
                <x-filament::badge :color="$priorityColor" class="shrink-0">
                    {{ $priority }}
                </x-filament::badge>
            @endif

            @if($tagName)
                <x-filament::badge color="success" class="shrink-0">
                    #{{ $tagName }}
                </x-filament::badge>
            @endif
        </div>

        @if($assigneeInitials)
            <span
                title="{{ $assignee }}"
                class="shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-700 ring-2 ring-black dark:ring-white text-xs font-semibold text-white"
            >
                {{ $assigneeInitials }}
            </span>
        @endif
    </div>

    <div class="flex flex-wrap justify-between items-center gap-2">
        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-o-paper-clip" class="h-3 w-3"/>
                {{ $mediaCount }}
            </span>

            <span class="inline-flex items-center gap-1">
                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-3 w-3"/>
                {{ $commentsCount }}
            </span>

            @if($hasChanges)
                <span class="h-2.5 w-2.5 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50 animate-pulse"></span>
            @endif
        </div>

        @if($updatedAt)
            <div class="text-xs text-gray-500 dark:text-gray-400 ml-auto">{{ $updatedAt }}</div>
        @endif
    </div>
</a>
