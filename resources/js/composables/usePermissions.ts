import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PermissionKey } from '@/constants/permissions';

export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.auth.user?.permissions ?? []);

    const can = (permission: PermissionKey | string): boolean =>
        permissions.value.includes(permission);

    const roleLabel = computed(
        () => page.props.auth.user?.role_label ?? 'User',
    );

    return { permissions, can, roleLabel };
}
