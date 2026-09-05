import resourceForm from './resourceForm'

/**
 * Form for a content record with a cover image.
 *
 * The API returns `cover_path` (what is stored) and `cover_url` (what to show),
 * while the picker wants a `{url, name}` object. Translating between the two
 * lives here so every content form does it identically.
 *
 * The extra properties are passed through resourceForm's own `options.extra`
 * (see resourceForm.js / resources/js/extend.js) rather than spread onto its
 * return value here — `{ ...resourceForm(...), coverValue: null }` would
 * flatten resourceForm's `isEditing`/`isDirty`/`hasErrors` getters into plain
 * values frozen at construction time, before init() has loaded anything. That
 * breaks the "Periksa kembali isian yang ditandai" banner: `hasErrors` would
 * read `false` forever, even right after a real 422.
 */
export default (endpoint, initial, options = {}) => resourceForm(endpoint, initial, {
    ...options,
    extra: {
        // Shape the media picker binds to.
        coverValue: null,

        async init() {
            if (this.recordId) {
                await this.load()
                this.syncCoverFromRecord()
            }
        },

        syncCoverFromRecord() {
            this.coverValue = this.form.cover_url
                ? { path: this.form.cover_path, url: this.form.cover_url, name: null, size: null }
                : null

            // remove_cover starts false: loading a record is not a request to
            // clear its image.
            this.form.remove_cover = false
        },
    },
})
