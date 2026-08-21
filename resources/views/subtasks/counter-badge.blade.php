{{--
    Done/total counter shown in the header of the subtasks section.

    The checklist itself is a nested Livewire component, so its updates never
    re-render this header: the counts are also pushed out as a browser event and
    picked up here by Alpine, which keeps the badge honest without a round trip
    through the parent form. The server-rendered values are the initial state,
    so the badge is already correct before Alpine boots.
--}}
<div
    x-data="{ done: {{ $done }}, total: {{ $total }} }"
    x-on:finisterre-subtasks-updated.window="done = $event.detail.done; total = $event.detail.total"
    x-show="total > 0"
    @if ($total === 0) style="display: none" @endif
>
    <x-filament::badge color="gray">
        <span x-text="`${done}/${total}`">{{ $done }}/{{ $total }}</span>
    </x-filament::badge>
</div>
