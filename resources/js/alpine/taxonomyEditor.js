import extend from '../extend'

/**
 * One taxonomy option list (team levels, news programs, ...) in the settings
 * editor: loads the raw bilingual shape and PUTs the whole list back in one
 * request (TaxonomyService::replace() is a full replace, not a per-row patch).
 */
export default (endpoint) => extend({
    endpoint,
    options: [],

    loading: false,
    saving: false,
    error: null,
    saved: false,

    init() {
        this.load()
    },

    async load() {
        this.loading = true
        this.error = null

        try {
            const res = await window.api.get(this.endpoint)
            this.options = res.data ?? []
        } catch (e) {
            this.error = e.message
        } finally {
            this.loading = false
        }
    },

    async save() {
        this.saving = true
        this.error = null
        this.saved = false

        try {
            const res = await window.api.put(this.endpoint, { options: this.options })
            this.options = res.data ?? this.options
            this.saved = true
        } catch (e) {
            this.error = e.message
        } finally {
            this.saving = false
        }
    },
}, {})
