import { Deferred, Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Bug,
    CalendarCheck,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    GitCommitHorizontal,
    Package,
    Rocket,
    Server,
    Sparkles,
    Wrench,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';

import SentryState from '@/components/dashboard/errors/SentryState';
import type { SentryPayload } from '@/components/dashboard/errors/types';
import PageHeader from '@/components/dashboard/PageHeader';
import StatCard from '@/components/dashboard/StatCard';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatDateTime, formatTimeAgo } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

type Health = 'healthy' | 'watch' | 'unhealthy';

type ChangelogGroup = 'breaking' | 'features' | 'fixes' | 'other';

interface ChangelogCommit {
    id: string;
    shortId: string;
    type: string;
    scope: string | null;
    subject: string;
    author: string | null;
    date: string | null;
}

interface Release {
    version: string;
    shortVersion: string;
    createdAt: string | null;
    newIssues: number;
    commitCount: number;
    authors: string[];
    deploys: { environment: string; finishedAt: string | null }[];
    changelog: Record<ChangelogGroup, ChangelogCommit[]>;
    url: string;
    issuesUrl: string;
    isRunning: boolean;
}

/** Mirrors SentryDeploymentsReport::execute(). */
interface DeploymentsReport {
    running: {
        version: string;
        shortVersion: string;
        deployedAt: string | null;
        environment: string | null;
        newIssues: number;
        health: Health;
        url: string;
        issuesUrl: string;
    } | null;
    summary: {
        lastDeployAt: string | null;
        lastDeployEnvironment: string | null;
        deploysThisMonth: number;
        releases: number;
        issuesIntroduced: number;
    };
    releases: Release[];
}

interface DeploymentsProps {
    build: {
        commit: string | null;
        shortCommit: string | null;
        environment: string;
    };
    report?: SentryPayload<DeploymentsReport>;
}

const HEALTH: Record<
    Health,
    { label: string; icon: LucideIcon; classes: string; glow: string }
> = {
    healthy: {
        label: 'Healthy',
        icon: CheckCircle2,
        classes: 'bg-green-500/15 text-green-700 dark:text-green-400',
        glow: 'from-green-500/15',
    },
    watch: {
        label: 'Worth a look',
        icon: AlertTriangle,
        classes: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        glow: 'from-amber-500/15',
    },
    unhealthy: {
        label: 'Unhealthy',
        icon: XCircle,
        classes: 'bg-rose-500/15 text-rose-700 dark:text-rose-400',
        glow: 'from-rose-500/15',
    },
};

const CHANGELOG_GROUPS: {
    key: ChangelogGroup;
    label: string;
    icon: LucideIcon;
    classes: string;
}[] = [
    {
        key: 'breaking',
        label: 'Breaking changes',
        icon: AlertTriangle,
        classes: 'text-rose-500',
    },
    {
        key: 'features',
        label: 'Features',
        icon: Sparkles,
        classes: 'text-brand-green',
    },
    { key: 'fixes', label: 'Fixes', icon: Wrench, classes: 'text-sky-500' },
    {
        key: 'other',
        label: 'Other',
        icon: GitCommitHorizontal,
        classes: 'text-gray-400',
    },
];

/** The errors page, searching for the issues first seen in this release. */
function introducedIssuesUrl(version: string): string {
    return dashboardRoutes.errors.url({
        query: { search: `firstRelease:${version}`, period: '30d' },
    });
}

function ReportSkeleton() {
    return (
        <div className="grid animate-pulse grid-cols-12 gap-6" aria-busy="true">
            <div className="col-span-full h-44 rounded-xl bg-white shadow-xs dark:bg-gray-800" />
            {Array.from({ length: 4 }, (_, index) => (
                <div
                    key={index}
                    className="col-span-6 h-32 rounded-xl bg-white shadow-xs xl:col-span-3 dark:bg-gray-800"
                />
            ))}
            {Array.from({ length: 3 }, (_, index) => (
                <div
                    key={index}
                    className="col-span-full h-28 rounded-xl bg-white shadow-xs dark:bg-gray-800"
                />
            ))}
        </div>
    );
}

function RunningCard({
    running,
    build,
}: {
    running: DeploymentsReport['running'];
    build: DeploymentsProps['build'];
}) {
    if (running === null) {
        return (
            <section className="col-span-full rounded-xl bg-white p-6 shadow-xs dark:bg-gray-800">
                <div className="flex items-center gap-4">
                    <span className="flex size-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-700">
                        <Server className="size-5" />
                    </span>
                    <div>
                        <div className="text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                            Running now
                        </div>
                        <div className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                            {build.shortCommit ? (
                                <span className="font-mono">
                                    {build.shortCommit}
                                </span>
                            ) : (
                                'Local build'
                            )}{' '}
                            <span className="text-sm font-normal text-gray-500 dark:text-gray-400">
                                on {build.environment}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {build.commit
                                ? 'This commit is not registered as a Sentry release yet. The deploy workflow registers one on each staging deploy.'
                                : 'No commit is baked into this build, so it cannot be matched to a release.'}
                        </p>
                    </div>
                </div>
            </section>
        );
    }

    const health = HEALTH[running.health];
    const HealthIcon = health.icon;

    return (
        <section className="relative col-span-full overflow-hidden rounded-xl bg-white p-6 shadow-xs dark:bg-gray-800">
            <div
                aria-hidden="true"
                className={`pointer-events-none absolute inset-0 bg-linear-to-br ${health.glow} via-transparent to-transparent`}
            />
            <div className="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-start gap-4">
                    <span className="relative mt-1 flex size-3">
                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-brand-green opacity-60" />
                        <span className="relative inline-flex size-3 rounded-full bg-brand-green" />
                    </span>
                    <div>
                        <div className="text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                            Running now
                        </div>
                        <div className="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span className="font-mono text-3xl font-bold text-gray-800 dark:text-gray-100">
                                {running.shortVersion}
                            </span>
                            {running.environment && (
                                <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700/60 dark:text-gray-300">
                                    {running.environment}
                                </span>
                            )}
                        </div>
                        {running.deployedAt && (
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Deployed {formatTimeAgo(running.deployedAt)},{' '}
                                {formatDateTime(running.deployedAt)}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <span
                        className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold ${health.classes}`}
                    >
                        <HealthIcon className="size-4" />
                        {health.label}
                    </span>
                    <Link
                        href={introducedIssuesUrl(running.version)}
                        className="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-medium text-gray-600 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300"
                    >
                        <Bug className="size-4 text-rose-500" />
                        {running.newIssues}{' '}
                        {running.newIssues === 1 ? 'new issue' : 'new issues'}
                    </Link>
                    <a
                        href={running.url}
                        target="_blank"
                        rel="noreferrer"
                        className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        <ExternalLink className="size-4" />
                        <span className="ml-2">Release in Sentry</span>
                    </a>
                </div>
            </div>
        </section>
    );
}

function Changelog({ release }: { release: Release }) {
    const groups = CHANGELOG_GROUPS.filter(
        (group) => release.changelog[group.key].length > 0,
    );

    if (groups.length === 0) {
        return (
            <p className="text-sm text-gray-400 dark:text-gray-500">
                No commits were sent with this release.
            </p>
        );
    }

    return (
        <div className="grid gap-5 md:grid-cols-2">
            {groups.map(({ key, label, icon: Icon, classes }) => (
                <div key={key}>
                    <h4 className="mb-2 flex items-center gap-1.5 text-xs font-semibold text-gray-500 uppercase dark:text-gray-400">
                        <Icon className={`size-3.5 ${classes}`} />
                        {label}
                        <span className="text-gray-400 dark:text-gray-500">
                            {release.changelog[key].length}
                        </span>
                    </h4>
                    <ul className="space-y-1.5">
                        {release.changelog[key].map((commit) => (
                            <li
                                key={commit.id}
                                className="flex items-start gap-2 text-sm"
                            >
                                <span className="mt-0.5 shrink-0 font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                    {commit.shortId}
                                </span>
                                <span className="min-w-0 text-gray-700 dark:text-gray-200">
                                    {commit.scope && (
                                        <span className="mr-1 font-semibold text-gray-500 dark:text-gray-400">
                                            {commit.scope}:
                                        </span>
                                    )}
                                    {commit.subject}
                                    {commit.author && (
                                        <span className="ml-1.5 text-xs text-gray-400 dark:text-gray-500">
                                            {commit.author}
                                        </span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}

function ReleaseItem({
    release,
    defaultOpen,
}: {
    release: Release;
    defaultOpen: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const environments = [
        ...new Set(release.deploys.map((deploy) => deploy.environment)),
    ];

    return (
        <li className="group relative pl-8">
            <span
                aria-hidden="true"
                className="absolute top-8 -bottom-4 left-[11px] w-px bg-gray-200 group-last:hidden dark:bg-gray-700"
            />
            <span
                className={`absolute top-5 left-0 flex size-6 items-center justify-center rounded-full ring-4 ring-gray-100 dark:ring-gray-900 ${
                    release.isRunning
                        ? 'bg-brand-green text-white'
                        : 'bg-white text-gray-400 dark:bg-gray-800'
                }`}
            >
                <Rocket className="size-3" />
            </span>

            <div className="rounded-xl bg-white shadow-xs dark:bg-gray-800">
                <button
                    type="button"
                    className="flex w-full flex-col gap-3 p-5 text-left sm:flex-row sm:items-center sm:justify-between"
                    onClick={() => setOpen(!open)}
                    aria-expanded={open}
                >
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            {open ? (
                                <ChevronDown className="size-4 text-gray-400" />
                            ) : (
                                <ChevronRight className="size-4 text-gray-400" />
                            )}
                            <span className="font-mono text-base font-semibold text-gray-800 dark:text-gray-100">
                                {release.shortVersion}
                            </span>
                            {release.isRunning && (
                                <span className="rounded-full bg-brand-green/15 px-2 py-0.5 text-[11px] font-semibold text-brand-green">
                                    Running
                                </span>
                            )}
                            {environments.map((environment) => (
                                <span
                                    key={environment}
                                    className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-gray-700/60 dark:text-gray-300"
                                >
                                    {environment}
                                </span>
                            ))}
                        </div>
                        <div className="mt-1 pl-6 text-xs text-gray-500 dark:text-gray-400">
                            {release.createdAt &&
                                `${formatDateTime(release.createdAt)} · `}
                            {release.commitCount}{' '}
                            {release.commitCount === 1 ? 'commit' : 'commits'}
                            {release.authors.length > 0 &&
                                ` · ${release.authors.join(', ')}`}
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center gap-2 pl-6 sm:pl-0">
                        <span
                            className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ${
                                release.newIssues === 0
                                    ? 'bg-green-500/15 text-green-700 dark:text-green-400'
                                    : 'bg-rose-500/12 text-rose-600 dark:text-rose-400'
                            }`}
                        >
                            <Bug className="size-3" />
                            {release.newIssues} new
                        </span>
                        <span className="text-xs text-gray-400 dark:text-gray-500">
                            {release.deploys.length}{' '}
                            {release.deploys.length === 1
                                ? 'deploy'
                                : 'deploys'}
                        </span>
                    </div>
                </button>

                {open && (
                    <div className="border-t border-gray-100 px-5 py-4 dark:border-gray-700/60">
                        <Changelog release={release} />
                        <div className="mt-4 flex flex-wrap gap-4 text-xs font-medium">
                            {release.newIssues > 0 && (
                                <Link
                                    href={introducedIssuesUrl(release.version)}
                                    className="text-rose-600 hover:text-rose-700 dark:text-rose-400"
                                >
                                    View issues introduced
                                </Link>
                            )}
                            <a
                                href={release.url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                Open in Sentry
                                <ExternalLink className="size-3" />
                            </a>
                        </div>
                    </div>
                )}
            </div>
        </li>
    );
}

function Report({
    report,
    build,
}: {
    report: DeploymentsReport;
    build: DeploymentsProps['build'];
}) {
    const { running, summary, releases } = report;

    return (
        <div className="grid grid-cols-12 gap-6">
            <RunningCard running={running} build={build} />

            <div className="col-span-full grid grid-cols-2 gap-6 xl:grid-cols-4">
                <StatCard
                    className=""
                    title="Last deploy"
                    label={summary.lastDeployEnvironment ?? 'Not deployed'}
                    value={
                        summary.lastDeployAt
                            ? formatTimeAgo(summary.lastDeployAt)
                            : '-'
                    }
                    icon={Rocket}
                />
                <StatCard
                    className=""
                    title="This month"
                    label="Deploys"
                    value={summary.deploysThisMonth}
                    icon={CalendarCheck}
                />
                <StatCard
                    className=""
                    title="Releases"
                    label="Shown below"
                    value={summary.releases}
                    icon={Package}
                />
                <StatCard
                    className=""
                    title="Introduced"
                    label="New issues, 30 days"
                    value={summary.issuesIntroduced}
                    icon={Bug}
                />
            </div>

            <section className="col-span-full">
                <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-100">
                    Releases
                </h2>
                {releases.length === 0 ? (
                    <div className="rounded-xl bg-white px-5 py-12 text-center text-sm text-gray-500 shadow-xs dark:bg-gray-800 dark:text-gray-400">
                        No releases yet. The next staging deploy registers the
                        first one.
                    </div>
                ) : (
                    <ol className="space-y-4">
                        {releases.map((release, index) => (
                            <ReleaseItem
                                key={release.version}
                                release={release}
                                defaultOpen={index === 0 || release.isRunning}
                            />
                        ))}
                    </ol>
                )}
            </section>
        </div>
    );
}

export default function DeploymentsIndex({ build, report }: DeploymentsProps) {
    return (
        <>
            <Head title="Deployments" />

            <PageHeader
                title="Deployments"
                subtitle="What is running, when it shipped, and what changed in each release."
            />

            <Deferred data="report" fallback={<ReportSkeleton />}>
                {() =>
                    report === undefined ? (
                        <ReportSkeleton />
                    ) : report.state === 'ready' ? (
                        <Report report={report.data} build={build} />
                    ) : (
                        <SentryState state={report.state} />
                    )
                }
            </Deferred>
        </>
    );
}

DeploymentsIndex.layout = DashboardLayout;
