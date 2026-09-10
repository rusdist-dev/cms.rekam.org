import clone from '../clone'
import extend from '../extend'

/**
 * The form counterpart of resourceTable (context.md §2.7, §4.3).
 *
 * Submits go through window.api, so a 422 comes back as a field-keyed error bag
 * and is rendered inline — the page never reloads and never shows an alert().
 */
export default (endpoint, initial = {}, options = {}) => extend({
    endpoint,
    // Endpoint of the record being edited; null means "create".
    recordId: options.recordId ?? null,
    redirectTo: options.redirectTo ?? null,

    form: clone(initial),
    pristine: clone(initial),

    // Files picked in this session, keyed by field name. Kept out of `form`
    // so the JSON payload stays serialisable.
    files: {},

    loading: false,
    saving: false,
    error: null,
    errors: {},
    saved: false,

    async init() {
        if (this.recordId) await this.load()
    },

    get isEditing() {
        return this.recordId !== null
    },

    get isDirty() {
        return JSON.stringify(this.form) !== JSON.stringify(this.pristine)
    },

    get hasErrors() {
        return Object.keys(this.errors).length > 0
    },

    /**
     * Field errors arrive keyed by dotted path (`title.id`, `rundowns.0.time`),
     * so views ask for exactly the key they render.
     */
    fieldError(field) {
        const value = this.errors[field]
        if (!value) return null

        return Array.isArray(value) ? value[0] : value
    },

    clearError(field) {
        if (this.errors[field]) delete this.errors[field]
    },

    async load() {
        this.loading = true
        this.error = null

        try {
            const res = await window.api.get(`${this.endpoint}/${this.recordId}`)
            this.form = { ...this.form, ...res.data }
            this.pristine = clone(this.form)
        } catch (e) {
            this.error = e.message
        } finally {
            this.loading = false
        }
    },

    /**
     * Files force multipart, and PHP does not populate $_FILES for PUT bodies,
     * so an update with an upload is POSTed to the record URL. The routes are
     * declared to match.
     */
    buildBody() {
        // Every field name registered by a mediaPicker (image-upload /
        // file-upload) — whether or not this submission actually picks a new
        // file for it. `form[name]` there holds the picker's display
        // metadata ({ path, url, name, size }), not something the server
        // accepts: a `nullable|image` rule rejects anything that isn't a real
        // UploadedFile, so that object must never reach the request body.
        // The paired `remove_{name}` flag already says what to do when no
        // new file is attached.
        const managedKeys = Object.keys(this.files)
        const files = Object.entries(this.files).filter(([, f]) => f instanceof File)

        if (files.length === 0) {
            if (managedKeys.length === 0) return this.form

            const body = { ...this.form }
            managedKeys.forEach((key) => delete body[key])

            return body
        }

        const body = new FormData()

        // Nested objects (title.id, rundowns.0.time) are flattened into the
        // bracket notation Laravel parses back into arrays.
        const append = (key, value) => {
            if (value === null || value === undefined) return

            if (value instanceof File) {
                body.append(key, value)
            } else if (Array.isArray(value)) {
                value.forEach((v, i) => append(`${key}[${i}]`, v))
            } else if (typeof value === 'object') {
                Object.entries(value).forEach(([k, v]) => append(`${key}[${k}]`, v))
            } else if (typeof value === 'boolean') {
                body.append(key, value ? '1' : '0')
            } else {
                body.append(key, value)
            }
        }

        Object.entries(this.form).forEach(([key, value]) => {
            if (managedKeys.includes(key)) return // sent as a real file below, or omitted
            append(key, value)
        })
        files.forEach(([key, file]) => body.append(key, file))

        return body
    },

    async submit(payload = null) {
        if (this.saving) return false

        this.saving = true
        this.error = null
        this.errors = {}
        this.saved = false

        const body = payload ?? this.buildBody()

        try {
            const res = this.isEditing
                ? await window.api.post(`${this.endpoint}/${this.recordId}`, body)
                : await window.api.post(this.endpoint, body)

            this.pristine = clone(this.form)
            this.saved = true

            if (!this.isEditing && res?.data?.id) {
                this.recordId = res.data.id
            }

            if (this.redirectTo) {
                // Leaving immediately would drop the success flash, so the server
                // sets it on the next page load.
                window.location.assign(this.redirectTo)
            }

            return true
        } catch (e) {
            this.errors = e.errors ?? {}
            // A 422 is already explained field by field; anything else needs a
            // banner or the user sees nothing at all.
            this.error = e.status === 422 ? null : e.message
            this.focusFirstError()

            return false
        } finally {
            this.saving = false
        }
    },

    focusFirstError() {
        const first = Object.keys(this.errors)[0]
        if (!first) return

        this.$nextTick(() => {
            const el = document.getElementById(first) ?? document.querySelector(`[name="${first}"]`)
            el?.focus()
            el?.scrollIntoView({ block: 'center', behavior: 'smooth' })
        })
    },

    reset() {
        this.form = clone(this.pristine)
        this.errors = {}
        this.error = null
    },
}, options.extra)
