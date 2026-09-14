{{--
    The task's card image, shown the way the board shows it: across the top.

    Repositioning works the way Notion's does. The picture is cropped with
    object-fit: cover, and the part that shows is its vertical object-position.
    Dragging maps pixels to that percentage through the height the crop hides, so
    the image follows the pointer exactly. Nothing reaches the server until the
    position is saved, and a picture no taller than its frame has nothing hidden to
    reveal, so it offers no button.
--}}
<div
    x-data="{
        position: @js($position),
        saved: @js($position),
        canReposition: false,
        repositioning: false,
        dragging: false,
        startY: 0,
        startPosition: 0,

        hiddenHeight() {
            const image = this.$refs.image

            if (! image.naturalWidth || ! image.naturalHeight) {
                return 0
            }

            const scale = Math.max(image.clientWidth / image.naturalWidth, image.clientHeight / image.naturalHeight)

            return image.naturalHeight * scale - image.clientHeight
        },

        measure() {
            this.canReposition = this.hiddenHeight() > 1
        },

        start(event) {
            if (! this.repositioning) {
                return
            }

            this.dragging = true
            this.startY = event.clientY
            this.startPosition = this.position
            event.currentTarget.setPointerCapture(event.pointerId)
        },

        move(event) {
            const hidden = this.hiddenHeight()

            if (! this.dragging || hidden <= 0) {
                return
            }

            const next = this.startPosition - ((event.clientY - this.startY) / hidden) * 100

            this.position = Math.min(100, Math.max(0, next))
        },

        stop() {
            this.dragging = false
        },

        cancel() {
            this.position = this.saved
            this.repositioning = false
        },

        save() {
            this.saved = this.position
            this.repositioning = false
            this.$wire.setCoverPosition(Math.round(this.position * 100) / 100)
        },
    }"
    x-on:resize.window="measure()"
    x-on:keydown.escape="repositioning && cancel()"
    class="group relative h-56 w-full overflow-hidden rounded-lg"
>
    <img
        x-ref="image"
        src="{{ $coverUrl }}"
        alt="{{ $title }}"
        draggable="false"
        style="object-position: 50% {{ $position }}%"
        x-bind:style="`object-position: 50% ${position}%`"
        x-init="$el.complete && measure()"
        x-on:load="measure()"
        x-on:pointerdown="start($event)"
        x-on:pointermove="move($event)"
        x-on:pointerup="stop()"
        x-on:pointercancel="stop()"
        x-bind:class="repositioning ? (dragging ? 'cursor-grabbing' : 'cursor-grab') + ' touch-none select-none' : ''"
        class="h-full w-full object-cover"
    />

    @if ($canReposition)
        <div
            x-show="repositioning"
            x-cloak
            class="pointer-events-none absolute inset-x-0 top-1/2 flex -translate-y-1/2 justify-center"
        >
            <span class="rounded-md bg-gray-900/70 px-3 py-1.5 text-xs font-medium text-white">
                {{ __('finisterre::finisterre.drag_cover') }}
            </span>
        </div>

        <div class="absolute right-2 top-2 flex gap-2">
            {{-- On a touch screen there is no hover to reveal it, so it stays on show. --}}
            <div
                x-show="canReposition && ! repositioning"
                x-cloak
                class="transition sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100"
            >
                <x-filament::button size="xs" color="gray" x-on:click="repositioning = true">
                    {{ __('finisterre::finisterre.reposition_cover') }}
                </x-filament::button>
            </div>

            <div x-show="repositioning" x-cloak class="flex gap-2">
                <x-filament::button size="xs" color="gray" x-on:click="cancel()">
                    {{ __('finisterre::finisterre.cancel') }}
                </x-filament::button>

                <x-filament::button size="xs" x-on:click="save()">
                    {{ __('finisterre::finisterre.save_position') }}
                </x-filament::button>
            </div>
        </div>
    @endif
</div>
