<a href="{{ $viewUrl }}" class="block hover:bg-gray-50 dark:hover:bg-white/5 -m-2 p-2 rounded-lg transition space-y-2">
    <div class="flex flex-wrap justify-between items-center gap-2">
        <div class="flex flex-wrap items-center gap-2">
            @if($priority)
                <x-filament::badge :color="$priorityColor" class="shrink-0">
                    {{ $priority }}
                </x-filament::badge>
            @endif

            @foreach($tagNames as $tagName)
                <x-filament::badge color="success" class="shrink-0">
                    #{{ $tagName }}
                </x-filament::badge>
            @endforeach
        </div>

        {{-- The assignee leads the stack: bigger, first, and z-10 keeps it on top
             of the creator that -space-x-2 tucks in behind its right edge. Each
             circle holds the host application's avatar when it has one, and falls
             back to the initials it always showed. --}}
        @if($assignee || $creator)
            <div class="shrink-0 flex items-center -space-x-2">
                @if($assignee)
                    <span
                        title="{{ __('finisterre::finisterre.assignee_name') }}: {{ $assignee }}"
                        class="relative z-10 shrink-0 inline-flex items-center justify-center overflow-hidden w-7 h-7 rounded-full bg-gray-700 ring-2 ring-black dark:ring-white text-xs font-semibold text-white"
                    >
                        @if($assigneeAvatar)
                            <img src="{{ $assigneeAvatar }}" alt="{{ $assignee }}" class="h-full w-full object-cover"/>
                        @else
                            {{ $assigneeInitials }}
                        @endif
                    </span>
                @endif

                @if($creator)
                    <span
                        title="{{ __('finisterre::finisterre.creator_name') }}: {{ $creator }}"
                        class="shrink-0 inline-flex items-center justify-center overflow-hidden w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-600 ring-2 ring-white dark:ring-gray-900 text-[10px] font-semibold text-white"
                    >
                        @if($creatorAvatar)
                            <img src="{{ $creatorAvatar }}" alt="{{ $creator }}" class="h-full w-full object-cover"/>
                        @else
                            {{ $creatorInitials }}
                        @endif
                    </span>
                @endif
            </div>
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

            @if($subtasksCount)
                <span class="inline-flex items-center gap-1">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-3 w-3"/>
                    {{ $subtasksDone }}/{{ $subtasksCount }}
                </span>
            @endif

            @if($hasChanges)
                <span class="h-2.5 w-2.5 bg-blue-500 rounded-full shadow-lg shadow-blue-500/50 animate-pulse"></span>
            @endif
        </div>

        @if($updatedAt)
            <div class="text-xs text-gray-500 dark:text-gray-400 ml-auto">{{ $updatedAt }}</div>
        @endif
    </div>
</a>
