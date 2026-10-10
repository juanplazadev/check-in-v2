import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Home, LogOut, Settings } from 'lucide-react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useStaffRoles } from '@/hooks/useStaffRoles';
import { initialsOf } from '@/lib/dashboard/format';
import { home, logout } from '@/routes';
import { settings as accountSettings } from '@/routes/account';
import type { Auth } from '@/types/auth';
import { STAFF_ROLE_LABELS } from '@/types/staff';

/** Mosaic's DropdownProfile, built on the app's Radix dropdown. */
export default function ProfileMenu() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const roles = useStaffRoles();
    const roleLabel = roles.map((role) => STAFF_ROLE_LABELS[role]).join(', ');

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="group inline-flex items-center justify-center"
                >
                    <span className="flex size-8 items-center justify-center rounded-full bg-linear-to-br from-brand-green to-green-600 text-xs font-semibold text-white">
                        {initialsOf(auth.user.name)}
                    </span>
                    <div className="flex items-center truncate">
                        <span className="ml-2 truncate text-sm font-medium text-gray-600 group-hover:text-gray-800 dark:text-gray-100 dark:group-hover:text-white">
                            {auth.user.name}
                        </span>
                        <ChevronDown className="ml-1 size-3 shrink-0 text-gray-400 dark:text-gray-500" />
                    </div>
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="min-w-44">
                <div className="mb-1 border-b border-gray-200 px-2.5 pt-0.5 pb-2 dark:border-gray-700/60">
                    <div className="font-medium text-gray-800 dark:text-gray-100">
                        {auth.user.name}
                    </div>
                    <div className="text-xs text-gray-500 italic dark:text-gray-400">
                        {roleLabel}
                    </div>
                </div>
                <DropdownMenuItem asChild>
                    <Link href={accountSettings.url()}>
                        <Settings />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={home.url()}>
                        <Home />
                        Driver site
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => router.post(logout.url())}>
                    <LogOut />
                    Sign out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
