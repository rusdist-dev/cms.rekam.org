/**
 * Free-form tag entry (SEO keywords, and the taxonomy editor in Pengaturan).
 * Values stay unique and trimmed so the stored array never needs cleaning up.
 */
export default (initial = []) => ({
    tags: Array.isArray(initial) ? [...initial] : [],
    draft: '',

    add() {
        const value = this.draft.trim()
        if (!value) return

        if (!this.tags.includes(value)) {
            this.tags = [...this.tags, value]
        }
        this.draft = ''
    },

    remove(tag) {
        this.tags = this.tags.filter((t) => t !== tag)
    },

    /** Backspace on an empty box removes the last tag — the expected shortcut. */
    backspace() {
        if (this.draft === '' && this.tags.length) {
            this.tags = this.tags.slice(0, -1)
        }
    },
})
