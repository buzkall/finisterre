<x-filament-panels::page>
    @php($pending = $this->pendingUpgrade())

    @if ($pending['appliable'] !== [] || $pending['console'] !== [])
        <x-filament::section
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
            :heading="__('finisterre::finisterre.settings.upgrade_title')"
            data-finisterre-upgrade
        >
            <div class="grid gap-y-3 text-sm text-gray-700 dark:text-gray-200">
                @if ($pending['appliable'] !== [])
                    <div>
                        <p>{{ __('finisterre::finisterre.settings.upgrade_help') }}</p>
                        <ul class="mt-1 list-disc ps-5 font-mono text-xs">
                            @foreach ($pending['appliable'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($pending['console'] !== [])
                    <div>
                        <p>{{ __('finisterre::finisterre.settings.upgrade_console') }}</p>
                        <ul class="mt-1 list-disc ps-5 font-mono text-xs">
                            @foreach ($pending['console'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif

    <form
        wire:submit="save"
        class="fi-form grid gap-y-6"
        x-data
        @keydown.window.prevent.cmd.s="$wire.save()"
        @keydown.window.prevent.ctrl.s="$wire.save()"
    >
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">
                {{ __('finisterre::finisterre.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
