@props(['user', 'size' => 'h-8 w-8 text-xs', 'ring' => 'ring-2 ring-white dark:ring-gray-900'])

@php
    $name = \Arzcode\Finisterre\Support\UserModel::displayName($user);
    $avatar = \Arzcode\Finisterre\Support\UserAvatar::url($user);
@endphp

{{-- The host application's avatar when it has one, the initials otherwise:
     the same circle the board cards draw. The image carries its own cap, since
     the circle is sized by the host theme's classes: with a theme built before
     these views existed, it would render at the full size of the file. --}}
<span
    title="{{ $name }}"
    {{ $attributes->class([$size, $ring, 'shrink-0 inline-flex items-center justify-center overflow-hidden rounded-full bg-gray-500 dark:bg-gray-600 font-semibold text-white']) }}
>
    @if ($avatar)
        <img src="{{ $avatar }}" alt="{{ $name }}" width="32" height="32" style="max-width: 2rem; max-height: 2rem" class="h-full w-full object-cover"/>
    @else
        {{ \Arzcode\Finisterre\Support\TaskFollowers::initials($name) }}
    @endif
</span>
