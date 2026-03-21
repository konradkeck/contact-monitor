import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function usePermissions() {
    const page = usePage()

    const permissions = computed(() => page.props.auth?.permissions ?? {})

    function can(permission) {
        return permissions.value[permission] ?? false
    }

    return { can, permissions }
}
