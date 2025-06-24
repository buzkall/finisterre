<x-filament-panels::page>
    <x-filament-panels::header
            :actions="$this->getCachedHeaderActions()"
            :breadcrumbs="filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : []"
            heading=""
            subheading=""
    />

    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    @include('finisterre::comments.view', ['record' => $record])
</x-filament-panels::page>