<x-filament-panels::page>
    {{ $this->content }}

    @include('finisterre::comments.view', ['record' => $record])

    @can('update', $record)
        @include('finisterre::tasks.editor-image-cover', ['coverFile' => $record->coverSourceFile()])
    @endcan
</x-filament-panels::page>
