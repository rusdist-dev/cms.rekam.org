/**
 * Checkbox-style multi picker used for `related_programs` and any other
 * taxonomy whose options come from site_settings (context.md §5.12).
 *
 * The model is an array of slugs — never labels — so renaming a program in
 * settings never rewrites content rows.
 */
export default (options = [], initial = [], { name = '', searchable = true } = {}) => ({
    options,
    selected: Array.isArray(initial) ? [...initial] : [],
    search: '',
    open: false,
    name,
    searchable,

    get filtered() {
        if (!this.search.trim()) return this.options

        const q = this.search.toLowerCase()
        return this.options.filter((o) => o.label.toLowerCase().includes(q))
    },

    get selectedOptions() {
        return this.selected
            .map((slug) => this.options.find((o) => o.value === slug))
            .filter(Boolean)
    },

    get isEmpty() {
        return this.selected.length === 0
    },

    isSelected(value) {
        return this.selected.includes(value)
    },

    toggle(value) {
        this.selected = this.isSelected(value)
            ? this.selected.filter((v) => v !== value)
            : [...this.selected, value]
    },

    remove(value) {
        this.selected = this.selected.filter((v) => v !== value)
    },

    clear() {
        this.selected = []
    },

    close() {
        this.open = false
        this.search = ''
    },
})
