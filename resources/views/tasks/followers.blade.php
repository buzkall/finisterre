{{-- The followers as a stack of overlapping avatars, sized to sit inside a badge
     of the strip: the first few, then how many more there are. --}}
@php($shown = $followers->take(5))

<span class="inline-flex items-center gap-1" data-finisterre-followers>
    @if ($followers->isEmpty())
        {{ __('finisterre::finisterre.followers.empty') }}
    @else
        <span class="-my-0.5 inline-flex items-center -space-x-1">
            @foreach ($shown as $follower)
                @include('finisterre::followers.avatar', ['user' => $follower, 'size' => 'h-5 w-5 text-[0.5625rem]'])
            @endforeach

            @if ($followers->count() > $shown->count())
                <span
                    title="{{ $followers->skip($shown->count())->map(fn($follower) => \Arzcode\Finisterre\Support\UserModel::displayName($follower))->implode(', ') }}"
                    class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-gray-200 px-1 text-[0.5625rem] font-semibold text-gray-600 ring-2 ring-white dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-900"
                >
                    +{{ $followers->count() - $shown->count() }}
                </span>
            @endif
        </span>
    @endif
</span>
