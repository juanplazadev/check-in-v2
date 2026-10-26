import RoleChips from '@/components/dashboard/RoleChips';
import { isStaffRole } from '@/types/staff';

/** A role's chip, or a plain label for driver, which has no staff chip. */
export default function RoleLabel({ name }: { name: string }) {
    if (isStaffRole(name)) {
        return <RoleChips roles={[name]} />;
    }

    return (
        <span className="text-xs font-semibold text-gray-500 capitalize dark:text-gray-400">
            {name}
        </span>
    );
}
