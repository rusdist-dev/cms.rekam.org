/**
 * Sidebar shell state (context.md §7.4): collapse to icon-only, per-group
 * open/close, and a drawer below `lg`. Everything here is UI state, so it lives
 * in localStorage — never on the server (context.md §1.5).
 */

const KEY_COLLAPSED = 'cms.sidebar.collapsed'
const KEY_GROUPS = 'cms.sidebar.groups'

const read = (key, fallback) => {
    try {
        const raw = localStorage.getItem(key)
        return raw === null ? fallback : JSON.parse(raw)
    } catch {
        return fallback
    }
}

const write = (key, value) => {
    try {
        localStorage.setItem(key, JSON.stringify(value))
    } catch {
        // Private mode or a full quota — the UI still works, it just forgets.
    }
}

export default (openGroups = []) => ({
    collapsed: false,
    drawerOpen: false,
    groups: {},

    init() {
        this.collapsed = read(KEY_COLLAPSED, false)

        // Groups holding the active route start open; the stored value wins
        // afterwards so a deliberate collapse survives navigation.
        const stored = read(KEY_GROUPS, null)
        this.groups = stored ?? Object.fromEntries(openGroups.map((g) => [g, true]))

        for (const g of openGroups) {
            if (!(g in this.groups)) this.groups[g] = true
        }

        // A resize past the breakpoint must not leave the drawer stuck open.
        this.$watch('collapsed', (v) => write(KEY_COLLAPSED, v))
        this.$watch('groups', (v) => write(KEY_GROUPS, v), { deep: true })
    },

    toggleCollapse() {
        this.collapsed = !this.collapsed
        // An icon-only rail has no room for group labels, so collapsing closes
        // the flyouts rather than leaving them half-rendered.
        if (this.collapsed) this.drawerOpen = false
    },

    toggleGroup(name) {
        if (this.collapsed) {
            // Expand first: a group cannot be read in icon-only mode.
            this.collapsed = false
            this.groups[name] = true
            return
        }
        this.groups[name] = !this.groups[name]
    },

    isGroupOpen(name) {
        return this.collapsed ? false : this.groups[name] !== false
    },

    openDrawer() {
        this.drawerOpen = true
    },

    closeDrawer() {
        this.drawerOpen = false
    },

    get railWidth() {
        return this.collapsed ? 'lg:w-sidebar-collapsed' : 'lg:w-sidebar'
    },

    get contentOffset() {
        return this.collapsed ? 'lg:pl-sidebar-collapsed' : 'lg:pl-sidebar'
    },
})
