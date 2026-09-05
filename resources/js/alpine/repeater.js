import clone from '../clone'

/**
 * Dynamic row editor for child records edited alongside their parent — the
 * event rundown (context.md §4.11). Rows are submitted with the parent in one
 * request, so this only manages the in-memory list.
 *
 * `_key` exists because Alpine's :key needs something stable: a new row has no
 * id yet, and using the array index makes drag-and-drop re-render every row.
 */
let seq = 0

const withKey = (row) => ({ _key: `r${++seq}`, ...row })

export default (initial = [], blank = {}) => ({
    rows: (Array.isArray(initial) ? initial : []).map(withKey),
    blank,

    get isEmpty() {
        return this.rows.length === 0
    },

    add() {
        this.rows.push(withKey(clone(this.blank)))
    },

    remove(index) {
        this.rows.splice(index, 1)
    },

    duplicate(index) {
        const { _key, id, ...rest } = this.rows[index]
        this.rows.splice(index + 1, 0, withKey(clone(rest)))
    },

    move(from, to) {
        if (to < 0 || to >= this.rows.length) return
        const [row] = this.rows.splice(from, 1)
        this.rows.splice(to, 0, row)
    },

    /**
     * Laravel returns nested errors indexed by position (`rundowns.0.time`), so
     * each row can show its own message (context.md §4.11).
     */
    rowError(errors, field, index) {
        const key = `${field}.${index}`
        const match = Object.keys(errors ?? {}).find((k) => k.startsWith(`${key}.`) || k === key)
        if (!match) return null

        const value = errors[match]
        return Array.isArray(value) ? value[0] : value
    },

    hasRowError(errors, field, index) {
        return this.rowError(errors, field, index) !== null
    },

    /** Strips the client-only key before the payload leaves the browser. */
    payload() {
        return this.rows.map(({ _key, ...row }) => row)
    },
})
