<x-filament-panels::page>
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
