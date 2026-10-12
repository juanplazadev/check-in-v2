import { ShieldAlert } from 'lucide-react';
import type { ReactNode } from 'react';

import { useCan } from '@/hooks/useCan';
import type { Permission } from '@/types/auth';

/**
 * Hides a screen from staff without the permission. This is presentation only: the route enforces
 * the same permission, since anyone can request the page directly.
 */
export default function RequirePermission({
    permission,
    children,
}: {
    permission: Permission;
    children: ReactNode;
}) {
    const can = useCan();

    if (can(permission)) {
        return children;
    }

    return (
        <div className="flex flex-col items-center rounded-xl bg-white px-5 py-16 text-center shadow-xs dark:bg-gray-800">
            <span className="mb-4 flex size-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                <ShieldAlert className="size-6 text-gray-400" />
            </span>
            <h2 className="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-100">
                No access
            </h2>
            <p className="text-sm">
                Ask an administrator if you need access to this page.
            </p>
        </div>
    );
}
