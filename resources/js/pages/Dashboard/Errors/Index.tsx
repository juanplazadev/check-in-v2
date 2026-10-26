import { Deferred, Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownUp,
    Bug,
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CircleSlash,
    ExternalLink,
    Gauge,
    Inbox,
    Layers,
    Search,
    Sparkles,
    Users,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import Card from '@/components/dashboard/Card';
import {
    CHART_SERIES,
    withOpacity,
} from '@/components/dashboard/charts/chartjs-config';
import LineChart from '@/components/dashboard/charts/LineChart';
import LevelBadge from '@/components/dashboard/errors/LevelBadge';
import SentryState from '@/components/dashboard/errors/SentryState';
import Sparkline from '@/components/dashboard/errors/Sparkline';
import type {
    Comparison,
    ErrorsReport,
    IssuePage,
    IssueRow,
    SentryPayload,
} from '@/components/dashboard/errors/types';
import PageHeader from '@/components/dashboard/PageHeader';
import { MENU_CLASSES } from '@/components/dashboard/RecordActionDialogs';
import StatCard from '@/components/dashboard/StatCard';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatDate, formatTimeAgo } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

/** Mirrors App\Enums\ErrorPeriod. */
type ErrorPeriod = '24h' | '7d' | '14d' | '30d';

/** Mirrors App\Enums\ErrorIssueTab. */
type IssueTab = 'unresolved' | 'new' | 'regressed' | 'resolved';

/** Mirrors App\Enums\ErrorIssueSort. */
type IssueSort = 'date' | 'new' | 'freq' | 'user';

interface Filters {
    period: ErrorPeriod;
    tab: IssueTab;
    sort: IssueSort;
    environment: string | null;
    search: string;
    cursor: string | null;
}

interface PeriodOption {
    value: ErrorPeriod;
    label: string;
    previousLabel: string;
}

interface ErrorsProps {
    filters: Filters;
    periods: PeriodOption[];
    tabs: { value: IssueTab; label: string }[];
    sorts: { value: IssueSort; label: string }[];
    sentryUrl: string | null;
    report?: SentryPayload<ErrorsReport>;
    issues?: SentryPayload<IssuePage>;
}

type Tone = 'positive' | 'negative' | 'neutral';

const SELECT_BUTTON =
    'btn justify-between gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-800 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:text-gray-100';

/** What each dropped outcome means, as Sentry's stats page explains it. */
const DROPPED_LABELS: Record<string, { label: string; hint: string }> = {
    filtered: {
        label: 'Filtered',
        hint: 'Dropped by inbound filters, such as known crawlers or legacy browsers.',
    },
    rate_limited: {
        label: 'Rate limited',
        hint: 'Over the quota or a spike-protection limit.',
    },
    invalid: { label: 'Invalid', hint: 'Malformed or too large to store.' },
    abuse: { label: 'Abuse', hint: 'Refused by abuse protection.' },
    client_discard: {
        label: 'Client discard',
        hint: 'Dropped by the SDK before sending, such as by sampling or a full queue.',
    },
    cardinality_limited: {
        label: 'Cardinality limited',
        hint: 'Over a metric cardinality limit.',
    },
};

const COMPACT = new Intl.NumberFormat(undefined, {
    notation: 'compact',
    maximumFractionDigits: 1,
});

function formatCount(value: number): string {
    return value < 10_000 ? value.toLocaleString() : COMPACT.format(value);
}

/**
 * Reloads the page with the given filters. The headline report depends only
 * on the period and environment, so other changes reload just the issues.
 */
function visit(filters: Filters, changes: Partial<Filters>) {
    const next = { ...filters, cursor: null, ...changes };
    const reportChanged =
        next.period !== filters.period ||
        next.environment !== filters.environment;

    router.get(
        dashboardRoutes.errors.url(),
        {
            ...(next.period !== '14d' && { period: next.period }),
            ...(next.tab !== 'unresolved' && { tab: next.tab }),
            ...(next.sort !== 'date' && { sort: next.sort }),
            ...(next.environment && { environment: next.environment }),
            ...(next.search && { search: next.search }),
            ...(next.cursor && { cursor: next.cursor }),
        },
        {
            only: reportChanged
                ? ['filters', 'report', 'issues']
                : ['filters', 'issues'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

/** The change against the previous period; for errors, fewer is better. */
function deltaOf(
    { value, previous }: Comparison<number>,
    previousLabel?: string,
): { text: string; tone: Tone } | undefined {
    if (previous === 0) {
        return undefined;
    }

    const change = Math.round(((value - previous) / previous) * 100);
    const tone: Tone =
        change === 0 ? 'neutral' : change < 0 ? 'positive' : 'negative';

    return {
        text: `${change > 0 ? '+' : ''}${change}%${previousLabel ? ` vs ${previousLabel}` : ''}`,
        tone,
    };
}

function Select<T extends string>({
    icon: Icon,
    label,
    options,
    value,
    onChange,
    className = 'min-w-0 sm:min-w-44',
}: {
    icon: typeof CalendarDays;
    label: string;
    options: { value: T; label: string }[];
    value: T;
    onChange: (value: T) => void;
    className?: string;
}) {
    const selected = options.find((option) => option.value === value);

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={`${SELECT_BUTTON} ${className}`}
                    aria-label={label}
                >
                    <span className="flex items-center truncate">
                        <Icon className="mr-2 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
                        <span className="truncate">{selected?.label}</span>
                    </span>
                    <ChevronDown className="size-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className={`w-48 ${MENU_CLASSES}`}>
                {options.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        className={`rounded-md ${option.value === value ? 'font-semibold text-brand-green' : ''}`}
                        onSelect={() => onChange(option.value)}
                    >
                        {option.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function SkeletonBlock({
    className = '',
    height,
}: {
    className?: string;
    height: number;
}) {
    return (
        <div
            className={`animate-pulse rounded-xl bg-white p-5 shadow-xs dark:bg-gray-800 ${className}`}
        >
            <div className="mb-4 h-4 w-1/3 rounded bg-gray-200 dark:bg-gray-700" />
            <div
                className="rounded-lg bg-gray-100 dark:bg-gray-700/50"
                style={{ height }}
            />
        </div>
    );
}

function ReportSkeleton() {
    return (
        <div className="mb-6 grid grid-cols-12 gap-6" aria-busy="true">
            <div className="col-span-full grid grid-cols-2 gap-6 lg:grid-cols-3 2xl:grid-cols-6">
                {Array.from({ length: 6 }, (_, index) => (
                    <SkeletonBlock key={index} height={56} />
                ))}
            </div>
            <SkeletonBlock
                className="col-span-full xl:col-span-8"
                height={248}
            />
            <SkeletonBlock
                className="col-span-full xl:col-span-4"
                height={248}
            />
        </div>
    );
}

function IssuesSkeleton() {
    return (
        <div
            className="animate-pulse divide-y divide-gray-100 dark:divide-gray-700/60"
            aria-busy="true"
        >
            {Array.from({ length: 6 }, (_, index) => (
                <div key={index} className="flex items-center gap-4 px-5 py-4">
                    <div className="size-2.5 rounded-full bg-gray-200 dark:bg-gray-700" />
                    <div className="flex-1 space-y-2">
                        <div className="h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700" />
                        <div className="h-3 w-1/3 rounded bg-gray-100 dark:bg-gray-700/50" />
                    </div>
                    <div className="hidden h-6 w-24 rounded bg-gray-100 sm:block dark:bg-gray-700/50" />
                </div>
            ))}
        </div>
    );
}

function QuotaCard({ quota }: { quota: ErrorsReport['quota'] }) {
    const percent = Math.min(100, quota.percent);
    const tone =
        quota.percent >= 90
            ? { bar: 'bg-rose-500', text: 'text-rose-600 dark:text-rose-400' }
            : quota.percent >= 75
              ? {
                    bar: 'bg-amber-400',
                    text: 'text-amber-600 dark:text-amber-400',
                }
              : {
                    bar: 'bg-brand-green',
                    text: 'text-green-700 dark:text-green-400',
                };

    return (
        <div className="col-span-2 flex flex-col rounded-xl bg-white p-5 shadow-xs lg:col-span-1 dark:bg-gray-800">
            <header className="mb-2 flex items-start justify-between">
                <h2 className="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-100">
                    Quota
                </h2>
                <span className="flex size-9 items-center justify-center rounded-full bg-brand-green/12 text-brand-green">
                    <Gauge className="size-4" />
                </span>
            </header>
            <div className="mb-1 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                Resets in {quota.daysUntilReset}{' '}
                {quota.daysUntilReset === 1 ? 'day' : 'days'}
            </div>
            <div className="mb-3 flex items-baseline gap-1.5">
                <span className={`text-3xl font-bold ${tone.text}`}>
                    {quota.percent}%
                </span>
                <span className="text-sm text-gray-500 dark:text-gray-400">
                    used
                </span>
            </div>
            <div
                className="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700/60"
                role="meter"
                aria-valuemin={0}
                aria-valuemax={quota.limit}
                aria-valuenow={quota.used}
                aria-label="Error quota used"
            >
                <div
                    className={`h-full rounded-full transition-all ${tone.bar}`}
                    style={{ width: `${percent}%` }}
                />
            </div>
            <div className="mt-2 text-xs text-gray-500 tabular-nums dark:text-gray-400">
                {quota.used.toLocaleString()} of {quota.limit.toLocaleString()}{' '}
                errors since {formatDate(quota.periodStart)}
            </div>
        </div>
    );
}

function OutcomeBreakdown({ summary }: { summary: ErrorsReport['summary'] }) {
    const total = summary.errors.value + summary.dropped.value;
    const rows = [
        {
            key: 'accepted',
            label: 'Accepted',
            hint: 'Stored and grouped into issues.',
            count: summary.errors.value,
            color: CHART_SERIES.rose,
        },
        ...Object.entries(summary.dropped.breakdown).map(([key, count]) => ({
            key,
            label: DROPPED_LABELS[key]?.label ?? key,
            hint: DROPPED_LABELS[key]?.hint ?? '',
            count,
            color: CHART_SERIES.gray,
        })),
    ];

    if (total === 0) {
        return (
            <div className="flex h-62 flex-col items-center justify-center gap-2 px-5 text-center text-sm text-gray-400 dark:text-gray-500">
                <Sparkles className="size-5 text-brand-green" />
                No errors in this period.
            </div>
        );
    }

    return (
        <ul className="space-y-4 px-5 py-4">
            {rows.map((row) => {
                const share = Math.round((row.count / total) * 1000) / 10;

                return (
                    <li key={row.key}>
                        <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                            <span className="font-medium text-gray-800 dark:text-gray-100">
                                {row.label}
                            </span>
                            <span className="text-gray-500 tabular-nums dark:text-gray-400">
                                {row.count.toLocaleString()}{' '}
                                <span className="text-xs">({share}%)</span>
                            </span>
                        </div>
                        <div className="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700/60">
                            <div
                                className="h-full rounded-full"
                                style={{
                                    width: `${share}%`,
                                    backgroundColor: row.color,
                                }}
                            />
                        </div>
                        {row.hint && (
                            <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                {row.hint}
                            </p>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

function Report({
    report,
    period,
    environment,
    reloading,
}: {
    report: ErrorsReport;
    period: PeriodOption;
    environment: string | null;
    reloading: boolean;
}) {
    const { summary, volume, quota } = report;
    const unit = volume.interval === '1h' ? 'hour' : 'day';

    // Memoized because the chart rebuilds whenever its series change identity.
    const volumeSeries = useMemo(
        () => [
            {
                label: 'Errors',
                color: CHART_SERIES.rose,
                fill: true,
                points: volume.labels.map((label, index): [string, number] => [
                    label,
                    volume.accepted[index],
                ]),
            },
            {
                label: 'Dropped',
                color: CHART_SERIES.gray,
                points: volume.labels.map((label, index): [string, number] => [
                    label,
                    volume.dropped[index],
                ]),
            },
        ],
        [volume],
    );

    return (
        <div
            className={`mb-6 grid grid-cols-12 gap-6 transition-opacity ${reloading ? 'opacity-60' : ''}`}
        >
            <div className="col-span-full grid grid-cols-2 gap-6 lg:grid-cols-3 2xl:grid-cols-6">
                <StatCard
                    className="col-span-2 lg:col-span-1"
                    title="Errors"
                    label={period.label}
                    value={formatCount(summary.errors.value)}
                    icon={Bug}
                    delta={deltaOf(summary.errors, period.previousLabel)}
                />
                <StatCard
                    className=""
                    title="Users"
                    label={
                        summary.usersAffected.exact
                            ? 'Affected'
                            : 'Most in one issue'
                    }
                    value={formatCount(summary.usersAffected.value)}
                    icon={Users}
                />
                <StatCard
                    className=""
                    title="Unresolved"
                    label="Open issues"
                    value={formatCount(summary.unresolved)}
                    icon={Inbox}
                />
                <StatCard
                    className=""
                    title="New issues"
                    label={`First seen, ${period.label.toLowerCase()}`}
                    value={formatCount(summary.newIssues)}
                    icon={Sparkles}
                />
                <StatCard
                    className=""
                    title="Dropped"
                    label="Not stored"
                    value={formatCount(summary.dropped.value)}
                    icon={CircleSlash}
                    delta={deltaOf(summary.dropped)}
                />
                <QuotaCard quota={quota} />
            </div>

            <Card
                title="Event volume"
                className="col-span-full xl:col-span-8"
                action={
                    <ul className="flex flex-wrap gap-x-4 gap-y-1">
                        {[
                            { label: 'Errors', color: CHART_SERIES.rose },
                            {
                                label: 'Dropped',
                                color: withOpacity(CHART_SERIES.gray, 0.9),
                            },
                        ].map((item) => (
                            <li
                                key={item.label}
                                className="flex items-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                <span
                                    className="mr-1.5 block size-2 rounded-full"
                                    style={{ backgroundColor: item.color }}
                                />
                                {item.label}
                            </li>
                        ))}
                    </ul>
                }
            >
                <LineChart series={volumeSeries} unit={unit} height={248} />
                {environment && (
                    <p className="px-5 pb-4 text-xs text-gray-400 dark:text-gray-500">
                        Volume and quota cover every environment; Sentry does
                        not split outcomes by environment.
                    </p>
                )}
            </Card>

            <Card
                title="Where events went"
                className="col-span-full xl:col-span-4"
            >
                <OutcomeBreakdown summary={summary} />
            </Card>
        </div>
    );
}

function IssueBadges({ issue }: { issue: IssueRow }) {
    return (
        <>
            {issue.isUnhandled && (
                <span className="rounded-full bg-rose-500/12 px-2 py-0.5 text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                    Unhandled
                </span>
            )}
            {issue.substatus === 'new' && (
                <span className="rounded-full bg-sky-500/12 px-2 py-0.5 text-[11px] font-semibold text-sky-600 dark:text-sky-400">
                    New
                </span>
            )}
            {issue.substatus === 'regressed' && (
                <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                    Regressed
                </span>
            )}
            {issue.substatus === 'escalating' && (
                <span className="rounded-full bg-red-500/15 px-2 py-0.5 text-[11px] font-semibold text-red-600 dark:text-red-400">
                    Escalating
                </span>
            )}
        </>
    );
}

function IssueList({
    page,
    filters,
    reloading,
}: {
    page: IssuePage;
    filters: Filters;
    reloading: boolean;
}) {
    if (page.rows.length === 0) {
        return (
            <div className="flex flex-col items-center gap-2 px-5 py-16 text-center">
                <span className="flex size-12 items-center justify-center rounded-full bg-brand-green/12 text-brand-green">
                    <Sparkles className="size-5" />
                </span>
                <p className="font-semibold text-gray-800 dark:text-gray-100">
                    Nothing here
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    No issues match{' '}
                    <code className="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-700">
                        {page.query}
                    </code>{' '}
                    in this period.
                </p>
            </div>
        );
    }

    return (
        <div className={`transition-opacity ${reloading ? 'opacity-60' : ''}`}>
            <div className="overflow-x-auto">
                <table className="w-full table-auto dark:text-gray-300">
                    <thead className="bg-gray-50 text-xs text-gray-400 uppercase dark:bg-gray-700/50 dark:text-gray-500">
                        <tr>
                            <th className="w-full px-5 py-2 text-left font-semibold">
                                Issue
                            </th>
                            <th className="hidden px-2 py-2 text-left font-semibold md:table-cell">
                                Trend
                            </th>
                            <th className="px-2 py-2 text-right font-semibold">
                                Events
                            </th>
                            <th className="px-2 py-2 text-right font-semibold">
                                Users
                            </th>
                            <th className="hidden px-5 py-2 text-right font-semibold lg:table-cell">
                                Seen
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                        {page.rows.map((issue) => (
                            <tr
                                key={issue.id}
                                className="group hover:bg-gray-50/60 dark:hover:bg-gray-700/20"
                            >
                                <td className="max-w-0 px-5 py-3">
                                    <div className="flex items-start gap-3">
                                        <span className="mt-1.5">
                                            <LevelBadge level={issue.level} />
                                        </span>
                                        <div className="min-w-0">
                                            <Link
                                                href={dashboardRoutes.errors.show.url(
                                                    { issueId: issue.id },
                                                )}
                                                className="block truncate font-semibold text-gray-800 group-hover:text-brand-green dark:text-gray-100"
                                            >
                                                {issue.title}
                                            </Link>
                                            <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                                <span className="font-mono text-gray-400 dark:text-gray-500">
                                                    {issue.shortId}
                                                </span>
                                                {issue.culprit && (
                                                    <span className="truncate">
                                                        {issue.culprit}
                                                    </span>
                                                )}
                                                <IssueBadges issue={issue} />
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td className="hidden px-2 py-3 text-rose-400 md:table-cell">
                                    <Sparkline values={issue.trend} />
                                </td>
                                <td className="px-2 py-3 text-right font-semibold whitespace-nowrap text-gray-800 tabular-nums dark:text-gray-100">
                                    {formatCount(issue.events)}
                                </td>
                                <td className="px-2 py-3 text-right whitespace-nowrap tabular-nums">
                                    {formatCount(issue.users)}
                                </td>
                                <td className="hidden px-5 py-3 text-right text-xs whitespace-nowrap text-gray-500 lg:table-cell dark:text-gray-400">
                                    {issue.lastSeen && (
                                        <div>
                                            {formatTimeAgo(issue.lastSeen)}
                                        </div>
                                    )}
                                    {issue.firstSeen && (
                                        <div className="text-gray-400 dark:text-gray-500">
                                            first{' '}
                                            {formatTimeAgo(issue.firstSeen)}
                                        </div>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <footer className="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-sm text-gray-500 dark:border-gray-700/60 dark:text-gray-400">
                <span>
                    {page.total.toLocaleString()}{' '}
                    {page.total === 1 ? 'issue' : 'issues'}
                </span>
                <div className="flex gap-2">
                    <button
                        type="button"
                        className="btn-sm border-gray-200 bg-white text-gray-600 hover:border-gray-300 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300"
                        disabled={page.previous === null}
                        onClick={() =>
                            visit(filters, { cursor: page.previous })
                        }
                    >
                        <ChevronLeft className="size-4" />
                        <span className="sr-only">Previous page</span>
                    </button>
                    <button
                        type="button"
                        className="btn-sm border-gray-200 bg-white text-gray-600 hover:border-gray-300 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300"
                        disabled={page.next === null}
                        onClick={() => visit(filters, { cursor: page.next })}
                    >
                        <ChevronRight className="size-4" />
                        <span className="sr-only">Next page</span>
                    </button>
                </div>
            </footer>
        </div>
    );
}

function SearchBox({ filters }: { filters: Filters }) {
    const [term, setTerm] = useState(filters.search);

    return (
        <form
            className="relative"
            role="search"
            onSubmit={(event) => {
                event.preventDefault();
                visit(filters, { search: term.trim() });
            }}
        >
            <label htmlFor="issue-search" className="sr-only">
                Search issues
            </label>
            <input
                id="issue-search"
                className="form-input w-full bg-white pr-8 pl-9 sm:w-72 dark:bg-gray-800"
                type="search"
                placeholder="Search, e.g. CheckIn or url:*dashboard*"
                value={term}
                onChange={(event) => setTerm(event.target.value)}
            />
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
            {filters.search !== '' && (
                <button
                    type="button"
                    className="absolute top-1/2 right-2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                    onClick={() => {
                        setTerm('');
                        visit(filters, { search: '' });
                    }}
                >
                    <X className="size-4" />
                    <span className="sr-only">Clear search</span>
                </button>
            )}
        </form>
    );
}

export default function ErrorsIndex({
    filters,
    periods,
    tabs,
    sorts,
    sentryUrl,
    report,
    issues,
}: ErrorsProps) {
    const period =
        periods.find((option) => option.value === filters.period) ?? periods[0];
    const environments =
        report?.state === 'ready' ? report.data.environments : [];

    return (
        <>
            <Head title="Errors" />

            <PageHeader
                title="Errors"
                subtitle="What is breaking, how often, and for whom, from Sentry."
                actions={
                    <>
                        {(environments.length > 1 ||
                            filters.environment !== null) && (
                            <Select
                                icon={Layers}
                                label="Select environment"
                                options={[
                                    { value: '', label: 'All environments' },
                                    ...environments.map((name) => ({
                                        value: name,
                                        label: name,
                                    })),
                                ]}
                                value={filters.environment ?? ''}
                                onChange={(environment) =>
                                    visit(filters, {
                                        environment: environment || null,
                                    })
                                }
                            />
                        )}
                        <Select
                            icon={CalendarDays}
                            label="Select period"
                            options={periods}
                            value={filters.period}
                            onChange={(selected) =>
                                visit(filters, { period: selected })
                            }
                        />
                        {sentryUrl && (
                            <a
                                href={sentryUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 max-sm:hidden dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                            >
                                <ExternalLink className="size-4" />
                                <span className="ml-2">Sentry</span>
                            </a>
                        )}
                    </>
                }
            />

            <Deferred data="report" fallback={<ReportSkeleton />}>
                {({ reloading }) =>
                    report === undefined ? (
                        <ReportSkeleton />
                    ) : report.state === 'ready' ? (
                        <Report
                            report={report.data}
                            period={period}
                            environment={filters.environment}
                            reloading={reloading}
                        />
                    ) : (
                        <SentryState state={report.state} className="mb-6" />
                    )
                }
            </Deferred>

            {issues?.state !== 'unconfigured' &&
                issues?.state !== 'unavailable' && (
                    <Card>
                        <header className="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700/60">
                            <ul className="-m-1 flex flex-wrap">
                                {tabs.map((tab) => {
                                    const active = tab.value === filters.tab;

                                    return (
                                        <li key={tab.value} className="m-1">
                                            <button
                                                type="button"
                                                aria-pressed={active}
                                                onClick={() =>
                                                    visit(filters, {
                                                        tab: tab.value,
                                                    })
                                                }
                                                className={`inline-flex items-center justify-center rounded-full border px-3 py-1 text-sm leading-5 font-medium shadow-sm transition ${
                                                    active
                                                        ? 'border-transparent bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-800'
                                                        : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600'
                                                }`}
                                            >
                                                {tab.label}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <SearchBox
                                    key={filters.search}
                                    filters={filters}
                                />
                                <Select
                                    icon={ArrowDownUp}
                                    label="Sort issues"
                                    options={sorts}
                                    value={filters.sort}
                                    onChange={(sort) =>
                                        visit(filters, { sort })
                                    }
                                    className="min-w-40"
                                />
                            </div>
                        </header>

                        <Deferred data="issues" fallback={<IssuesSkeleton />}>
                            {({ reloading }) =>
                                issues?.state === 'ready' ? (
                                    <IssueList
                                        page={issues.data}
                                        filters={filters}
                                        reloading={reloading}
                                    />
                                ) : (
                                    <IssuesSkeleton />
                                )
                            }
                        </Deferred>
                    </Card>
                )}
        </>
    );
}

ErrorsIndex.layout = DashboardLayout;
