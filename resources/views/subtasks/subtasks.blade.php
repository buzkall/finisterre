{{--
    Subtask checklist. Every interaction persists immediately, so this never
    depends on the task form being saved. The heading comes from the collapsible
    Section that wraps this component in the task form.

    Styling sticks to Filament's own components plus utility classes already
    used elsewhere in this package, so a host app does not need to re-run
    `npm run build` after upgrading. The completed-row strikethrough is an
    inline style for the same reason: `line-through` is not among the compiled
    utilities, and a rule in the package stylesheet would need `filament:assets`.
--}}
<div class="space-y-2">
    @php($canManage = $this->canManage())

    @if (count($subtasks))
        <div
            class="space-y-2"
            @if ($canManage)
                x-sortable
                x-on:end.stop="$wire.reorder($event.target.sortable.toArray())"
            @endif
        >
            @foreach ($subtasks as $rowKey => $subtask)
                <div
                    wire:key="finisterre-{{ $rowKey }}"
                    x-sortable-item="{{ $subtask['id'] }}"
                    class="flex items-center gap-2"
                >
                    @if ($canManage)
                        <button
                            type="button"
                            x-sortable-handle
                            class="shrink-0 text-gray-400"
                            title="{{ __('finisterre::finisterre.subtasks.reorder') }}"
                        >
                            <x-filament::icon icon="heroicon-o-arrows-up-down" class="h-3 w-3"/>
                        </button>
                    @endif

                    <x-filament::input.checkbox
                        class="shrink-0"
                        wire:model.live="subtasks.{{ $rowKey }}.completed"
                        :disabled="! $canManage"
                    />

                    <x-filament::input.wrapper class="flex-grow">
                        <x-filament::input
                            type="text"
                            wire:model.blur="subtasks.{{ $rowKey }}.title"
                            {{-- Enter must not submit the surrounding task form. --}}
                            x-on:keydown.enter.prevent="$el.blur()"
                            maxlength="255"
                            :disabled="! $canManage"
                            @style([
                                'text-decoration: line-through; opacity: 0.6;' => $subtask['completed'],
                            ])
                        />
                    </x-filament::input.wrapper>

                    @if ($canManage)
                        <x-filament::icon-button
                            class="shrink-0"
                            icon="heroicon-o-trash"
                            color="danger"
                            size="sm"
                            wire:click="delete('{{ $rowKey }}')"
                            wire:loading.attr="disabled"
                            :label="__('finisterre::finisterre.subtasks.delete')"
                        />
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        <div class="flex items-center gap-2">
            <x-filament::input.wrapper class="flex-grow">
                <x-filament::input
                    type="text"
                    wire:model="newTitle"
                    wire:keydown.enter.prevent="add"
                    maxlength="255"
                    placeholder="{{ __('finisterre::finisterre.subtasks.placeholder') }}"
                />
            </x-filament::input.wrapper>

            <x-filament::button
                class="shrink-0"
                color="gray"
                size="sm"
                icon="heroicon-o-plus"
                wire:click="add"
                wire:loading.attr="disabled"
            >
                {{ __('finisterre::finisterre.subtasks.add') }}
            </x-filament::button>
        </div>

    @endif
</div>
