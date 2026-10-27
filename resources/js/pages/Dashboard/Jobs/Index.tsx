import { Deferred, Head, usePoll } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CloudOff,
    CheckCircle2,
    Hourglass,
    ListChecks,
} from 'lucide-react';

import Card from '@/components/dashboard/Card';
import FailedJobsCard from '@/components/dashboard/jobs/FailedJobsCard';
import type {
    HorizonPayload,
    HorizonStatus,
    JobsReport,
} from '@/components/dashboard/jobs/types';
import WorkerControls from '@/components/dashboard/jobs/WorkerControls';
import PageHeader from '@/components/dashboard/PageHeader';
import StatCard from '@/components/dashboard/StatCard';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatDateTime, formatTimeAgo } from '@/lib/dashboard/format';

interface JobsProps {
    environment: string;
    report?: HorizonPayload;
}

/** How often the page re-reads Horizon, in milliseconds. */
const POLL_INTERVAL = 5000;

const STATUS: Record<
    HorizonStatus,
    { label: string; body: string; classes: string; dot: string }
> = {
    running: {
        label: 'Running',
        body: 'Workers are picking up jobs.',
        classes: 'bg-green-500/15 text-green-700 dark:text-green-400',
        dot: 'bg-brand-green',
    },
    paused: {
        label: 'Paused',
        body: 'Workers are not picking up new jobs. Queued jobs wait until you resume.',
        classes: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        dot: 'bg-amber-500',
    },
    inactive: {
        label: 'Not running',
        body: 'No Horizon process is running, so nothing is being processed. Check the horizon container.',
        classes: 'bg-rose-500/15 text-rose-700 dark:text-rose-400',
        dot: 'bg-rose-500',
    },
};

const JOB_STATUS_CLASSES: Record<string, string> = {
    completed: 'bg-brand-green/12 text-brand-green',
    failed: 'bg-rose-500/12 text-rose-700 dark:text-rose-400',
    reserved: 'bg-blue-500/12 text-blue-700 dark:text-blue-400',
    pending: 'bg-amber-500/12 text-amber-700 dark:text-amber-400',
};

const TABLE_HEAD =
    'bg-gray-50 text-xs text-gray-400 uppercase dark:bg-gray-700/50 dark:text-gray-500';

function formatWait(seconds: number): string {
    if (seconds < 60) {
        return `${seconds}s`;
    }

    const minutes = Math.floor(seconds / 60);

    return minutes < 60
        ? `${minutes}m`
        : `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
}

function formatRuntime(seconds: number | null): string {
    if (seconds === null) {
        return '-';
    }

    return seconds < 1
        ? `${Math.round(seconds * 1000)}ms`
        : `${seconds.toFixed(2)}s`;
}

function shortName(name: string): string {
    return name.split('\\').pop() ?? name;
}

function ReportSkeleton() {
    return (
        <div className="grid animate-pulse grid-cols-12 gap-6" aria-busy="true">
            <div className="col-span-full h-24 rounded-xl bg-white shadow-xs dark:bg-gray-800" />
            {Array.from({ length: 4 }, (_, index) => (
                <div
                    key={index}
                    className="col-span-6 h-32 rounded-xl bg-white shadow-xs xl:col-span-3 dark:bg-gray-800"
                />
            ))}
            <div className="col-span-full h-48 rounded-xl bg-white shadow-xs xl:col-span-6 dark:bg-gray-800" />
            <div className="col-span-full h-48 rounded-xl bg-white shadow-xs xl:col-span-6 dark:bg-gray-800" />
            <div className="col-span-full h-64 rounded-xl bg-white shadow-xs dark:bg-gray-800" />
        </div>
    );
}

function Unavailable() {
    return (
        <div className="flex flex-col items-center rounded-xl bg-white px-5 py-16 text-center shadow-xs dark:bg-gray-800">
            <span className="mb-4 flex size-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                <CloudOff className="size-6 text-gray-400" />
            </span>
            <h2 className="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-100">
                Redis could not be reached
            </h2>
            <p className="max-w-md text-sm text-gray-500 dark:text-gray-400">
                The queue lives in Redis, which did not answer. This page
                retries every few seconds.
            </p>
        </div>
    );
}

function StatusBanner({
    status,
    environment,
}: {
    status: HorizonStatus;
    environment: string;
}) {
    const { label, body, classes, dot } = STATUS[status];

    return (
        <section className="col-span-full flex items-center gap-4 rounded-xl bg-white p-5 shadow-xs dark:bg-gray-800">
            <span className="relative flex size-3">
                {status === 'running' && (
                    <span
                        className={`absolute inline-flex size-full animate-ping rounded-full opacity-60 ${dot}`}
                    />
                )}
                <span
                    className={`relative inline-flex size-3 rounded-full ${dot}`}
                />
            </span>
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={`rounded-full px-2.5 py-0.5 text-sm font-semibold ${classes}`}
                    >
                        {label}
                    </span>
                    <span className="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-700/60 dark:text-gray-300">
                        {environment}
                    </span>
                </div>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {body}
                </p>
            </div>
        </section>
    );
}

function QueuesCard({ workload }: { workload: JobsReport['workload'] }) {
    return (
        <Card title="Queues" className="col-span-full xl:col-span-6">
            <div className="overflow-x-auto">
                <table className="w-full table-auto dark:text-gray-300">
                    <thead className={TABLE_HEAD}>
                        <tr>
                            <th className="w-full px-5 py-2 text-left font-semibold">
                                Queue
                            </th>
                            <th className="px-2 py-2 text-right font-semibold">
                                Waiting
                            </th>
                            <th className="px-2 py-2 text-right font-semibold">
                                Clears in
                            </th>
                            <th className="px-5 py-2 text-right font-semibold">
                                Workers
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                        {workload.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-5 py-6 text-center text-gray-400"
                                >
                                    No queues are being watched.
                                </td>
                            </tr>
                        )}
                        {workload.map((queue) => (
                            <tr key={queue.name}>
                                <td className="px-5 py-3 font-semibold text-gray-800 dark:text-gray-100">
                                    {queue.name}
                                </td>
                                <td
                                    className={`px-2 py-3 text-right tabular-nums ${queue.length > 0 ? 'font-semibold text-gray-800 dark:text-gray-100' : ''}`}
                                >
                                    {queue.length}
                                </td>
                                <td className="px-2 py-3 text-right whitespace-nowrap tabular-nums">
                                    {formatWait(queue.wait)}
                                </td>
                                <td className="px-5 py-3 text-right tabular-nums">
                                    {queue.processes}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function WorkersCard({
    supervisors,
}: {
    supervisors: JobsReport['supervisors'];
}) {
    return (
        <Card title="Workers" className="col-span-full xl:col-span-6">
            <div className="overflow-x-auto">
                <table className="w-full table-auto dark:text-gray-300">
                    <thead className={TABLE_HEAD}>
                        <tr>
                            <th className="w-full px-5 py-2 text-left font-semibold">
                                Supervisor
                            </th>
                            <th className="px-2 py-2 text-left font-semibold">
                                Status
                            </th>
                            <th className="px-5 py-2 text-right font-semibold">
                                Processes
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                        {supervisors.length === 0 && (
                            <tr>
                                <td
                                    colSpan={3}
                                    className="px-5 py-6 text-center text-gray-400"
                                >
                                    No supervisors are running.
                                </td>
                            </tr>
                        )}
                        {supervisors.map((supervisor) => (
                            <tr key={supervisor.name}>
                                <td className="px-5 py-3">
                                    <div className="font-semibold text-gray-800 dark:text-gray-100">
                                        {supervisor.name}
                                    </div>
                                    <div className="text-xs text-gray-500 dark:text-gray-400">
                                        {supervisor.queues.join(', ')}
                                    </div>
                                </td>
                                <td className="px-2 py-3">
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${
                                            supervisor.status === 'running'
                                                ? 'bg-green-500/15 text-green-700 dark:text-green-400'
                                                : 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                        }`}
                                    >
                                        {supervisor.status}
                                    </span>
                                </td>
                                <td className="px-5 py-3 text-right whitespace-nowrap tabular-nums">
                                    {supervisor.processes}
                                    {supervisor.maxProcesses !== null && (
                                        <span className="text-gray-400">
                                            {' '}
                                            / {supervisor.maxProcesses}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function RecentJobsCard({
    jobs,
    keptMinutes,
}: {
    jobs: JobsReport['recentJobs'];
    keptMinutes: number;
}) {
    return (
        <Card title="Recent jobs" className="col-span-full">
            <div className="overflow-x-auto">
                <table className="w-full table-auto dark:text-gray-300">
                    <thead className={TABLE_HEAD}>
                        <tr>
                            <th className="w-full px-5 py-2 text-left font-semibold">
                                Job
                            </th>
                            <th className="px-2 py-2 text-left font-semibold">
                                Status
                            </th>
                            <th className="hidden px-2 py-2 text-right font-semibold sm:table-cell">
                                Runtime
                            </th>
                            <th className="px-5 py-2 text-right font-semibold">
                                Queued
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                        {jobs.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-5 py-6 text-center text-gray-400"
                                >
                                    No jobs ran in the last {keptMinutes}{' '}
                                    minutes.
                                </td>
                            </tr>
                        )}
                        {jobs.map((job) => (
                            <tr key={job.id}>
                                <td className="max-w-0 px-5 py-3">
                                    <div
                                        className="truncate font-semibold text-gray-800 dark:text-gray-100"
                                        title={job.name}
                                    >
                                        {shortName(job.name)}
                                    </div>
                                    <div className="text-xs text-gray-500 dark:text-gray-400">
                                        {job.queue}
                                    </div>
                                </td>
                                <td className="px-2 py-3">
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${JOB_STATUS_CLASSES[job.status] ?? 'bg-gray-400/15 text-gray-500'}`}
                                    >
                                        {job.status === 'reserved'
                                            ? 'running'
                                            : job.status}
                                    </span>
                                </td>
                                <td className="hidden px-2 py-3 text-right whitespace-nowrap tabular-nums sm:table-cell">
                                    {formatRuntime(job.runtime)}
                                </td>
                                <td
                                    className="px-5 py-3 text-right text-xs whitespace-nowrap text-gray-500 dark:text-gray-400"
                                    title={
                                        job.queuedAt
                                            ? formatDateTime(job.queuedAt)
                                            : undefined
                                    }
                                >
                                    {job.queuedAt
                                        ? formatTimeAgo(job.queuedAt)
                                        : '-'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function Report({
    report,
    environment,
}: {
    report: JobsReport;
    environment: string;
}) {
    const can = useCan();
    const { stats, periods } = report;
    const longestWait = Math.max(
        0,
        ...report.workload.map((queue) => queue.wait),
    );

    return (
        <div className="grid grid-cols-12 gap-6">
            <StatusBanner status={report.status} environment={environment} />

            <StatCard
                title="Throughput"
                label="Jobs per minute"
                value={stats.jobsPerMinute}
                icon={Activity}
            />
            <StatCard
                title="Waiting"
                label="Jobs queued now"
                value={stats.pendingJobs}
                icon={Hourglass}
                delta={
                    longestWait > 0
                        ? {
                              text: `clears in ${formatWait(longestWait)}`,
                              tone: 'neutral',
                          }
                        : undefined
                }
            />
            <StatCard
                title="Recent"
                label={`Jobs in the last ${periods.recent} min`}
                value={stats.recentJobs}
                icon={ListChecks}
            />
            <StatCard
                title="Failed"
                label={`Failures in the last ${Math.round(periods.failed / 1440)} days`}
                value={stats.failedJobs}
                icon={stats.failedJobs > 0 ? AlertTriangle : CheckCircle2}
                delta={
                    stats.failedJobs > 0
                        ? { text: 'needs a look', tone: 'negative' }
                        : undefined
                }
            />

            <QueuesCard workload={report.workload} />
            <WorkersCard supervisors={report.supervisors} />

            <FailedJobsCard
                jobs={report.failedJobs}
                total={stats.failedJobs}
                keptMinutes={periods.failed}
                canManage={can('manage jobs')}
            />

            <RecentJobsCard
                jobs={report.recentJobs}
                keptMinutes={periods.recent}
            />
        </div>
    );
}

export default function JobsIndex({ environment, report }: JobsProps) {
    const can = useCan();

    usePoll(POLL_INTERVAL, { only: ['report'] });

    const status = report?.state === 'ready' ? report.data.status : null;

    return (
        <>
            <Head title="Jobs" />

            <PageHeader
                title="Jobs"
                subtitle="The background job queues, the workers running them, and the jobs that failed. Refreshes every few seconds."
                actions={
                    status !== null && can('manage jobs') ? (
                        <WorkerControls status={status} />
                    ) : undefined
                }
            />

            <Deferred data="report" fallback={<ReportSkeleton />}>
                {() =>
                    report === undefined ? (
                        <ReportSkeleton />
                    ) : report.state === 'ready' ? (
                        <Report
                            report={report.data}
                            environment={environment}
                        />
                    ) : (
                        <Unavailable />
                    )
                }
            </Deferred>
        </>
    );
}

JobsIndex.layout = DashboardLayout;
