<div class="pt-4">
    <hr class="border-gray-200 dark:border-white/10"/>

    <div class="fi-fo-field-wrp py-8">
        <div class="grid gap-y-4">
        <span class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
            {{ __('finisterre::finisterre.comments.title') }}
        </span>

            {{-- the key now is the key! otherwise, the nested component gets loaded
                when the kanban is loaded and we have no record --}}
            <livewire:finisterre-comments key="{{ $record->id }}" :record="$record"/>
        </div>
    </div>
</div>
