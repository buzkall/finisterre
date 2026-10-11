{{--
    Done/total counter of the subtasks badge in the strip of the task page.

    The checklist itself is a nested Livewire component, so its updates never
    re-render the strip: the counts are also pushed out as a browser event and
    picked up here by Alpine, which keeps the badge honest without a round trip
    through the page. The server-rendered values are the initial state, so the
    badge is already correct before Alpine boots.
--}}
<span
    x-data="{ done: {{ $done }}, total: {{ $total }} }"
    x-on:finisterre-subtasks-updated.window="done = $event.detail.done; total = $event.detail.total"
    x-text="total > 0 ? `${done}/${total}` : '0'"
>{{ $total > 0 ? $done . '/' . $total : '0' }}</span>
