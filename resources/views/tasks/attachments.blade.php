<ul class="flex flex-wrap items-center gap-2 text-sm">
    @foreach ($media as $file)
        @php($isImage = str_starts_with((string) $file->mime_type, 'image/'))

        <li class="inline-flex items-center gap-2 rounded-lg bg-gray-50 px-2 py-1 dark:bg-white/5">
            @if ($isImage)
                {{-- Images open in a lightbox built on Filament's own modal, no extra library needed. --}}
                <x-filament::modal
                    id="finisterre-attachment-{{ $file->getKey() }}"
                    width="5xl"
                    :heading="$file->name"
                    :close-button="true"
                >
                    <x-slot name="trigger">
                        <button type="button" class="block" title="{{ $file->name }}">
                            <img
                                src="{{ $file->getUrl() }}"
                                alt="{{ $file->name }}"
                                class="h-16 w-16 rounded-lg object-cover"
                            />
                        </button>
                    </x-slot>

                    <img
                        src="{{ $file->getUrl() }}"
                        alt="{{ $file->name }}"
                        class="mx-auto max-h-[80vh] w-auto"
                    />

                    <div class="flex items-center justify-end gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <a
                            href="{{ $file->getUrl() }}"
                            download="{{ $file->file_name }}"
                            class="inline-flex shrink-0 items-center gap-1 text-primary-600 underline"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4"/>
                            {{ __('finisterre::finisterre.download') }}
                        </a>
                    </div>
                </x-filament::modal>
            @else
                <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4 text-gray-400"/>

                <a
                    href="{{ $file->getUrl() }}"
                    target="_blank"
                    rel="noopener"
                    class="text-primary-600 underline"
                >
                    {{ $file->name }}
                </a>

                <a
                    href="{{ $file->getUrl() }}"
                    download="{{ $file->file_name }}"
                    title="{{ __('finisterre::finisterre.download') }}"
                >
                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4 text-gray-400"/>
                </a>
            @endif
        </li>
    @endforeach
</ul>
