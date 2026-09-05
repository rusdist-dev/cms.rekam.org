/**
 * Local file selection with a preview, shared by every cover/photo/logo field.
 *
 * Phase 5 adds the media library modal on top of this — the picked value is
 * already a `{ path, url, name }` shape so switching from a fresh upload to a
 * library pick needs no change at the call sites.
 */
const bytesToLabel = (bytes) => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export default (initial = null, { maxKb = 4096, accept = [] } = {}) => ({
    // { path, url, name, size } — path is what the API stores.
    value: initial,
    file: null,
    error: null,
    dragging: false,
    maxKb,
    accept,

    get hasValue() {
        return Boolean(this.value?.url || this.value?.path)
    },

    get previewUrl() {
        return this.value?.url ?? null
    },

    get label() {
        if (!this.value) return null
        const size = this.value.size ? ` · ${bytesToLabel(this.value.size)}` : ''
        return `${this.value.name ?? this.value.path}${size}`
    },

    pick() {
        this.$refs.input?.click()
    },

    onSelect(event) {
        const file = event.target.files?.[0]
        if (file) this.accept_(file)
        // Reset so re-picking the same file still fires a change event.
        event.target.value = ''
    },

    onDrop(event) {
        this.dragging = false
        const file = event.dataTransfer?.files?.[0]
        if (file) this.accept_(file)
    },

    /**
     * Client-side validation is a courtesy, not a guarantee — the server
     * validates again in the Form Request.
     */
    accept_(file) {
        this.error = null

        if (this.accept.length) {
            const ext = file.name.split('.').pop()?.toLowerCase()
            if (!this.accept.includes(ext)) {
                this.error = `Format tidak didukung. Gunakan: ${this.accept.join(', ')}.`
                return
            }
        }

        if (file.size > this.maxKb * 1024) {
            this.error = `Ukuran berkas melebihi ${bytesToLabel(this.maxKb * 1024)}.`
            return
        }

        this.file = file
        this.value = {
            path: null,
            url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
            name: file.name,
            size: file.size,
        }
    },

    remove() {
        if (this.value?.url?.startsWith('blob:')) URL.revokeObjectURL(this.value.url)

        this.value = null
        this.file = null
        this.error = null
    },
})
