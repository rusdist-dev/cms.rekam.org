import extend from '../extend'

/**
 * Single-payload fetch (a stats summary, a detail record, a list of options)
 * with the same four states every other fetch must show (context.md §2.3).
 *
 * resourceTable covers paginated lists; this covers everything else, so no page
 * ever needs a raw fetch() in an x-data attribute.
 */
export default (endpoint, fallback = null, options = {}) => extend({
    endpoint,
    data: fallback,
    loading: false,
    error: null,

    init() {
        this.load()
    },

    get isEmpty() {
        if (this.loading || this.error) return false
        if (this.data === null || this.data === undefined) return true

        return Array.isArray(this.data) ? this.data.length === 0 : Object.keys(this.data).length === 0
    },

    get isReady() {
        return !this.loading && !this.error && !this.isEmpty
    },

    async load() {
        this.loading = true
        this.error = null

        try {
            const res = await window.api.get(this.endpoint)
            this.data = res.data ?? res
        } catch (e) {
            this.error = e.message
            this.data = fallback
        } finally {
            this.loading = false
        }
    },
}, options.extra)
