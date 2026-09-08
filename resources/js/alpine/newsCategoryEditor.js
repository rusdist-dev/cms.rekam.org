import extend from '../extend'

/**
 * The "Kategori Berita" widget in Pengaturan > Taksonomi. Unlike the generic
 * taxonomy lists, news categories are real rows with their own id and an
 * in-use delete guard, so this calls NewsCategoryApiController's CRUD
 * endpoints per action instead of replacing one whole array in a single PUT.
 */
export default (endpoint) => extend({
    endpoint,
    items: [],
    newName: '',

    loading: false,
    creating: false,
    deletingId: null,
    error: null,

    init() {
        this.load()
    },

    async load() {
        this.loading = true
        this.error = null

        try {
            const res = await window.api.get(this.endpoint)
            this.items = res.data ?? []
        } catch (e) {
            this.error = e.message
        } finally {
            this.loading = false
        }
    },

    async create() {
        const name = this.newName.trim()
        if (this.creating || !name) return

        this.creating = true
        this.error = null

        try {
            await window.api.post(this.endpoint, { name: { id: name, en: null } })
            this.newName = ''
            await this.load()
        } catch (e) {
            this.error = e.message
        } finally {
            this.creating = false
        }
    },

    async remove(id) {
        this.deletingId = id
        this.error = null

        try {
            await window.api.delete(`${this.endpoint}/${id}`)
            await this.load()
        } catch (e) {
            // "still in use" comes back as a 422 with a readable message.
            this.error = e.message
        } finally {
            this.deletingId = null
        }
    },
}, {})
