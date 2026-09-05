import extend from '../extend'

/**
 * The list pattern every index page uses (context.md §2 — canonical table).
 *
 * The four states are not optional: `loading`, `error`, `empty` and `ready` are
 * all rendered, so a slow or failing API never leaves a blank screen.
 */
export default (endpoint, initialFilters = {}, options = {}) => extend({
    endpoint,
    // URL template for the row's delete endpoint, e.g. '/dash-api/v1/users/__ID__'.
    deleteUrl: options.deleteUrl ?? null,
    // Endpoint for publish/draft/delete on the current selection.
    bulkUrl: options.bulkUrl ?? null,
    items: [],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    filters: { search: '', page: 1, per_page: 15, ...initialFilters },
    defaults: { search: '', page: 1, per_page: 15, ...initialFilters },

    loading: false,
    error: null,
    selected: [],

    // Destructive actions always go through <x-modal.confirm> (context.md §7.11),
    // so the id being confirmed lives here rather than in each page's x-data.
    confirming: null,
    deleting: false,
    confirmingBulkDelete: false,
    bulking: false,

    sort: options.sort ?? null,
    direction: options.direction ?? 'desc',

    // A newer request must never be overwritten by a slower older one.
    controller: null,

    init() {
        this.load()
    },

    get isEmpty() {
        return !this.loading && !this.error && this.items.length === 0
    },

    get isReady() {
        return !this.loading && !this.error && this.items.length > 0
    },

    get hasActiveFilters() {
        return Object.entries(this.filters).some(
            ([key, value]) => key !== 'page' && value !== '' && value !== this.defaults[key]
        )
    },

    get allSelected() {
        return this.items.length > 0 && this.selected.length === this.items.length
    },

    async load() {
        this.controller?.abort()
        this.controller = new AbortController()

        this.loading = true
        this.error = null

        try {
            const params = {
                ...this.filters,
                ...(this.sort ? { sort: this.sort, direction: this.direction } : {}),
            }

            const res = await window.api.get(
                `${this.endpoint}?${window.api.query(params)}`,
                { signal: this.controller.signal }
            )

            this.items = res.data ?? []
            this.meta = res.meta ?? this.meta
            // Rows that vanished after a reload must not stay selected.
            this.selected = this.selected.filter((id) => this.items.some((i) => i.id === id))
        } catch (e) {
            if (e.name === 'AbortError') return

            this.error = e.message
            this.items = []
        } finally {
            this.loading = false
        }
    },

    applyFilters() {
        this.filters.page = 1
        this.load()
    },

    resetFilters() {
        this.filters = { ...this.defaults }
        this.load()
    },

    goToPage(page) {
        if (page < 1 || page > this.meta.last_page || page === this.filters.page) return
        this.filters.page = page
        this.load()
    },

    sortBy(column) {
        if (this.sort === column) {
            this.direction = this.direction === 'asc' ? 'desc' : 'asc'
        } else {
            this.sort = column
            this.direction = 'asc'
        }
        this.applyFilters()
    },

    toggleSelectAll() {
        this.selected = this.allSelected ? [] : this.items.map((i) => i.id)
    },

    toggle(id) {
        this.selected = this.selected.includes(id)
            ? this.selected.filter((s) => s !== id)
            : [...this.selected, id]
    },

    confirmDelete(id) {
        this.confirming = id
    },

    cancelDelete() {
        this.confirming = null
    },

    /** Deletes the row the confirmation modal is holding. */
    async destroy() {
        if (this.deleting || this.confirming === null) return

        this.deleting = true
        this.error = null

        try {
            await window.api.delete(this.deleteUrl.replace('__ID__', this.confirming))
            this.confirming = null
            await this.refresh()
        } catch (e) {
            // Leaving the modal open would hide the reason it failed.
            this.confirming = null
            this.error = e.message
        } finally {
            this.deleting = false
        }
    },

    /**
     * Applies one action to every selected row in a single request, so a
     * hundred-row publish is one round trip rather than a hundred.
     */
    async bulk(action) {
        if (this.bulking || this.selected.length === 0) return

        this.bulking = true
        this.error = null

        try {
            await window.api.post(this.bulkUrl, { action, ids: this.selected })
            this.selected = []
            await this.refresh()
        } catch (e) {
            this.error = e.message
        } finally {
            this.bulking = false
        }
    },

    /** Reload while keeping the current page — used after a delete or bulk action. */
    async refresh() {
        // Deleting the only row on the last page would otherwise show an empty
        // page instead of the previous one.
        if (this.items.length === 1 && this.filters.page > 1) {
            this.filters.page -= 1
        }
        await this.load()
    },
}, options.extra)
