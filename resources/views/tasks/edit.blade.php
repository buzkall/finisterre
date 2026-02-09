<x-filament-panels::page>
    {{ $this->content }}

    @include('finisterre::comments.view', ['record' => $record])
</x-filament-panels::page>