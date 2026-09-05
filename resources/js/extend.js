/**
 * Adds `extra`'s properties onto `base`, keeping any getters on either side
 * genuinely live.
 *
 * `{ ...base, ...extra }` and `Object.assign(base, extra)` both read every
 * property via `[[Get]]` — so a getter on `base` (or on `extra`) is invoked
 * exactly once, right then, and what lands on the result is the plain value it
 * returned at that instant, not the getter itself. For a factory like
 * resourceTable's `isEmpty` — a getter that must keep re-evaluating
 * `this.items.length` after every fetch — that is fatal: it freezes to
 * whatever `items` was at construction (before the first `load()` has even
 * run), and never changes again. The table's empty state then never leaves,
 * no matter how much data arrives.
 *
 * `Object.getOwnPropertyDescriptors` reads the *descriptor* instead — for an
 * accessor property that is `{ get, set, enumerable, configurable }`, with the
 * getter function itself, never invoked. `Object.defineProperties` installs
 * that descriptor as a real accessor on `base`, so it keeps recomputing on
 * every access, exactly like it would if it had been written directly into
 * `base`'s own object literal.
 *
 * Used by every wrapper factory (contentForm, roleForm, tenantForm, teamBoard)
 * to add page-specific properties — including their own getters — onto a base
 * factory (resourceForm, resourceTable, sortableList) without this bug.
 */
export default function extend(base, extra = {}) {
    return Object.defineProperties(base, Object.getOwnPropertyDescriptors(extra))
}
