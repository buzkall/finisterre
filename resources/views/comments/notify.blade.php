@php
    $recipients = $getLivewire()->notifyUsers();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="flex flex-wrap items-center gap-2" data-finisterre-notify>
        @forelse ($recipients as $recipient)
            @php($name = \Arzcode\Finisterre\Support\UserModel::displayName($recipient))

            <span class="relative inline-flex" wire:key="notify-{{ $recipient->getKey() }}">
                @include('finisterre::followers.avatar', ['user' => $recipient])

                <button
                    type="button"
                    wire:click="removeNotify({{ $recipient->getKey() }})"
                    title="{{ __('finisterre::finisterre.comments.notify_remove', ['name' => $name]) }}"
                    aria-label="{{ __('finisterre::finisterre.comments.notify_remove', ['name' => $name]) }}"
                    style="top: -0.375rem; right: -0.375rem"
                    class="absolute inline-flex h-4 w-4 items-center justify-center rounded-full bg-white text-gray-500 ring-1 ring-gray-950/10 hover:text-danger-600 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/20"
                >
                    <x-filament::icon icon="heroicon-m-x-mark" class="h-3 w-3"/>
                </button>
            </span>
        @empty
            <span class="text-sm text-gray-400 dark:text-gray-500">
                {{ __('finisterre::finisterre.comments.notify_nobody') }}
            </span>
        @endforelse

        {{ $getLivewire()->addNotifyAction }}
    </div>
</x-dynamic-component>
