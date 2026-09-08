import extend from '../extend'

/**
 * Drag-and-drop ordering for team members, partners and units.
 *
 * The new order is persisted in one batch PATCH (context.md §4.8 — multi-step
 * logic belongs in a service, not in N requests), and the list is rolled back
 * if the server rejects it, so what is on screen is always what is stored.
 */
export default (endpoint, initial = [], options = {}) => extend({
    endpoint,
    items: Array.isArray(initial) ? [...initial] : [],
    // Group key when ordering happens inside a group (team levels).
    groupKey: options.groupKey ?? null,

    loading: false,
    saving: false,
    error: null,
    savedAt: null,

    // Destructive actions always go through <x-modal.confirm> (context.md
    // §7.11), so the id being confirmed lives here rather than in each page's
    // x-data — same convention as resourceTable.js.
    confirming: null,
    deleting: false,

    init() {
        if (options.autoload !== false) this.load()
    },

    get isEmpty() {
        return !this.loading && !this.error && this.items.length === 0
    },

    get isReady() {
        return !this.loading && !this.error && this.items.length > 0
    },

    /** Items bucketed by group, in the order the taxonomy defines. */
    grouped(groups = []) {
        return groups.map((group) => ({
            ...group,
            items: this.items.filter((i) => i[this.groupKey] === group.value),
        }))
    },

    async load() {
        this.loading = true
        this.error = null

        try {
            const res = await window.api.get(this.endpoint)
            this.items = res.data ?? []
        } catch (e) {
            this.error = e.message
            this.items = []
        } finally {
            this.loading = false
        }
    },

    /**
     * Alpine's sort plugin reports the moved item and its new position; the
     * server is told the whole resulting order so it never has to infer it.
     */
    async move(id, position, group = null) {
        const previous = [...this.items]

        const scope = group === null
            ? this.items
            : this.items.filter((i) => i[this.groupKey] === group)

        const from = scope.findIndex((i) => i.id === id)
        if (from === -1 || from === position) return

        const [moved] = scope.splice(from, 1)
        scope.splice(position, 0, moved)

        if (group !== null) {
            const others = this.items.filter((i) => i[this.groupKey] !== group)
            this.items = [...others, ...scope]
        } else {
            this.items = scope
        }

        await this.persist(scope, previous)
    },

    async persist(scope, previous) {
        this.saving = true
        this.error = null

        try {
            await window.api.patch(`${this.endpoint}/reorder`, {
                order: scope.map((item, index) => ({ id: item.id, sort_order: index })),
            })

            this.savedAt = Date.now()
        } catch (e) {
            // Keeping the optimistic order would show an order that does not
            // survive a reload — worse than undoing the drag.
            this.items = previous
            this.error = e.message
        } finally {
            this.saving = false
        }
    },

    /** Deletes the row the confirmation modal is holding. */
    async destroy() {
        if (this.deleting || this.confirming === null) return

        this.deleting = true
        this.error = null

        try {
            await window.api.delete(`${this.endpoint}/${this.confirming}`)
            this.items = this.items.filter((i) => i.id !== this.confirming)
            this.confirming = null
        } catch (e) {
            this.confirming = null
            this.error = e.message
        } finally {
            this.deleting = false
        }
    },
}, options.extra)
