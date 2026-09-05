import resourceForm from './resourceForm'

/**
 * Company settings: feature flags plus API key rotation.
 *
 * Rotation is separate from the save button on purpose — it invalidates the key
 * the compro is using right now, so it must be a deliberate act rather than a
 * side effect of pressing Simpan.
 *
 * Composed through resourceForm's `options.extra`, not `{ ...resourceForm(...),
 * ...}` — see resourceForm.js and resources/js/extend.js for why the spread
 * form freezes resourceForm's getters into dead values.
 */
export default (endpoint, recordId, rotateEndpoint) => resourceForm(
    endpoint,
    { name: '', domain: '', is_active: true, features: {} },
    {
        recordId,
        extra: {
            // Shown once after rotation; the server only ever stores the hash.
            apiKey: null,
            rotating: false,
            confirmingRotate: false,
            rotateError: null,

            async rotate() {
                if (this.rotating) return

                this.rotating = true
                this.rotateError = null

                try {
                    const res = await window.api.post(rotateEndpoint)
                    this.apiKey = res.data.api_key
                    this.confirmingRotate = false
                } catch (e) {
                    this.rotateError = e.message
                } finally {
                    this.rotating = false
                }
            },

            dismissKey() {
                this.apiKey = null
            },

            /** Core modules are always on, so their toggle is rendered as locked. */
            isCore(key, coreKeys) {
                return coreKeys.includes(key)
            },
        },
    }
)
