{{-- The followers as a stack of overlapping avatars, inside a badge of the strip:
     the first few, then how many more there are. The circles are as tall as the
     badge's icon and its line of text, so the badge keeps the height of its
     neighbours, and their ring takes the badge's background: it only shows as
     the gap between two overlapping avatars. --}}
@php($shown = $followers->take(5))

<span class="flex items-center" data-finisterre-followers>
    @if ($followers->isEmpty())
        {{ __('finisterre::finisterre.followers.empty') }}
    @else
        <span class="flex items-center -space-x-1">
            @foreach ($shown as $follower)
                @include('finisterre::followers.avatar', [
                    'user' => $follower,
                    'size' => 'h-4 w-4 text-[0.5rem]',
                    'ring' => 'ring-1 ring-gray-50 dark:ring-gray-800',
                ])
            @endforeach

            @if ($followers->count() > $shown->count())
                <span
                    title="{{ $followers->skip($shown->count())->map(fn($follower) => \Arzcode\Finisterre\Support\UserModel::displayName($follower))->implode(', ') }}"
                    class="inline-flex h-4 min-w-4 shrink-0 items-center justify-center rounded-full bg-gray-200 px-1 text-[0.5rem] font-semibold text-gray-600 ring-1 ring-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:ring-gray-800"
                >
                    +{{ $followers->count() - $shown->count() }}
                </span>
            @endif
        </span>
    @endif
</span>
