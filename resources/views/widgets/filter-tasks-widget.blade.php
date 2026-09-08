<x-filament-widgets::widget>
    {{-- A folded panel is an empty widget, but the grid row it sits in stays in
         the page content and the 2rem gap on either side of it doubles the space
         between the header and the board. The row is taken out of the flow while
         the panel is folded. The rule keys off an attribute rather than an inline
         style Alpine would have to reapply: a Livewire re-render of the page
         wipes what the server did not send, and Alpine only reapplies bindings
         when the state they read actually changes. --}}
    <style>
        .fi-grid:has([data-finisterre-filters-folded]) {
            display: none;
        }
    </style>

    {{-- Below `lg` the four filters stack into four rows and push the board off
         the screen, so on a phone the panel starts folded away and the Filters
         button in the page header unfolds it. From `lg` up nothing changes: the
         panel is always open, the button is not rendered, and a tablet turned to
         landscape opens the panel again. --}}
    <div
        x-data="{ open: window.matchMedia('(min-width: 1024px)').matches }"
        x-on:finisterre-toggle-filters.window="open = ! open"
        x-on:resize.window="if (window.matchMedia('(min-width: 1024px)').matches) open = true"
        x-bind:data-finisterre-filters-folded="open ? null : true"
    >
        {{-- Belt and braces: where `:has()` is not supported the panel still
             hides, it just leaves the empty row behind. --}}
        <div x-show="open" class="relative">
            <div class="font-bold absolute bottom-4 right-4 z-10">
                <button wire:click="resetFilters" type="button" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                    {{ __('finisterre::finisterre.filter.reset') }}
                </button>
            </div>
            {{ $this->form }}
        </div>
    </div>
</x-filament-widgets::widget>
