/**
 * Workaround for a Filament v5 RichEditor bug where pasting several images at once
 * drops all but the last one (no console error).
 *
 * Root cause: Filament's pasted-file handler (resources/js/components/rich-editor/
 * extension-local-files.js, `handlePaste` files branch) uploads every pasted file in a
 * concurrent `files.forEach` loop. The temporary Livewire uploads target nested
 * `componentFileAttachments.<statePath>.<key>` properties and evict each other, so the
 * earlier files' temporary attachment URL resolves to `null`. Filament then silently
 * bails on `if (!url) { return }`, and that image is never inserted.
 *
 * Fix: intercept a multi-file paste before Filament's handler runs, then replay it as a
 * sequence of single-file paste events, waiting for each upload to finish before starting
 * the next. The single-file path already works correctly, so we just serialise it.
 *
 * The `text/html` paste branch (e.g. copying an image from a web page) is already
 * sequential upstream and is intentionally left untouched.
 */
(() => {
    const UPLOADED_EVENT = 'rich-editor-uploaded-file'
    const VALIDATION_EVENT = 'rich-editor-file-validation-message'
    const UPLOAD_TIMEOUT = 30000

    document.addEventListener('paste', onPaste, true)

    function onPaste(event) {
        const target = event.target
        const editorEl = target?.closest?.('.fi-fo-rich-editor .ProseMirror, .fi-fo-rich-editor.ProseMirror')

        if (! editorEl) {
            return
        }

        const clipboard = event.clipboardData

        if (! clipboard) {
            return
        }

        const files = clipboard.files?.length ? Array.from(clipboard.files) : []
        const hasText = (clipboard.getData('text')?.length ?? 0) > 0

        // Mirror Filament's own condition for the buggy branch. Single-file pastes and
        // text/html pastes already behave correctly, so leave them to Filament.
        if (files.length < 2 || hasText) {
            return
        }

        // Block Filament's concurrent handler for this event, including ProseMirror's
        // own paste handling, then replay sequentially.
        event.preventDefault()
        event.stopImmediatePropagation()

        replaySequentially(editorEl, files)
    }

    async function replaySequentially(editorEl, files) {
        for (const file of files) {
            const uploaded = waitForUpload(editorEl)
            dispatchSingleFilePaste(editorEl, file)
            await uploaded
        }
    }

    function dispatchSingleFilePaste(editorEl, file) {
        const dataTransfer = new DataTransfer()
        dataTransfer.items.add(file)

        editorEl.dispatchEvent(
            new ClipboardEvent('paste', {
                clipboardData: dataTransfer,
                bubbles: true,
                cancelable: true,
            }),
        )
    }

    function waitForUpload(editorEl) {
        return new Promise((resolve) => {
            const done = () => {
                cleanup()
                resolve()
            }

            const cleanup = () => {
                editorEl.removeEventListener(UPLOADED_EVENT, done)
                editorEl.removeEventListener(VALIDATION_EVENT, done)
                clearTimeout(timeout)
            }

            // Fallback so the chain never stalls if an upload never reports back.
            const timeout = setTimeout(done, UPLOAD_TIMEOUT)

            editorEl.addEventListener(UPLOADED_EVENT, done)
            editorEl.addEventListener(VALIDATION_EVENT, done)
        })
    }
})()
