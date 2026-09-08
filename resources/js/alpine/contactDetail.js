import apiResource from './apiResource'
import extend from '../extend'

/**
 * Contact message detail page: the record fetch (apiResource) plus the two
 * actions its page-header and delete modal need. Kept out of the Blade file
 * since Alpine logic longer than a few lines belongs here (context.md §3.9).
 */
export default (endpoint, archiveUrl, destroyUrl, redirectTo) => extend(apiResource(endpoint, null), {
    confirming: false,
    deleting: false,
    archiving: false,

    async archive() {
        if (this.archiving) return

        this.archiving = true

        try {
            const res = await window.api.patch(archiveUrl)
            this.data = res.data ?? this.data
        } catch (e) {
            this.error = e.message
        } finally {
            this.archiving = false
        }
    },

    async destroy() {
        if (this.deleting) return

        this.deleting = true

        try {
            await window.api.delete(destroyUrl)
            window.location.assign(redirectTo)
        } catch (e) {
            this.deleting = false
            this.confirming = false
            this.error = e.message
        }
    },
})
