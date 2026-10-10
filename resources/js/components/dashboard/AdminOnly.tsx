import { ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';

import { hasAnyRole, useStaffRoles } from '@/hooks/useStaffRoles';

/**
 * Hides admin screens from other staff in the UI. This is presentation only: the backend pass
 * must enforce the same rule with route middleware, since anyone can request the page directly.
 */
export default function AdminOnly({ children }: { children: ReactNode }) {
    const roles = useStaffRoles();

    if (hasAnyRole(roles, ['admin'])) {
        return children;
    }

    return (
        <div className="flex flex-col items-center rounded-xl bg-white px-5 py-16 text-center shadow-xs dark:bg-gray-800">
            <span className="mb-4 flex size-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                <ShieldAlert className="size-6 text-gray-400" />
            </span>
            <h2 className="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-100">
                Admins only
            </h2>
            <p className="text-sm">
                Ask an administrator if you need access to this page.
            </p>
        </div>
    );
}
