import clone from '../clone'
import extend from '../extend'

/**
 * Form for a singleton settings resource (contact info, site identity/SEO/
 * socials/map) — the smaller sibling of resourceForm.js for an endpoint that
 * is always "the one record", with no id segment and no create/edit
 * distinction: always GET on load, always submit the whole form back.
 *
 * Submits PUT (plain JSON) when the form has no picked files (contact info,
 * socials, map) and POST (multipart) when it does (identity's logo/favicon,
 * SEO's og_image) — PHP does not populate $_FILES on PUT bodies, same reason
 * every other cover/photo/logo update in the app POSTs instead.
 */
export default (endpoint, initial = {}) => extend({
    endpoint,

    form: clone(initial),
    pristine: clone(initial),

    // Files picked in this session, keyed by field name — kept out of `form`
    // so the JSON path stays serialisable when there is nothing to upload.
    files: {},

    loading: false,
    saving: false,
    error: null,
    errors: {},
    saved: false,

    async init() {
        await this.load()
    },

    // shared.form-states' retry button branches on this to decide between
    // reloading and just dismissing the error — a singleton settings form is
    // always "editing" the one record, never "creating".
    get isEditing() {
        return true
    },

    get isDirty() {
        return JSON.stringify(this.form) !== JSON.stringify(this.pristine)
    },

    get hasErrors() {
        return Object.keys(this.errors).length > 0
    },

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
            const res = await window.api.get(this.endpoint)
            this.form = { ...this.form, ...res.data }
            this.pristine = clone(this.form)
        } catch (e) {
            this.error = e.message
        } finally {
            this.loading = false
        }
    },

    /**
     * Nested objects (tagline.id, keywords.0) are flattened into the bracket
     * notation Laravel parses back into arrays — same logic as
     * resourceForm.js's buildBody(), duplicated rather than shared since
     * neither factory depends on the other.
     */
    buildBody() {
        const files = Object.entries(this.files).filter(([, f]) => f instanceof File)

        if (files.length === 0) return this.form

        const body = new FormData()

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

        Object.entries(this.form).forEach(([key, value]) => append(key, value))
        files.forEach(([key, file]) => body.append(key, file))

        return body
    },

    async submit() {
        if (this.saving) return false

        this.saving = true
        this.error = null
        this.errors = {}
        this.saved = false

        const body = this.buildBody()
        const isMultipart = body instanceof FormData

        try {
            const res = isMultipart
                ? await window.api.post(this.endpoint, body)
                : await window.api.put(this.endpoint, body)

            this.form = { ...this.form, ...res.data }
            this.pristine = clone(this.form)
            this.saved = true

            return true
        } catch (e) {
            this.errors = e.errors ?? {}
            this.error = e.status === 422 ? null : e.message

            return false
        } finally {
            this.saving = false
        }
    },

    reset() {
        this.form = clone(this.pristine)
        this.errors = {}
        this.error = null
    },
}, {})
