import apiResource from './apiResource'

/**
 * A <select> whose options come from an endpoint.
 *
 * Every taxonomy dropdown in the CMS is this same shape, and copying the markup
 * per page is what let one of them keep pointing at a URL that had moved
 * (context.md §3.1). Value and label are read by dot path so the component
 * serves both response shapes: taxonomy lists ({value,label}) and relational
 * records ({id, name:{id,en}}).
 *
 * Composed through apiResource's `options.extra` (see apiResource.js and
 * resources/js/extend.js), not `{ ...apiResource(...), ... }` — the getters
 * declared below (`options`, `hasOptions`, `isBlank`) happen to read only
 * plain properties (`data`, `loading`, `error`), so this specific bug would
 * not visibly bite today, but the wrong composition is a landmine for the
 * next getter added here — and for the day someone reads `isEmpty`/`isReady`
 * off this component instead of `isBlank`.
 */
const at = (object, path) =>
    path.split('.').reduce((carry, key) => (carry == null ? undefined : carry[key]), object)

export default (endpoint, { valueKey = 'value', labelKey = 'label' } = {}) => apiResource(endpoint, [], {
    extra: {
        valueKey,
        labelKey,

        get options() {
            return (Array.isArray(this.data) ? this.data : [])
                .map((row) => ({
                    value: String(at(row, this.valueKey) ?? ''),
                    label: String(at(row, this.labelKey) ?? ''),
                }))
                .filter((option) => option.value !== '')
        },

        get hasOptions() {
            return this.options.length > 0
        },

        /** True once loading finished and the list came back empty. */
        get isBlank() {
            return !this.loading && !this.error && !this.hasOptions
        },
    },
})
