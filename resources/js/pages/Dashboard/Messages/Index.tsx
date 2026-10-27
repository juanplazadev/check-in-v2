import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CircleAlert,
    Hourglass,
    Mail,
    MailCheck,
    MessageSquare,
    PlugZap,
    Send,
} from 'lucide-react';
import { useState } from 'react';

import { CHART_SERIES } from '@/components/dashboard/charts/chartjs-config';
import {
    DataTableProvider,
    useDataTableContext,
} from '@/components/dashboard/data-table/context';
import DataTable from '@/components/dashboard/data-table/DataTable';
import DataTableFilter from '@/components/dashboard/data-table/DataTableFilter';
import DataTableSearch from '@/components/dashboard/data-table/DataTableSearch';
import DataTableTabs from '@/components/dashboard/data-table/DataTableTabs';
import { createDashboardColumnHelper } from '@/components/dashboard/data-table/features';
import CopyValue from '@/components/dashboard/messages/CopyValue';
import MessageDrawer from '@/components/dashboard/messages/MessageDrawer';
import MessageStatusBadge from '@/components/dashboard/messages/MessageStatusBadge';
import {
    PROVIDER_LABELS,
    shortUuid,
    STATUS_META,
    TYPE_LABELS,
} from '@/components/dashboard/messages/meta';
import type {
    MessageRecord,
    MessageStatus,
    MessageType,
} from '@/components/dashboard/messages/types';
import PageHeader from '@/components/dashboard/PageHeader';
import RequirePermission from '@/components/dashboard/RequirePermission';
import StatCard from '@/components/dashboard/StatCard';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDateTime,
    formatPhone,
    formatTimeAgo,
} from '@/lib/dashboard/format';
import {
    MOCK_MESSAGES,
    MOCK_NOW,
    MOCK_SMS_CONNECTED,
} from '@/lib/dashboard/mock';
import dashboardRoutes from '@/routes/dashboard';

/** The query parameter that holds the open message, so a link can open it. */
const MESSAGE_PARAM = 'message';
const STAT_DAYS = 7;
const DAY = 24 * 60 * 60 * 1000;

const SUCCESS_STATUSES = new Set<MessageStatus>([
    'accepted',
    'delivered',
    'opened',
]);
const PROBLEM_STATUSES = new Set<MessageStatus>(['bounced', 'failed']);
const WAITING_STATUSES = new Set<MessageStatus>(['queued', 'sent']);

const STATUS_OPTIONS = (Object.keys(STATUS_META) as MessageStatus[]).map(
    (status) => ({ label: STATUS_META[status].label, value: status }),
);
const TYPE_OPTIONS = (Object.keys(TYPE_LABELS) as MessageType[]).map(
    (type) => ({ label: TYPE_LABELS[type], value: type }),
);

/** The email providers seen in the data, newest first; the first is the one sending now. */
const EMAIL_PROVIDERS = [
    ...new Set(
        MOCK_MESSAGES.filter((message) => message.channel === 'email').map(
            (message) => message.provider,
        ),
    ),
];

const column = createDashboardColumnHelper<MessageRecord>();

const columns = column.columns([
    column.accessor('status', {
        header: 'Status',
        filterFn: 'inList',
        cell: ({ getValue }) => <MessageStatusBadge status={getValue()} />,
    }),
    column.accessor('recipient', {
        header: 'Recipient',
        cell: ({ row }) => {
            const message = row.original;
            const Icon = message.channel === 'sms' ? MessageSquare : Mail;

            return (
                <div className="flex items-center gap-3">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                        <Icon className="size-3.5" aria-hidden />
                        <span className="sr-only">
                            {message.channel === 'sms' ? 'Text' : 'Email'}
                        </span>
                    </span>
                    <div className="min-w-0">
                        <div className="truncate font-medium text-gray-800 dark:text-gray-100">
                            {message.recipientName ?? message.recipient}
                        </div>
                        <div className="truncate text-xs text-gray-500 dark:text-gray-400">
                            {message.channel === 'sms'
                                ? formatPhone(message.recipient)
                                : message.recipient}
                        </div>
                    </div>
                </div>
            );
        },
    }),
    column.accessor('type', {
        header: 'Message',
        filterFn: 'inList',
        meta: { cellClassName: 'max-w-xs' },
        cell: ({ row }) => {
            const message = row.original;

            return (
                <div className="min-w-0">
                    <div className="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {TYPE_LABELS[message.type]}
                    </div>
                    <div className="truncate text-gray-700 dark:text-gray-300">
                        {message.subject ?? message.body}
                    </div>
                </div>
            );
        },
    }),
    column.accessor((message) => message.related?.referenceNumber ?? '', {
        id: 'related',
        header: 'Related',
        cell: ({ row }) => {
            const related = row.original.related;

            if (related === null) {
                return <span className="text-gray-400">-</span>;
            }

            return (
                <Link
                    href={
                        related.kind === 'check-in'
                            ? dashboardRoutes.checkIns.show.url(related.uuid)
                            : dashboardRoutes.appointments.show.url(
                                  related.uuid,
                              )
                    }
                    onClick={(event) => event.stopPropagation()}
                    className="font-mono text-xs text-gray-600 hover:text-brand-green hover:underline dark:text-gray-300"
                >
                    {related.referenceNumber}
                </Link>
            );
        },
    }),
    column.accessor('sentAt', {
        header: 'Sent',
        sortFn: 'text',
        cell: ({ getValue }) => (
            <span
                title={formatDateTime(getValue())}
                className="text-gray-500 dark:text-gray-400"
            >
                {formatTimeAgo(getValue())}
            </span>
        ),
    }),
    column.accessor('uuid', {
        header: 'Message ID',
        enableSorting: false,
        cell: ({ getValue }) => (
            <span className="text-gray-500 dark:text-gray-400">
                <CopyValue
                    value={getValue()}
                    display={shortUuid(getValue())}
                    label="Copy message ID"
                />
            </span>
        ),
    }),
    // Backs the tabs only.
    column.accessor('channel', { filterFn: 'inList' }),
]);

const searchText = (message: MessageRecord): string =>
    [
        message.recipient,
        message.recipientName,
        message.subject,
        message.uuid,
        message.related?.referenceNumber,
    ]
        .filter(Boolean)
        .join(' ');

/** Messages per day for the stat sparklines, oldest first, as `[YYYY-MM-DD, value]`. */
function perDay(
    messages: MessageRecord[],
    valueOf: (day: MessageRecord[]) => number,
): [string, number][] {
    return Array.from({ length: STAT_DAYS }, (_, index) => {
        const day = new Date(MOCK_NOW.getTime() - (STAT_DAYS - 1 - index) * DAY)
            .toISOString()
            .slice(0, 10);

        return [
            day,
            valueOf(
                messages.filter((message) => message.sentAt.startsWith(day)),
            ),
        ];
    });
}

function deliveryRate(messages: MessageRecord[]): number {
    const settled = messages.filter(
        (message) => !WAITING_STATUSES.has(message.status),
    );

    return settled.length === 0
        ? 100
        : Math.round(
              (settled.filter((message) => SUCCESS_STATUSES.has(message.status))
                  .length /
                  settled.length) *
                  1000,
          ) / 10;
}

function Stats() {
    const since = MOCK_NOW.getTime() - STAT_DAYS * DAY;
    const recent = MOCK_MESSAGES.filter(
        (message) => new Date(message.sentAt).getTime() >= since,
    );
    const problems = recent.filter((message) =>
        PROBLEM_STATUSES.has(message.status),
    );
    const waiting = MOCK_MESSAGES.filter((message) =>
        WAITING_STATUSES.has(message.status),
    );

    return (
        <div className="mb-8 grid grid-cols-12 gap-6">
            <StatCard
                title="Sent"
                label={`Last ${STAT_DAYS} days`}
                value={recent.length}
                icon={Send}
                sparkline={{
                    label: 'Messages',
                    points: perDay(recent, (day) => day.length),
                    fill: true,
                }}
            />
            <StatCard
                title="Delivered"
                label="Delivered, opened or accepted"
                value={`${deliveryRate(recent)}%`}
                icon={MailCheck}
                sparkline={{
                    label: 'Delivery rate',
                    points: perDay(recent, deliveryRate),
                    fill: true,
                }}
            />
            <StatCard
                title="Problems"
                label="Bounced or failed"
                value={problems.length}
                icon={CircleAlert}
                delta={
                    problems.length > 0
                        ? { text: 'Needs a look', tone: 'negative' }
                        : undefined
                }
                sparkline={{
                    label: 'Bounced or failed',
                    points: perDay(problems, (day) => day.length),
                    color: CHART_SERIES.rose,
                    fill: true,
                }}
            />
            <StatCard
                title="In flight"
                label="Queued or waiting for a receipt"
                value={waiting.length}
                icon={Hourglass}
                sparkline={{
                    label: 'Waiting',
                    points: perDay(waiting, (day) => day.length),
                    color: CHART_SERIES.sky,
                    fill: true,
                }}
            />
        </div>
    );
}

function ProviderPill() {
    const [current] = EMAIL_PROVIDERS;

    if (current === undefined) {
        return null;
    }

    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-600 shadow-xs dark:bg-gray-800 dark:text-gray-300">
            <span className="relative flex size-2">
                <span className="absolute inline-flex size-full animate-ping rounded-full bg-brand-green opacity-50" />
                <span className="relative inline-flex size-2 rounded-full bg-brand-green" />
            </span>
            Email via {PROVIDER_LABELS[current]}
        </span>
    );
}

/** Shown on the SMS tab until a carrier is connected: the texts are preview data. */
function SmsNotice() {
    const { getColumnFilter } = useDataTableContext();
    const [channel] = getColumnFilter('channel');

    if (MOCK_SMS_CONNECTED || channel !== 'sms') {
        return null;
    }

    return (
        <div className="mb-5 flex items-start gap-3 rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-500/40 dark:bg-amber-500/10">
            <PlugZap
                className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                aria-hidden
            />
            <div>
                <div className="font-medium text-amber-800 dark:text-amber-300">
                    No SMS carrier connected
                </div>
                <p className="text-amber-700/90 dark:text-amber-300/80">
                    These texts are preview data. Connect a carrier before
                    launch so drivers get texts and their delivery receipts show
                    here.
                </p>
            </div>
        </div>
    );
}

function messageParam(url: string): string | null {
    const query = url.split('?')[1] ?? '';

    return new URLSearchParams(query).get(MESSAGE_PARAM);
}

/** Puts the open message in the URL without a server visit or a new history entry. */
function showMessage(uuid: string | null): void {
    const url = new URL(window.location.href);

    if (uuid === null) {
        url.searchParams.delete(MESSAGE_PARAM);
    } else {
        url.searchParams.set(MESSAGE_PARAM, uuid);
    }

    router.replace({
        url: `${url.pathname}${url.search}`,
        preserveState: true,
        preserveScroll: true,
    });
}

export default function MessagesIndex() {
    const { url } = usePage();
    const openUuid = messageParam(url);
    const openMessage =
        MOCK_MESSAGES.find((message) => message.uuid === openUuid) ?? null;
    // The rows the drawer was opened from, in table order; a direct link steps through them all.
    const [stepRows, setStepRows] = useState<MessageRecord[]>(MOCK_MESSAGES);

    const index =
        openMessage === null
            ? -1
            : stepRows.findIndex((message) => message.uuid === openUuid);
    const stepTo = (offset: number) => {
        const target = stepRows[index + offset];

        return index === -1 || target === undefined
            ? null
            : () => showMessage(target.uuid);
    };

    const countFor = (channel: MessageRecord['channel']) =>
        MOCK_MESSAGES.filter((message) => message.channel === channel).length;
    const tabs = [
        { label: 'All', value: null, count: MOCK_MESSAGES.length },
        { label: 'Email', value: 'email', count: countFor('email') },
        { label: 'SMS', value: 'sms', count: countFor('sms') },
    ];

    return (
        <RequirePermission permission="view messages">
            <DataTableProvider>
                <Head title="Messages" />

                <PageHeader
                    title="Messages"
                    subtitle="Every email and text sent to drivers, and whether it arrived."
                    actions={
                        <>
                            <div className="hidden items-center sm:flex">
                                <ProviderPill />
                            </div>
                            <DataTableSearch placeholder="Recipient, subject or ID…" />
                            <DataTableFilter
                                columnId="type"
                                title="Message"
                                options={TYPE_OPTIONS}
                            />
                            <DataTableFilter
                                columnId="status"
                                title="Status"
                                options={STATUS_OPTIONS}
                            />
                        </>
                    }
                />

                <Stats />

                <div className="mb-5">
                    <DataTableTabs columnId="channel" tabs={tabs} />
                </div>

                <SmsNotice />

                <DataTable
                    data={MOCK_MESSAGES}
                    columns={columns}
                    getRowId={(message) => message.uuid}
                    getSearchText={searchText}
                    title="Messages"
                    pageSize={15}
                    hiddenColumns={['channel']}
                    emptyMessage="No messages match these filters."
                    onRowClick={(message, rows) => {
                        setStepRows(rows);
                        showMessage(message.uuid);
                    }}
                    activeRowId={openMessage?.uuid ?? null}
                />

                <MessageDrawer
                    message={openMessage}
                    position={
                        index === -1
                            ? null
                            : { index: index + 1, total: stepRows.length }
                    }
                    onClose={() => showMessage(null)}
                    onPrevious={stepTo(-1)}
                    onNext={stepTo(1)}
                />
            </DataTableProvider>
        </RequirePermission>
    );
}

MessagesIndex.layout = DashboardLayout;
