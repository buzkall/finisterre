<div class="relative">
    <div class="font-bold absolute bottom-4 right-4 z-10">
        <button wire:click="resetFilters" type="button" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
            {{ __('finisterre::finisterre.filter.reset') }}
        </button>
    </div>
    {{ $this->form }}
</div>
