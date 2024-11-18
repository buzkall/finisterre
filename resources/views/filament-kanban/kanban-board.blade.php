<x-filament-panels::page>
    <div x-data wire:ignore.self class="md:flex overflow-x-auto overflow-y-hidden gap-4 pb-4">
        @foreach($statuses as $status)
            @include(static::$statusView)
        @endforeach

        <div wire:ignore>
            @include(static::$scriptsView)
        </div>
    </div>

    @unless($disableEditModal)
        {{--<x-filament-kanban::edit-record-modal/>--}}

        <x-filament-panels::form wire:submit.prevent="editModalFormSubmitted">
            <x-filament::modal id="kanban--edit-record-modal" :slideOver="$this->getEditModalSlideOver()"
                               :width="$this->getEditModalWidth()">
                <x-slot name="header">
                    <x-filament::modal.heading>
                        {{ $this->getEditModalTitle() }}
                    </x-filament::modal.heading>
                </x-slot>

                {{ $this->form }}

                {{-- Removed footer buttons, because we add it above the comments section in the form --}}
                {{--<x-slot name="footer">
                    <x-filament::button type="submit">
                        {{$this->getEditModalSaveButtonLabel()}}
                    </x-filament::button>

                    <x-filament::button color="gray" x-on:click="isOpen = false">
                        {{$this->getEditModalCancelButtonLabel()}}
                    </x-filament::button>
                </x-slot>--}}
            </x-filament::modal>
        </x-filament-panels::form>
    @endunless
</x-filament-panels::page>
