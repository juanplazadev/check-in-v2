import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    History,
    IdCard,
    LayoutGrid,
    MailWarning,
    Settings,
    UserRound,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import AppLayout from '@/layouts/AppLayout';
import { account } from '@/routes';
import {
    history as accountHistory,
    profile as accountProfile,
    settings as accountSettings,
} from '@/routes/account';
import { notice as verificationNotice } from '@/routes/verification';

// ── Types ────────────────────────────────────────────────────────────────────

interface AccountNavTranslations {
    overview: string;
    history: string;
    profile: string;
    settings: string;
    emailVerified: string;
    emailUnverified: string;
    verifyNow: string;
    licenseOnFile: string;
}

interface PageProps {
    auth: {
        user: {
            name: string;
            email: string;
            email_verified_at?: string | null;
        };
    };
    hasLicense?: boolean;
    translations: { accountNav: AccountNavTranslations };
    [key: string]: unknown;
}

interface Tab {
    key: string;
    href: string;
    label: string;
    icon: ComponentType<{ className?: string }>;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function initialsOf(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function Chip({
    icon: Icon,
    label,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
}) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-brand-green/10 px-2.5 py-1 text-xs font-medium text-brand-green">
            <Icon className="h-3.5 w-3.5" />
            {label}
        </span>
    );
}

// ── Layout ───────────────────────────────────────────────────────────────────

export default function AccountLayout({ children }: { children: ReactNode }) {
    const page = usePage<PageProps>();
    const { auth, hasLicense = false, translations } = page.props;
    const t = translations.accountNav;

    const path = page.url.split('?')[0];

    // Longest prefix first: /account/history must not fall through to overview.
    // Settings is not a tab, so it deliberately matches none of them rather
    // than leaving Overview lit while you are on another page.
    const activeKey = path.startsWith('/account/history')
        ? 'history'
        : path.startsWith('/account/profile')
          ? 'profile'
          : path.startsWith('/account/settings')
            ? 'settings'
            : 'overview';

    const settingsActive = activeKey === 'settings';
    const verified = Boolean(auth.user.email_verified_at);

    // Profile is the only account page reachable before the email is verified
    // (it is the escape hatch for a mistyped address), so while unverified the
    // other tabs would every one of them bounce off the verified middleware.
    const tabs: Tab[] = (
        [
            {
                key: 'overview',
                href: account().url,
                label: t.overview,
                icon: LayoutGrid,
            },
            {
                key: 'history',
                href: accountHistory().url,
                label: t.history,
                icon: History,
            },
            {
                key: 'profile',
                href: accountProfile().url,
                label: t.profile,
                icon: UserRound,
            },
        ] satisfies Tab[]
    ).filter((tab) => verified || tab.key === 'profile');

    return (
        <AppLayout>
            {/* Identity header */}
            <div className="relative overflow-hidden">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-16 left-1/2 h-56 w-[36rem] max-w-full -translate-x-1/2 rounded-full bg-brand-green/10 blur-3xl dark:bg-brand-green/[0.07]"
                />
                <div className="relative mx-auto max-w-2xl px-6 pt-8 pb-5">
                    <div className="flex items-center gap-4">
                        <Avatar className="size-14 shadow-sm ring-2 ring-white dark:ring-gray-800">
                            <AvatarFallback className="bg-gradient-to-br from-brand-green to-green-600 text-lg font-semibold text-white">
                                {initialsOf(auth.user.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <h1 className="truncate text-2xl font-bold tracking-tight text-brand-grey dark:text-gray-50">
                                {auth.user.name}
                            </h1>
                            <p className="truncate text-sm text-gray-500 dark:text-gray-400">
                                {auth.user.email}
                            </p>
                        </div>

                        {/* Settings lives here rather than as a 4th tab: a
                            fourth segment drops each tab to ~78px at 375px,
                            which clips the label. Hidden while unverified for
                            the same reason as the tabs above: it is gated. */}
                        {verified && (
                            <Link
                                href={accountSettings().url}
                                aria-label={t.settings}
                                aria-current={
                                    settingsActive ? 'page' : undefined
                                }
                                className={`flex size-11 shrink-0 items-center justify-center rounded-full transition-colors ${
                                    settingsActive
                                        ? 'bg-brand-green/10 text-brand-green'
                                        : 'text-gray-500 hover:bg-gray-100 hover:text-brand-grey dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200'
                                }`}
                            >
                                <Settings className="h-5 w-5" />
                            </Link>
                        )}
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {verified ? (
                            <Chip icon={BadgeCheck} label={t.emailVerified} />
                        ) : (
                            /* Actionable rather than decorative: this is the
                               one place an unverified driver can get back to
                               the resend screen without signing out. */
                            <Link
                                href={verificationNotice().url}
                                className="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 transition-colors hover:bg-amber-500/20 dark:text-amber-400"
                            >
                                <MailWarning className="h-3.5 w-3.5" />
                                {t.emailUnverified}
                                <span className="font-semibold underline underline-offset-2">
                                    {t.verifyNow}
                                </span>
                            </Link>
                        )}
                        {hasLicense && (
                            <Chip icon={IdCard} label={t.licenseOnFile} />
                        )}
                    </div>
                </div>
            </div>

            {/* Sticky segmented sub-nav */}
            <div className="sticky top-15 z-30 border-b border-gray-200/70 bg-white/90 backdrop-blur dark:border-gray-700/50 dark:bg-gray-900/90">
                <div className="mx-auto max-w-2xl px-6">
                    <nav className="py-2.5">
                        <div className="flex gap-1 rounded-2xl bg-gray-100/80 p-1 dark:bg-gray-800/50">
                            {tabs.map((tab) => {
                                const active = tab.key === activeKey;
                                const Icon = tab.icon;

                                return (
                                    <Link
                                        key={tab.key}
                                        href={tab.href}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={`flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-xl px-2 py-2 text-[13px] font-semibold transition-colors sm:gap-2 sm:px-4 sm:text-sm ${
                                            active
                                                ? 'bg-brand-green text-white shadow-sm shadow-brand-green/25'
                                                : 'text-gray-500 hover:text-brand-grey dark:text-gray-400 dark:hover:text-gray-200'
                                        }`}
                                    >
                                        <Icon className="h-4 w-4 shrink-0" />
                                        <span className="truncate">
                                            {tab.label}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    </nav>
                </div>
            </div>

            {children}
        </AppLayout>
    );
}
