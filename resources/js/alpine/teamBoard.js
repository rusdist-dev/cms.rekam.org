import sortableList from './sortableList'

/**
 * Team index: members bucketed into levels, reorderable inside a level.
 *
 * Levels are taxonomy from site_settings, so they are fetched rather than
 * hardcoded (context.md §5.12) — which means two loads have to settle before
 * the board can render, and both need their own error handling.
 *
 * Composed through sortableList's `options.extra`, not `{ ...sortableList(...),
 * ...}` — see sortableList.js and resources/js/extend.js for why the spread
 * form freezes sortableList's `isEmpty`/`isReady` getters into dead values.
 * This page does not read those two directly (it defines its own `busy` /
 * `ready` / `blank` / `failed` below), but composing safely here keeps that
 * true rather than merely coincidental.
 */
export default (membersEndpoint, levelsEndpoint, options = {}) => sortableList(membersEndpoint, [], {
    groupKey: 'group',
    autoload: false,
    extra: {
        // Page-specific extras (editUrl, ...) flow through here rather than
        // being spread onto teamBoard's return value in Blade — see the
        // module doc comment above.
        ...options.extra,

        levels: [],
        levelsLoading: false,
        levelsError: null,

        init() {
            this.load()
            this.loadLevels()
        },

        get busy() {
            return this.loading || this.levelsLoading
        },

        get failed() {
            return !this.busy && (this.error || this.levelsError)
        },

        get ready() {
            return !this.busy && !this.failed && this.items.length > 0
        },

        get blank() {
            return !this.busy && !this.failed && this.items.length === 0
        },

        async loadLevels() {
            this.levelsLoading = true
            this.levelsError = null

            try {
                const res = await window.api.get(levelsEndpoint)
                this.levels = res.data ?? []
            } catch (e) {
                this.levelsError = e.message
            } finally {
                this.levelsLoading = false
            }
        },

        retry() {
            this.load()
            this.loadLevels()
        },

        /** Members whose level was removed from the taxonomy must stay visible. */
        get orphans() {
            const known = this.levels.map((l) => l.value)
            return this.items.filter((i) => !known.includes(i.group))
        },
    },
})
