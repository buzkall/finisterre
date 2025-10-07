<div style="position: relative;">
    <div class="font-bold" style="position: absolute; bottom: 1rem; right: 1rem; z-index: 10;">
        <button wire:click="resetFilters" type="button" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
            {{ __('finisterre::finisterre.filter.reset') }}
        </button>
    </div>
    {{ $this->form }}
</div>
