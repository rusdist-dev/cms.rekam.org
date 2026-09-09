import extend from '../extend'

/**
 * One taxonomy option list (team levels, news programs, ...) in the settings
 * editor: loads the raw bilingual shape and PUTs the whole list back in one
 * request (TaxonomyService::replace() is a full replace, not a per-row patch).
 *
 * Load and save hit different paths — `taxonomy/{group}/edit` (GET only) vs
 * `taxonomy/{group}` (PUT only) — unlike settingsForm's singleton resources,
 * so the two URLs must be passed in separately rather than sharing one.
 */
export default (loadEndpoint, saveEndpoint) => extend({
    loadEndpoint,
    saveEndpoint,
    options: [],

    loading: false,
    saving: false,
    error: null,
    saveError: null,
    saved: false,

    init() {
        this.load()
    },

    async load() {
        this.loading = true
        this.error = null

        try {
            const res = await window.api.get(this.loadEndpoint)
            this.options = res.data ?? []
        } catch (e) {
            this.error = e.message
        } finally {
            this.loading = false
        }
    },

    // A failed save must not hide the form the way a failed load does — that
    // would bury the user's unsaved edits behind a dead end (`load()` is the
    // only recovery, and it discards what they typed).
    async save() {
        this.saving = true
        this.saveError = null
        this.saved = false

        try {
            const res = await window.api.put(this.saveEndpoint, { options: this.options })
            this.options = res.data ?? this.options
            this.saved = true
        } catch (e) {
            this.saveError = Object.values(e.errors ?? {}).flat()[0] ?? e.message
        } finally {
            this.saving = false
        }
    },
}, {})
