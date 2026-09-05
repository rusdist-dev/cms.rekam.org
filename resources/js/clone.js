/**
 * Deep-clones plain, JSON-safe data — form state, repeater rows, and the like.
 *
 * `structuredClone()` looks like the obvious choice but throws
 * `DataCloneError: #<Object> could not be cloned` the moment the value has
 * passed through Alpine's reactivity system: once a property is read off a
 * component's `x-data`, it comes back wrapped in Alpine's reactive Proxy, and
 * structuredClone refuses to serialize that exotic object even though its
 * contents are plain data. JSON.stringify does not have this problem — Alpine
 * itself relies on that same fact for deep-watching (see reactivity.js) — so a
 * stringify/parse round trip is what actually works here.
 *
 * Only ever call this on data that is genuinely JSON-safe (our form state
 * always is: it is either posted as JSON or flattened into FormData). A Date,
 * Map, or File would silently come out wrong.
 */
export default function clone(value) {
    return JSON.parse(JSON.stringify(value))
}
