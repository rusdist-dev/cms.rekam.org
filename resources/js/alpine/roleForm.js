import resourceForm from './resourceForm'

/**
 * Role editor: the role itself plus the permission matrix it grants.
 *
 * The matrix is fetched rather than hardcoded so a permission added by a
 * seeder appears here without touching the view.
 *
 * Composed through resourceForm's `options.extra`, not `{ ...resourceForm(...),
 * ...}` — that would flatten resourceForm's `isEditing`/`isDirty`/`hasErrors`
 * getters (and, recursively, this file's own `grantedCount` getter below) into
 * dead, frozen values. See resourceForm.js and resources/js/extend.js.
 */
export default (endpoint, permissionsEndpoint, options = {}) => resourceForm(endpoint, { name: '', permissions: [] }, {
    ...options,
    extra: {
        modules: [],
        matrixLoading: false,
        matrixError: null,

        async init() {
            // resourceForm's own init loads the record being edited; both
            // fetches run together so the form is not blocked on the slower one.
            await Promise.all([
                options.recordId ? this.load() : Promise.resolve(),
                this.loadMatrix(),
            ])
        },

        async loadMatrix() {
            this.matrixLoading = true
            this.matrixError = null

            try {
                const res = await window.api.get(permissionsEndpoint)
                this.modules = res.data ?? []
            } catch (e) {
                this.matrixError = e.message
            } finally {
                this.matrixLoading = false
            }
        },

        has(permission) {
            return this.form.permissions.includes(permission)
        },

        toggle(permission) {
            this.form.permissions = this.has(permission)
                ? this.form.permissions.filter((p) => p !== permission)
                : [...this.form.permissions, permission]
        },

        /** Row-level "select all" for a module, the way people actually grant access. */
        moduleState(module) {
            const names = module.actions.map((a) => a.name)
            const granted = names.filter((n) => this.has(n)).length

            if (granted === 0) return 'none'

            return granted === names.length ? 'all' : 'some'
        },

        toggleModule(module) {
            const names = module.actions.map((a) => a.name)

            this.form.permissions = this.moduleState(module) === 'all'
                ? this.form.permissions.filter((p) => !names.includes(p))
                : [...new Set([...this.form.permissions, ...names])]
        },

        get grantedCount() {
            return this.form.permissions.length
        },
    },
})
