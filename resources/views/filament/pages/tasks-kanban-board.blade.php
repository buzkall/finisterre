{{--
    flowforge's board page gives the board a fixed calc(100vh - 11rem), which assumes nothing sits above it. The filter
    widget does, so the board ran past the bottom of the screen and the end of a long column (and the board's own
    horizontal scrollbar) could not be reached. The board is measured instead: it takes whatever height is left below
    everything above it, and is measured again whenever that changes (the filter panel lazy-loading, folding on a phone,
    the window resizing). On a screen too short for that it keeps a minimum height and the page scrolls as usual.
--}}
<x-filament-panels::page>
    <div
        x-data="{
            height: null,
            observer: null,
            init() {
                this.fit()
                this.observer = new ResizeObserver(() => this.fit())
                this.observer.observe(document.body)
            },
            destroy() {
                this.observer?.disconnect()
            },
            fit() {
                const top = this.$el.getBoundingClientRect().top + window.scrollY
                const height = Math.max(384, Math.floor(window.innerHeight - top - this.spaceBelow()))

                if (Math.abs(height - (this.height ?? 0)) > 1) {
                    this.height = height
                }
            },
            {{-- What the page lays out under the board: the padding its containers close with and anything that
                 follows it. Not read from the document height, which Filament's full-screen layout pads out with
                 empty space whenever the content is shorter than the screen. --}}
            spaceBelow() {
                let space = 0

                for (let element = this.$el; element && element !== document.documentElement; element = element.parentElement) {
                    const style = getComputedStyle(element)

                    if (element !== this.$el) {
                        space += parseFloat(style.paddingBottom) + parseFloat(style.borderBottomWidth)
                    }

                    space += parseFloat(style.marginBottom)

                    const parent = element.parentElement && getComputedStyle(element.parentElement)

                    if (parent?.display.includes('flex') && ! parent.flexDirection.startsWith('column')) {
                        continue
                    }

                    for (let sibling = element.nextElementSibling; sibling; sibling = sibling.nextElementSibling) {
                        if (! ['absolute', 'fixed'].includes(getComputedStyle(sibling).position)) {
                            space += sibling.getBoundingClientRect().height
                        }
                    }
                }

                return space
            },
        }"
        x-on:resize.window="fit()"
        x-bind:style="height ? { height: height + 'px' } : {}"
        class="h-[calc(100vh-11rem)] relative"
    >
        {{ $this->board }}
    </div>
</x-filament-panels::page>
