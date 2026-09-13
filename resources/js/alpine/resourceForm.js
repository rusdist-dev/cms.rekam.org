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
    // 422 messages whose key matches no input on the page — see
    // collectUnmappedErrors().
    unmappedErrors: [],
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
            this.form = this.mergeRecord(res.data)
            this.pristine = clone(this.form)
        } catch (e) {
            this.error = e.message
        } finally {
            this.loading = false
        }
    },

    /**
     * Folds a loaded record onto the declared defaults, keeping a declared
     * shape wherever the record has nothing to put in it.
     *
     * A plain `{ ...this.form, ...record }` cannot: a translatable column that
     * is NULL in the database arrives as `null`, not as the `{id, en}` map the
     * defaults declare, and that `null` then lands where the map belongs. Every
     * `x-model="form.excerpt.id"` on the page throws reading a property off it,
     * so the field renders blank and silently discards whatever is typed into
     * it. Rows written through this form always have the full map; rows
     * imported straight into the database routinely do not.
     */
    mergeRecord(record) {
        const merged = { ...this.form }

        Object.entries(record ?? {}).forEach(([key, value]) => {
            const declared = this.form[key]
            const keepShape = value === null && declared !== null && typeof declared === 'object'

            merged[key] = keepShape ? clone(declared) : value
        })

        return merged
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
        const managedKeys = Object.keys(this.files)
        const fileEntries = Object.entries(this.files).filter(([, f]) => f instanceof File)
        const filedKeys = new Set(fileEntries.map(([key]) => key))

        // `remove_{name}` is recomputed here from the form's current value
        // rather than trusted as whatever the picker's own x-effect last
        // wrote — that write races the record's async load(), so a submit
        // right after opening the edit page could still see its pre-load
        // "no file yet" state and flag an untouched photo for deletion. A
        // field keeps its file whenever it still carries a path/url; a field
        // replaced by a fresh upload is never "removed".
        const removeFlags = {}
        managedKeys.forEach((key) => {
            if (filedKeys.has(key)) return
            const current = this.form[key]
            removeFlags[`remove_${key}`] = !(current && (current.path || current.url))
        })

        if (fileEntries.length === 0) {
            const body = { ...this.form, ...removeFlags }
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

        Object.entries({ ...this.form, ...removeFlags }).forEach(([key, value]) => {
            if (managedKeys.includes(key)) return // sent as a real file below, or omitted
            append(key, value)
        })
        fileEntries.forEach(([key, file]) => body.append(key, file))

        return body
    },

    async submit(payload = null) {
        if (this.saving) return false

        this.saving = true
        this.error = null
        this.errors = {}
        this.unmappedErrors = []
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
            this.collectUnmappedErrors()

            return false
        } finally {
            this.saving = false
        }
    },

    /**
     * The error banner tells the user to look for the fields marked in red, so
     * a message keyed on something the page never renders leaves them staring
     * at a form where everything looks filled in — a validation rule on a
     * parent key (`excerpt`, whose inputs are `excerpt.id` / `excerpt.en`) does
     * exactly that. Those messages are pulled out here so the banner can say
     * them outright instead.
     */
    collectUnmappedErrors() {
        this.$nextTick(() => {
            this.unmappedErrors = Object.entries(this.errors)
                .filter(([key]) => ! document.getElementById(key)
                    && ! document.querySelector(`[name="${key}"]`))
                .map(([, value]) => (Array.isArray(value) ? value[0] : value))
        })
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
        this.unmappedErrors = []
        this.error = null
    },
}, options.extra)
