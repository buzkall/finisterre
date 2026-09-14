{{--
    The card image star for images pasted into the description or a comment.

    Livewire morphs away anything added to rendered HTML, so the star is not put on
    each image. There is one, part of this template: hovering or tapping an image
    inside a [data-finisterre-editor-images] container moves it onto that image's
    top-right corner. It is fixed to the viewport, and hidden until then, so it takes
    no room in the page's grid; the layout-critical styles are inline so it works in
    host apps that never compile the package's CSS. Its $wire is the task page.

    data-cover-file is re-rendered with the page, so which image is the card image is
    read from it at hover time rather than kept in Alpine.
--}}
<button
    type="button"
    data-finisterre-cover-star
    data-cover-file="{{ $coverFile }}"
    x-data="{
        file: null,
        isCover: false,
        top: 0,
        left: 0,

        show(event) {
            if (event.target.closest('[data-finisterre-cover-star]')) {
                return
            }

            const image = event.target.closest('[data-finisterre-editor-images] img')
            const match = image?.getAttribute('src')?.match(/\/storage\/(?:finisterre-files\/)?([^\/?#]+)(?:[?#].*)?$/)

            if (! match) {
                this.file = null

                return
            }

            const rect = image.getBoundingClientRect()

            this.file = decodeURIComponent(match[1])
            this.isCover = this.file === this.$root.dataset.coverFile
            this.top = rect.top + 4
            this.left = rect.right - 4
        },

        pick() {
            const file = this.file

            this.file = null

            this.isCover ? $wire.setCoverMedia(null) : $wire.setCoverFromEditorImage(file)
        },
    }"
    x-show="file"
    x-cloak
    x-on:mouseover.document="show($event)"
    x-on:click.document="show($event)"
    x-on:scroll.window="file = null"
    x-on:resize.window="file = null"
    x-on:click="pick()"
    x-bind:title="isCover ? @js(__('finisterre::finisterre.remove_card_image')) : @js(__('finisterre::finisterre.set_card_image'))"
    x-bind:style="`position: fixed; z-index: 20; top: ${top}px; left: ${left}px; transform: translateX(-100%)`"
    class="inline-flex items-center justify-center rounded-full bg-gray-900/60 p-1 text-white"
>
    <span x-show="isCover">
        <x-filament::icon icon="heroicon-s-star" class="h-4 w-4"/>
    </span>

    <span x-show="! isCover">
        <x-filament::icon icon="heroicon-o-star" class="h-4 w-4"/>
    </span>
</button>
