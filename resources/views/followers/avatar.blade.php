@props(['user', 'size' => 'h-8 w-8 text-xs', 'ring' => 'ring-2 ring-white dark:ring-gray-900'])

@php
    $name = \Arzcode\Finisterre\Support\UserModel::displayName($user);
    $avatar = \Arzcode\Finisterre\Support\UserAvatar::url($user);
@endphp

{{-- The host application's avatar when it has one, the initials otherwise:
     the same circle the board cards draw. --}}
<span
    title="{{ $name }}"
    {{ $attributes->class([$size, $ring, 'shrink-0 inline-flex items-center justify-center overflow-hidden rounded-full bg-gray-500 dark:bg-gray-600 font-semibold text-white']) }}
>
    @if ($avatar)
        <img src="{{ $avatar }}" alt="{{ $name }}" class="h-full w-full object-cover"/>
    @else
        {{ \Arzcode\Finisterre\Support\TaskFollowers::initials($name) }}
    @endif
</span>
