import type { StaffRole } from '@/types/staff';
import { STAFF_ROLE_LABELS } from '@/types/staff';

const ROLE_CLASSES: Record<StaffRole, string> = {
    admin: 'bg-violet-500/12 text-violet-700 dark:text-violet-300',
    'shipping-manager': 'bg-sky-500/12 text-sky-700 dark:text-sky-300',
    shipping: 'bg-brand-green/12 text-brand-green',
    sales: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
};

export default function RoleChips({ roles }: { roles: StaffRole[] }) {
    if (roles.length === 0) {
        return <span className="text-gray-400 dark:text-gray-500">Driver</span>;
    }

    return (
        <span className="inline-flex flex-wrap gap-1">
            {roles.map((role) => (
                <span
                    key={role}
                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${ROLE_CLASSES[role]}`}
                >
                    {STAFF_ROLE_LABELS[role]}
                </span>
            ))}
        </span>
    );
}
