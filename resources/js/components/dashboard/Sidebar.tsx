import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRightFromLine } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import type { DashboardCounts } from '@/components/dashboard/navigation';
import {
    DASHBOARD_NAVIGATION,
    isNavItemActive,
} from '@/components/dashboard/navigation';
import Logo from '@/components/Logo';
import { hasAnyRole, useStaffRoles } from '@/hooks/useStaffRoles';
import { dashboard } from '@/routes';

const EXPANDED_STORAGE_KEY = 'dashboard-sidebar-expanded';

interface SidebarProps {
    sidebarOpen: boolean;
    setSidebarOpen: (open: boolean) => void;
}

function readStoredExpanded(): boolean {
    try {
        return localStorage.getItem(EXPANDED_STORAGE_KEY) === 'true';
    } catch {
        return false;
    }
}

/**
 * Ported from Mosaic's Sidebar: a drawer below `lg`, an icon rail on `lg` that the driver can
 * expand (remembered per browser), and always expanded from `2xl`.
 */
export default function Sidebar({ sidebarOpen, setSidebarOpen }: SidebarProps) {
    const { url, props } = usePage<{ dashboard: DashboardCounts | null }>();
    const roles = useStaffRoles();
    const trigger = useRef<HTMLButtonElement>(null);
    const sidebar = useRef<HTMLDivElement>(null);
    const [sidebarExpanded, setSidebarExpanded] = useState(false);

    // Read after mount so the server render and the first client render agree.
    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setSidebarExpanded(readStoredExpanded());
    }, []);

    useEffect(() => {
        try {
            localStorage.setItem(EXPANDED_STORAGE_KEY, String(sidebarExpanded));
        } catch {
            // Storage can be unavailable (private mode); the rail just won't be remembered.
        }

        document.body.classList.toggle('sidebar-expanded', sidebarExpanded);

        return () => document.body.classList.remove('sidebar-expanded');
    }, [sidebarExpanded]);

    useEffect(() => {
        if (!sidebarOpen) {
            return;
        }

        const closeOnOutsideClick = ({ target }: MouseEvent) => {
            if (
                sidebar.current?.contains(target as Node) ||
                trigger.current?.contains(target as Node)
            ) {
                return;
            }

            setSidebarOpen(false);
        };
        const closeOnEscape = ({ key }: KeyboardEvent) => {
            if (key === 'Escape') {
                setSidebarOpen(false);
            }
        };

        document.addEventListener('click', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('click', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [sidebarOpen, setSidebarOpen]);

    const groups = DASHBOARD_NAVIGATION.map((group) => ({
        ...group,
        items: group.items.filter((item) => hasAnyRole(roles, item.roles)),
    })).filter((group) => group.items.length > 0);

    return (
        <div className="min-w-fit">
            {/* Backdrop (mobile only) */}
            <div
                className={`fixed inset-0 z-40 bg-gray-900/30 transition-opacity duration-200 lg:z-auto lg:hidden ${
                    sidebarOpen
                        ? 'opacity-100'
                        : 'pointer-events-none opacity-0'
                }`}
                aria-hidden="true"
            />

            <div
                id="sidebar"
                ref={sidebar}
                className={`absolute top-0 left-0 z-40 no-scrollbar flex h-dvh w-64 shrink-0 flex-col overflow-y-scroll rounded-r-2xl bg-white p-4 shadow-xs transition-all duration-200 ease-in-out lg:static lg:top-auto lg:left-auto lg:flex! lg:w-20 lg:translate-x-0 lg:overflow-y-auto 2xl:w-64! dark:bg-gray-800 lg:sidebar-expanded:w-64! ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-64'
                }`}
            >
                {/* Header */}
                <div className="mb-10 flex justify-between pr-3 sm:px-2">
                    <button
                        ref={trigger}
                        type="button"
                        className="text-gray-500 hover:text-gray-400 lg:hidden"
                        onClick={() => setSidebarOpen(!sidebarOpen)}
                        aria-controls="sidebar"
                        aria-expanded={sidebarOpen}
                    >
                        <span className="sr-only">Close sidebar</span>
                        <ArrowLeft className="size-6" />
                    </button>

                    <Link
                        href={dashboard.url()}
                        className="flex h-8 items-center"
                    >
                        {/* The wordmark is too wide for the collapsed rail, so the rail gets a monogram. */}
                        <Logo
                            size="md"
                            className="lg:hidden 2xl:block lg:sidebar-expanded:block"
                        />
                        <span
                            className="hidden size-8 items-center justify-center rounded-lg bg-brand-green text-sm font-bold text-white lg:flex 2xl:hidden lg:sidebar-expanded:hidden"
                            aria-hidden="true"
                        >
                            {(import.meta.env.VITE_APP_NAME ?? 'C').charAt(0)}
                        </span>
                    </Link>
                </div>

                {/* Links */}
                <div className="space-y-8">
                    {groups.map((group) => (
                        <div key={group.label}>
                            <h3 className="pl-3 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                                <span
                                    className="hidden w-6 text-center lg:block 2xl:hidden lg:sidebar-expanded:hidden"
                                    aria-hidden="true"
                                >
                                    •••
                                </span>
                                <span className="lg:hidden 2xl:block lg:sidebar-expanded:block">
                                    {group.label}
                                </span>
                            </h3>

                            <ul className="mt-3">
                                {group.items.map((item) => {
                                    const active = isNavItemActive(item, url);
                                    const badge =
                                        item.badgeKey !== undefined
                                            ? props.dashboard?.[item.badgeKey]
                                            : item.badge;
                                    const Icon = item.icon;

                                    return (
                                        <li
                                            key={item.href}
                                            className={`mb-0.5 rounded-lg bg-linear-to-r py-2 pr-3 pl-4 last:mb-0 ${
                                                active
                                                    ? 'from-brand-green/[0.12] to-brand-green/[0.04] dark:from-brand-green/[0.24]'
                                                    : ''
                                            }`}
                                        >
                                            <Link
                                                href={item.href}
                                                onClick={() =>
                                                    setSidebarOpen(false)
                                                }
                                                aria-current={
                                                    active ? 'page' : undefined
                                                }
                                                className={`block truncate text-gray-800 transition duration-150 dark:text-gray-100 ${
                                                    active
                                                        ? ''
                                                        : 'hover:text-gray-900 dark:hover:text-white'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between">
                                                    <div className="flex grow items-center">
                                                        <Icon
                                                            className={`size-4 shrink-0 ${
                                                                active
                                                                    ? 'text-brand-green'
                                                                    : 'text-gray-400 dark:text-gray-500'
                                                            }`}
                                                            strokeWidth={2.25}
                                                        />
                                                        <span className="ml-4 text-sm font-medium duration-200 lg:opacity-0 2xl:opacity-100 lg:sidebar-expanded:opacity-100">
                                                            {item.label}
                                                        </span>
                                                    </div>

                                                    {badge !== undefined &&
                                                        badge > 0 && (
                                                            <div className="ml-2 flex shrink-0 lg:hidden 2xl:flex lg:sidebar-expanded:flex">
                                                                <span className="inline-flex h-5 items-center justify-center rounded-sm bg-brand-green px-2 text-xs font-medium text-white">
                                                                    {badge}
                                                                </span>
                                                            </div>
                                                        )}
                                                </div>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ))}
                </div>

                {/* Expand / collapse */}
                <div className="mt-auto hidden justify-end pt-3 lg:inline-flex 2xl:hidden">
                    <div className="w-12 py-2 pr-3 pl-4">
                        <button
                            type="button"
                            className="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
                            onClick={() => setSidebarExpanded(!sidebarExpanded)}
                        >
                            <span className="sr-only">
                                Expand / collapse sidebar
                            </span>
                            <ArrowRightFromLine className="size-4 shrink-0 sidebar-expanded:rotate-180" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
