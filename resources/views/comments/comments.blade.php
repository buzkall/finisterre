<div class="flex flex-col h-full space-y-4">
    @if (auth()->user()->can('create', \Buzkall\Finisterre\Models\FinisterreTaskComment::class))
        <div class="space-y-4">
            {{ $this->form }}

            <div class="w-full flex justify-end">
                <x-filament::button
                    wire:click="create"
                    color="primary"
                >
                    {{ __('finisterre::finisterre.comments.add') }}
                </x-filament::button>
            </div>
        </div>
    @endif

    @if (count($this->comments))
        <div class="grid gap-4">
            @foreach ($this->comments as $comment)
                <div class="fi-in-repeatable-item block rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex gap-x-3">
                        @if (config('finisterre.comments.display_avatars'))
                            <x-filament-panels::avatar.user size="md" :user="$comment->creator"/>
                        @endif

                        <div class="flex-grow space-y-2 pt-[6px]">
                            <div class="flex gap-x-2 items-center justify-between">
                                <div class="flex gap-x-2 items-center">
                                    <div class="text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $comment->creator[config('finisterre.comments.user_name_attribute')] }}
                                    </div>

                                    <div class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                        {{ $comment->created_at->diffForHumans() }}
                                    </div>
                                </div>

                                @if (auth()->user()->can('delete', $comment))
                                    <div class="flex-shrink-0">
                                        <x-filament::icon-button
                                            wire:click="delete({{ $comment->id }})"
                                            icon="heroicon-s-trash"
                                            color="danger"
                                            tooltip="{{ __('finisterre::finisterre.comments.delete') }}"
                                        />
                                    </div>
                                @endif
                            </div>

                            <div class="prose dark:prose-invert [&>*]:mb-2 [&>*]:mt-0 [&>*:last-child]:mb-0 prose-sm text-sm leading-6 text-gray-950 dark:text-white max-w-none pr-8">
                                @if(config('finisterre.comments.editor') === 'markdown')
                                    {{ Str::of($comment->comment)->markdown()->toHtmlString() }}
                                @else
                                    @php
                                        // First, temporarily mark URLs in HTML tags to protect them
                                        $content = preg_replace_callback('/<[^>]*>/', function($match) {
                                            return str_replace(['http://', 'https://'], ['__HTTP__', '__HTTPS__'], $match[0]);
                                        }, $comment->comment);

                                        // Now safely replace URLs that are not in tags
                                        $content = preg_replace('/(https?:\/\/[^\s<]+)/', '<a href="$1" target="_blank" class="text-blue-500 underline">$1</a>', $content);

                                        // Restore protected URLs
                                        $content = str_replace(['__HTTP__', '__HTTPS__'], ['http://', 'https://'], $content);

                                        $htmlString = new \Illuminate\Support\HtmlString($content);
                                    @endphp
                                    {{ $htmlString }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="flex-grow flex flex-col items-center justify-center space-y-4">
            <x-filament::icon
                icon="heroicon-s-chat-bubble-left-right"
                class="h-12 w-12 text-gray-400 dark:text-gray-500"
            />

            <div class="text-sm text-gray-400 dark:text-gray-500">
                {{ __('finisterre::finisterre.comments.empty') }}
            </div>
        </div>
    @endif
</div>
