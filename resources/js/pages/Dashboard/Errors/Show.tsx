import { Deferred, Head } from '@inertiajs/react';
import {
    Bug,
    CalendarClock,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    History,
    Rocket,
    Users,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';

import BackLink from '@/components/dashboard/BackLink';
import Card from '@/components/dashboard/Card';
import { CHART_SERIES } from '@/components/dashboard/charts/chartjs-config';
import LineChart from '@/components/dashboard/charts/LineChart';
import { DetailCard, DetailRow } from '@/components/dashboard/DetailCard';
import LevelBadge from '@/components/dashboard/errors/LevelBadge';
import SentryState from '@/components/dashboard/errors/SentryState';
import type {
    IssueDetail,
    SentryPayload,
    StackFrame,
} from '@/components/dashboard/errors/types';
import StatChip from '@/components/dashboard/StatChip';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDateTime,
    formatLabel,
    formatTime,
    formatTimeAgo,
} from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

interface ErrorShowProps {
    issueId: number;
    detail?: SentryPayload<IssueDetail>;
}

const BREADCRUMB_LEVEL_CLASSES: Record<string, string> = {
    fatal: 'bg-red-600',
    error: 'bg-rose-500',
    warning: 'bg-amber-400',
    info: 'bg-sky-400',
    debug: 'bg-gray-300 dark:bg-gray-600',
};

const CONTEXT_LABELS: Record<string, string> = {
    runtime: 'Runtime',
    os: 'OS',
    browser: 'Browser',
    device: 'Device',
    client_os: 'Client OS',
};

function shortVersion(version: string): string {
    return /^[0-9a-f]{40}$/.test(version) ? version.slice(0, 7) : version;
}

function DetailSkeleton() {
    return (
        <div className="grid animate-pulse grid-cols-12 gap-6" aria-busy="true">
            <div className="col-span-full h-40 rounded-xl bg-white shadow-xs dark:bg-gray-800" />
            <div className="col-span-full h-96 rounded-xl bg-white shadow-xs xl:col-span-8 dark:bg-gray-800" />
            <div className="col-span-full h-96 rounded-xl bg-white shadow-xs xl:col-span-4 dark:bg-gray-800" />
        </div>
    );
}

function Frame({ frame }: { frame: StackFrame }) {
    const [open, setOpen] = useState(frame.inApp && frame.context.length > 0);
    const hasContext = frame.context.length > 0;

    return (
        <li
            className={
                frame.inApp
                    ? 'bg-white dark:bg-gray-800'
                    : 'bg-gray-50/70 dark:bg-gray-900/30'
            }
        >
            <button
                type="button"
                className="flex w-full items-start gap-2 px-5 py-2.5 text-left text-sm disabled:cursor-default"
                onClick={() => setOpen(!open)}
                disabled={!hasContext}
                aria-expanded={hasContext ? open : undefined}
            >
                <span className="mt-0.5 size-4 shrink-0 text-gray-400">
                    {hasContext &&
                        (open ? (
                            <ChevronDown className="size-4" />
                        ) : (
                            <ChevronRight className="size-4" />
                        ))}
                </span>
                <span className="min-w-0 font-mono text-xs leading-5 break-all">
                    <span
                        className={
                            frame.inApp
                                ? 'font-semibold text-gray-800 dark:text-gray-100'
                                : 'text-gray-500 dark:text-gray-400'
                        }
                    >
                        {frame.filename}
                    </span>
                    {frame.function && (
                        <span className="text-gray-400 dark:text-gray-500">
                            {' '}
                            in{' '}
                            <span className="text-sky-600 dark:text-sky-400">
                                {frame.function}
                            </span>
                        </span>
                    )}
                    {frame.lineNo !== null && (
                        <span className="text-gray-400 dark:text-gray-500">
                            {' '}
                            at line {frame.lineNo}
                        </span>
                    )}
                </span>
                {frame.inApp && (
                    <span className="ml-auto shrink-0 rounded-full bg-brand-green/12 px-2 py-0.5 text-[11px] font-semibold text-brand-green">
                        app
                    </span>
                )}
            </button>
            {open && hasContext && (
                <pre className="mx-5 mb-3 overflow-x-auto rounded-lg bg-gray-900 py-2 text-xs leading-5 text-gray-300">
                    {frame.context.map(({ line, code }) => (
                        <div
                            key={line}
                            className={`flex px-3 ${line === frame.lineNo ? 'bg-rose-500/25 text-white' : ''}`}
                        >
                            <span className="mr-4 w-8 shrink-0 text-right text-gray-500 select-none">
                                {line}
                            </span>
                            <code className="whitespace-pre">{code}</code>
                        </div>
                    ))}
                </pre>
            )}
        </li>
    );
}

/**
 * Frames most recent first. Runs of framework frames fold into one row, so
 * the app's own code reads straight down.
 */
function StackTrace({ frames }: { frames: StackFrame[] }) {
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const hasAppFrames = frames.some((frame) => frame.inApp);

    const groups = useMemo(() => {
        const result: {
            start: number;
            frames: StackFrame[];
            inApp: boolean;
        }[] = [];

        frames.forEach((frame, index) => {
            const inApp = frame.inApp || !hasAppFrames;
            const last = result[result.length - 1];

            if (last && last.inApp === inApp) {
                last.frames.push(frame);
            } else {
                result.push({ start: index, frames: [frame], inApp });
            }
        });

        return result;
    }, [frames, hasAppFrames]);

    if (frames.length === 0) {
        return (
            <p className="px-5 py-4 text-sm text-gray-400 dark:text-gray-500">
                No stack trace was sent with this event.
            </p>
        );
    }

    return (
        <ol className="divide-y divide-gray-100 dark:divide-gray-700/60">
            {groups.map((group) =>
                group.inApp || expanded.has(group.start) ? (
                    <Fragment key={group.start}>
                        {group.frames.map((frame, index) => (
                            <Frame key={group.start + index} frame={frame} />
                        ))}
                    </Fragment>
                ) : (
                    <li key={group.start}>
                        <button
                            type="button"
                            className="w-full bg-gray-50/70 px-5 py-2 text-left text-xs font-medium text-gray-500 hover:text-gray-700 dark:bg-gray-900/30 dark:text-gray-400 dark:hover:text-gray-200"
                            onClick={() =>
                                setExpanded(new Set(expanded).add(group.start))
                            }
                        >
                            Show {group.frames.length} framework{' '}
                            {group.frames.length === 1 ? 'frame' : 'frames'}
                        </button>
                    </li>
                ),
            )}
        </ol>
    );
}

function Detail({ detail }: { detail: IssueDetail }) {
    const { issue, event, tags } = detail;

    const trendSeries = useMemo(
        () => [
            {
                label: 'Events',
                color: CHART_SERIES.rose,
                fill: true,
                points: issue.trend.labels.map(
                    (label, index): [string, number] => [
                        new Date(label).toISOString(),
                        issue.trend.counts[index],
                    ],
                ),
            },
        ],
        [issue.trend],
    );

    return (
        <div className="grid grid-cols-12 gap-6">
            <section className="col-span-full rounded-xl bg-white p-5 shadow-xs dark:bg-gray-800">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                            <LevelBadge level={issue.level} withLabel />
                            <span className="font-mono text-xs text-gray-400 dark:text-gray-500">
                                {issue.shortId}
                            </span>
                            <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700/60 dark:text-gray-300">
                                {formatLabel(issue.substatus ?? issue.status)}
                            </span>
                            {issue.isUnhandled && (
                                <span className="rounded-full bg-rose-500/12 px-2.5 py-0.5 text-xs font-semibold text-rose-600 dark:text-rose-400">
                                    Unhandled
                                </span>
                            )}
                        </div>
                        <h1 className="text-xl font-bold break-words text-gray-800 md:text-2xl dark:text-gray-100">
                            {issue.type ?? issue.title}
                        </h1>
                        {issue.value && (
                            <p className="mt-1 text-sm break-words text-gray-600 dark:text-gray-300">
                                {issue.value}
                            </p>
                        )}
                        {issue.culprit && (
                            <p className="mt-1 font-mono text-xs break-all text-gray-400 dark:text-gray-500">
                                {issue.culprit}
                            </p>
                        )}
                    </div>
                    {issue.permalink && (
                        <a
                            href={issue.permalink}
                            target="_blank"
                            rel="noreferrer"
                            className="btn shrink-0 bg-gray-900 text-gray-100 hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                        >
                            <ExternalLink className="size-4" />
                            <span className="ml-2">Open in Sentry</span>
                        </a>
                    )}
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                    <StatChip
                        icon={Bug}
                        label="Events"
                        value={issue.events.toLocaleString()}
                    />
                    <StatChip
                        icon={Users}
                        label="Users"
                        value={issue.users.toLocaleString()}
                    />
                    {issue.firstSeen && (
                        <StatChip
                            icon={History}
                            label="First seen"
                            value={formatTimeAgo(issue.firstSeen)}
                        />
                    )}
                    {issue.lastSeen && (
                        <StatChip
                            icon={CalendarClock}
                            label="Last seen"
                            value={formatTimeAgo(issue.lastSeen)}
                        />
                    )}
                    {issue.firstRelease && (
                        <StatChip
                            icon={Rocket}
                            label="First release"
                            value={shortVersion(issue.firstRelease)}
                        />
                    )}
                </div>
            </section>

            <div className="col-span-full flex flex-col gap-6 xl:col-span-8">
                {event === null ? (
                    <Card title="Latest event">
                        <p className="px-5 py-4 text-sm text-gray-400 dark:text-gray-500">
                            Sentry has no stored event for this issue.
                        </p>
                    </Card>
                ) : (
                    <>
                        {event.exceptions.length === 0 ? (
                            <Card title="Message">
                                <p className="px-5 py-4 font-mono text-sm break-words">
                                    {event.message ?? issue.title}
                                </p>
                            </Card>
                        ) : (
                            event.exceptions.map((exception, index) => (
                                <Card
                                    key={index}
                                    title={
                                        <span className="flex flex-wrap items-center gap-2">
                                            {index > 0 && (
                                                <span className="text-xs font-medium text-gray-400 uppercase">
                                                    Caused
                                                </span>
                                            )}
                                            {exception.type}
                                            {exception.handled === false && (
                                                <span className="rounded-full bg-rose-500/12 px-2 py-0.5 text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                                                    Unhandled
                                                </span>
                                            )}
                                        </span>
                                    }
                                    action={
                                        event.dateCreated && (
                                            <span className="text-xs text-gray-400 dark:text-gray-500">
                                                {formatDateTime(
                                                    event.dateCreated,
                                                )}
                                            </span>
                                        )
                                    }
                                >
                                    {exception.value && (
                                        <p className="border-b border-gray-100 px-5 py-3 text-sm break-words text-gray-700 dark:border-gray-700/60 dark:text-gray-200">
                                            {exception.value}
                                        </p>
                                    )}
                                    <StackTrace frames={exception.frames} />
                                </Card>
                            ))
                        )}

                        <Card title="Breadcrumbs">
                            {event.breadcrumbs.length === 0 ? (
                                <p className="px-5 py-4 text-sm text-gray-400 dark:text-gray-500">
                                    No breadcrumbs were recorded.
                                </p>
                            ) : (
                                <ol className="relative px-5 py-4">
                                    {event.breadcrumbs.map((crumb, index) => (
                                        <li
                                            key={index}
                                            className="relative flex gap-3 pb-4 pl-5 last:pb-0"
                                        >
                                            <span className="absolute top-1.5 bottom-0 left-[4px] w-px bg-gray-200 dark:bg-gray-700" />
                                            <span
                                                className={`absolute top-1.5 left-0 size-2.5 rounded-full ring-4 ring-white dark:ring-gray-800 ${BREADCRUMB_LEVEL_CLASSES[crumb.level] ?? BREADCRUMB_LEVEL_CLASSES.info}`}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-baseline justify-between gap-3">
                                                    <span className="text-xs font-semibold text-gray-700 dark:text-gray-200">
                                                        {crumb.category ??
                                                            crumb.type}
                                                    </span>
                                                    {crumb.timestamp && (
                                                        <span className="shrink-0 text-xs text-gray-400 tabular-nums dark:text-gray-500">
                                                            {formatTime(
                                                                crumb.timestamp,
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                                {crumb.message && (
                                                    <p className="mt-0.5 font-mono text-xs break-all text-gray-500 dark:text-gray-400">
                                                        {crumb.message}
                                                    </p>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Card>
                    </>
                )}
            </div>

            <div className="col-span-full flex flex-col gap-6 xl:col-span-4">
                <Card title="Last 30 days">
                    <LineChart series={trendSeries} unit="day" height={140} />
                </Card>

                {event && (
                    <DetailCard title="Latest event">
                        <DetailRow label="Environment">
                            {event.environment}
                        </DetailRow>
                        <DetailRow label="Release">
                            {event.release && (
                                <span className="font-mono">
                                    {shortVersion(event.release)}
                                </span>
                            )}
                        </DetailRow>
                        {event.request && (
                            <DetailRow label="Request">
                                <span className="font-mono text-xs break-all">
                                    {event.request.method} {event.request.url}
                                </span>
                            </DetailRow>
                        )}
                        {event.user && (
                            <DetailRow label="User">
                                {event.user.email ??
                                    event.user.username ??
                                    (event.user.id && `#${event.user.id}`) ??
                                    event.user.ip}
                            </DetailRow>
                        )}
                        {event.contexts.map((context) => (
                            <DetailRow
                                key={context.key}
                                label={
                                    CONTEXT_LABELS[context.key] ?? context.key
                                }
                            >
                                {context.value}
                            </DetailRow>
                        ))}
                    </DetailCard>
                )}

                {tags.length > 0 && (
                    <Card title="Tags">
                        <div className="space-y-5 px-5 py-4">
                            {tags.map((tag) => (
                                <div key={tag.key}>
                                    <div className="mb-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                                        {tag.name}
                                    </div>
                                    <ul className="space-y-1.5">
                                        {tag.top.map((value) => (
                                            <li
                                                key={value.value}
                                                className="relative overflow-hidden rounded-md px-2 py-1 text-xs"
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    className="absolute inset-y-0 left-0 bg-brand-green/12"
                                                    style={{
                                                        width: `${value.percent}%`,
                                                    }}
                                                />
                                                <span className="relative flex justify-between gap-3">
                                                    <span className="truncate font-medium text-gray-700 dark:text-gray-200">
                                                        {value.value ||
                                                            '(empty)'}
                                                    </span>
                                                    <span className="shrink-0 text-gray-500 tabular-nums dark:text-gray-400">
                                                        {value.percent}%
                                                    </span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </div>
    );
}

export default function ErrorShow({ detail }: ErrorShowProps) {
    const title =
        detail?.state === 'ready'
            ? (detail.data.issue.type ?? detail.data.issue.title)
            : 'Error';

    return (
        <>
            <Head title={title} />

            <BackLink href={dashboardRoutes.errors.url()} label="Errors" />

            <Deferred data="detail" fallback={<DetailSkeleton />}>
                {() =>
                    detail === undefined ? (
                        <DetailSkeleton />
                    ) : detail.state === 'ready' ? (
                        <Detail detail={detail.data} />
                    ) : (
                        <SentryState state={detail.state} />
                    )
                }
            </Deferred>
        </>
    );
}

ErrorShow.layout = DashboardLayout;
