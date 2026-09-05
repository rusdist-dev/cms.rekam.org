/**
 * Minimal rich text editor built on contenteditable.
 *
 * The package baseline (context.md §8.9) contains no editor library, and adding
 * one needs a written justification — so this covers what the CMS body fields
 * actually need: headings, bold/italic, lists, links, quotes. If the editorial
 * team turns out to need tables or embeds, that is the moment to argue for
 * TipTap rather than growing this file.
 *
 * Paste is forced to plain text: content arriving from Word or another CMS
 * otherwise drags in inline styles that the compro cannot theme.
 */

const COMMANDS = {
    bold: () => document.execCommand('bold'),
    italic: () => document.execCommand('italic'),
    h2: () => document.execCommand('formatBlock', false, 'h2'),
    h3: () => document.execCommand('formatBlock', false, 'h3'),
    paragraph: () => document.execCommand('formatBlock', false, 'p'),
    quote: () => document.execCommand('formatBlock', false, 'blockquote'),
    ul: () => document.execCommand('insertUnorderedList'),
    ol: () => document.execCommand('insertOrderedList'),
    clear: () => document.execCommand('removeFormat'),
}

export default (initial = '') => ({
    html: initial ?? '',

    init() {
        this.$refs.area.innerHTML = this.html ?? ''

        // The parent form owns the value; keep the DOM in sync when it is
        // replaced wholesale (loading an existing record into the form).
        this.$watch('html', (value) => {
            if (this.$refs.area.innerHTML !== value) {
                this.$refs.area.innerHTML = value ?? ''
            }
        })
    },

    sync() {
        this.html = this.$refs.area.innerHTML
    },

    run(command) {
        this.$refs.area.focus()
        COMMANDS[command]?.()
        this.sync()
    },

    isActive(command) {
        try {
            return document.queryCommandState(command)
        } catch {
            return false
        }
    },

    link() {
        const url = window.prompt('Alamat tautan (URL):')
        if (!url) return

        this.$refs.area.focus()
        document.execCommand('createLink', false, url)
        this.sync()
    },

    unlink() {
        this.$refs.area.focus()
        document.execCommand('unlink')
        this.sync()
    },

    pastePlain(event) {
        event.preventDefault()
        const text = (event.clipboardData ?? window.clipboardData).getData('text/plain')
        document.execCommand('insertText', false, text)
        this.sync()
    },

    /**
     * Reading `$refs.area` here (instead of just `this.html`) used to freeze
     * the placeholder forever: touching the live DOM node inside a getter
     * breaks Alpine/Vue's dependency tracking for the whole `x-show`, so it
     * never re-ran once the record's body arrived after the initial mount —
     * the editor showed the loaded content with the placeholder stuck on top
     * of it. Stripping tags from the tracked `html` string catches the same
     * "just an empty paragraph" case without ever touching the DOM.
     */
    get isEmpty() {
        return this.html.replace(/<[^>]*>/g, '').trim() === ''
    },
})
