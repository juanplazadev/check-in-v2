import { Head, Link } from '@inertiajs/react';
import { format } from 'date-fns';
import { AlertTriangle, CalendarClock, Clock, Truck } from 'lucide-react';

import Card from '@/components/dashboard/Card';
import BarChart from '@/components/dashboard/charts/BarChart';
import { CHART_SERIES } from '@/components/dashboard/charts/chartjs-config';
import LineChart from '@/components/dashboard/charts/LineChart';
import PageHeader from '@/components/dashboard/PageHeader';
import StatCard from '@/components/dashboard/StatCard';
import type { AppointmentStatus } from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatLabel,
    formatRelative,
    formatTime,
} from '@/lib/dashboard/format';
import {
    checkInsPerDay,
    isSameMockDay,
    MOCK_APPOINTMENTS,
    MOCK_CHECK_INS,
    MOCK_LOCATIONS,
    MOCK_NOW,
} from '@/lib/dashboard/mock';
import dashboardRoutes from '@/routes/dashboard';

// Everything below is derived from mock data and becomes page props with the backend.
const last28Days = checkInsPerDay(28);
const last14Days = last28Days.slice(-14);
const thisWeek = last14Days
    .slice(-7)
    .reduce((sum, [, count]) => sum + count, 0);
const previousWeek = last14Days
    .slice(0, 7)
    .reduce((sum, [, count]) => sum + count, 0);
const weekChange =
    previousWeek === 0
        ? 0
        : Math.round(((thisWeek - previousWeek) / previousWeek) * 100);

const checkInsToday = MOCK_CHECK_INS.filter((checkIn) =>
    isSameMockDay(checkIn.createdAt),
);
const pendingCheckIns = MOCK_CHECK_INS.filter(
    (checkIn) => checkIn.status === 'pending',
);
const erpFailures = MOCK_CHECK_INS.filter(
    (checkIn) => checkIn.erpStatus === 'failed',
);
const appointmentsToday = MOCK_APPOINTMENTS.filter((appointment) =>
    isSameMockDay(appointment.scheduledFor),
);
const upcomingToday = appointmentsToday.filter(
    (appointment) => appointment.status === 'scheduled',
);

const APPOINTMENT_STATUS_SERIES: {
    status: AppointmentStatus;
    color: string;
}[] = [
    { status: 'scheduled', color: CHART_SERIES.sky },
    { status: 'completed', color: CHART_SERIES.green },
    { status: 'no-show', color: CHART_SERIES.rose },
    { status: 'cancelled', color: CHART_SERIES.gray },
];

const locationLabels = MOCK_LOCATIONS.map(
    (location) => location.name.split(' - ')[0],
);
const appointmentsByLocation = APPOINTMENT_STATUS_SERIES.map(
    ({ status, color }) => ({
        label: formatLabel(status),
        color,
        values: MOCK_LOCATIONS.map(
            (location) =>
                MOCK_APPOINTMENTS.filter(
                    (appointment) =>
                        appointment.location.id === location.id &&
                        appointment.status === status,
                ).length,
        ),
    }),
);

const recentCheckIns = MOCK_CHECK_INS.slice(0, 6);

const recentActivity = [
    ...MOCK_CHECK_INS.slice(0, 15).flatMap((checkIn) =>
        checkIn.history.map((entry) => ({
            ...entry,
            reference: checkIn.referenceNumber,
            href: dashboardRoutes.checkIns.show.url(checkIn.uuid),
        })),
    ),
    ...MOCK_APPOINTMENTS.slice(0, 15).flatMap((appointment) =>
        appointment.history.map((entry) => ({
            ...entry,
            reference: appointment.referenceNumber,
            href: dashboardRoutes.appointments.show.url(appointment.uuid),
        })),
    ),
]
    .filter((entry) => entry.createdAt <= MOCK_NOW.toISOString())
    .sort((a, b) => b.createdAt.localeCompare(a.createdAt))
    .slice(0, 7);

export default function DashboardIndex() {
    return (
        <>
            <Head title="Dashboard" />

            <PageHeader
                title="Overview"
                subtitle={format(MOCK_NOW, 'EEEE, MMMM d')}
                actions={
                    <Link
                        href={dashboardRoutes.checkIns.url()}
                        className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        <Truck className="size-4 shrink-0" />
                        <span className="ml-2">View check-ins</span>
                    </Link>
                }
            />

            <div className="grid grid-cols-12 gap-6">
                <StatCard
                    title="Check-ins"
                    label="Today"
                    value={checkInsToday.length}
                    icon={Truck}
                    delta={{
                        text: `${weekChange >= 0 ? '+' : ''}${weekChange}% wk`,
                        tone: weekChange >= 0 ? 'positive' : 'negative',
                    }}
                    sparkline={{
                        label: 'Check-ins per day',
                        points: last14Days,
                        fill: true,
                    }}
                />
                <StatCard
                    title="Waiting"
                    label="Pending check-ins"
                    value={pendingCheckIns.length}
                    icon={Clock}
                    delta={{
                        text: 'needs action',
                        tone:
                            pendingCheckIns.length > 0 ? 'neutral' : 'positive',
                    }}
                />
                <StatCard
                    title="Appointments"
                    label="Today"
                    value={appointmentsToday.length}
                    icon={CalendarClock}
                    delta={{
                        text: `${upcomingToday.length} upcoming`,
                        tone: 'neutral',
                    }}
                />
                <StatCard
                    title="ERP sync"
                    label="Failed"
                    value={erpFailures.length}
                    icon={AlertTriangle}
                    delta={{
                        text:
                            erpFailures.length > 0
                                ? 'retry needed'
                                : 'all synced',
                        tone: erpFailures.length > 0 ? 'negative' : 'positive',
                    }}
                />

                <Card
                    title="Check-ins, last 28 days"
                    className="col-span-full xl:col-span-8"
                >
                    <LineChart
                        series={[
                            {
                                label: 'Check-ins',
                                points: last28Days,
                                fill: true,
                            },
                        ]}
                        height={248}
                    />
                </Card>

                <Card
                    title="Appointments by location"
                    className="col-span-full xl:col-span-4"
                >
                    <BarChart
                        labels={locationLabels}
                        series={appointmentsByLocation}
                        height={248}
                        stacked
                    />
                </Card>

                {/* Recent check-ins (Mosaic DashboardCard07) */}
                <Card
                    title="Latest check-ins"
                    className="col-span-full xl:col-span-8"
                    action={
                        <Link
                            href={dashboardRoutes.checkIns.url()}
                            className="text-sm font-medium text-brand-green hover:text-green-700"
                        >
                            View all -&gt;
                        </Link>
                    }
                >
                    <div className="overflow-x-auto p-3">
                        <table className="w-full table-auto dark:text-gray-300">
                            <thead className="rounded-xs bg-gray-50 text-xs text-gray-400 uppercase dark:bg-gray-700/50 dark:text-gray-500">
                                <tr>
                                    <th className="p-2 text-left font-semibold">
                                        Reference
                                    </th>
                                    <th className="p-2 text-left font-semibold">
                                        Driver
                                    </th>
                                    <th className="p-2 text-left font-semibold">
                                        Location
                                    </th>
                                    <th className="p-2 text-left font-semibold">
                                        Time
                                    </th>
                                    <th className="p-2 text-left font-semibold">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 text-sm font-medium dark:divide-gray-700/60">
                                {recentCheckIns.map((checkIn) => (
                                    <tr key={checkIn.uuid}>
                                        <td className="p-2 whitespace-nowrap">
                                            <Link
                                                href={dashboardRoutes.checkIns.show.url(
                                                    checkIn.uuid,
                                                )}
                                                className="font-semibold text-gray-800 hover:text-brand-green dark:text-gray-100"
                                            >
                                                {checkIn.referenceNumber}
                                            </Link>
                                        </td>
                                        <td className="p-2 whitespace-nowrap">
                                            {checkIn.driver.name}
                                        </td>
                                        <td className="p-2 whitespace-nowrap">
                                            {
                                                checkIn.location.name.split(
                                                    ' - ',
                                                )[0]
                                            }
                                        </td>
                                        <td className="p-2 whitespace-nowrap">
                                            {formatTime(checkIn.createdAt)}
                                        </td>
                                        <td className="p-2 whitespace-nowrap">
                                            <StatusBadge
                                                status={checkIn.status}
                                                label={formatLabel(
                                                    checkIn.status,
                                                )}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {/* Recent activity (Mosaic DashboardCard10) */}
                <Card
                    title="Recent activity"
                    className="col-span-full xl:col-span-4"
                >
                    <ul className="p-3">
                        {recentActivity.map((entry) => (
                            <li key={entry.id} className="flex px-2">
                                <span
                                    className={`my-2 mr-3 size-2 shrink-0 rounded-full ${
                                        entry.event === 'notification-failed'
                                            ? 'bg-red-500'
                                            : entry.event === 'status-changed'
                                              ? 'bg-sky-500'
                                              : 'bg-brand-green'
                                    }`}
                                />
                                <div className="grow border-b border-gray-100 pb-2 text-sm dark:border-gray-700/60">
                                    <Link
                                        href={entry.href}
                                        className="font-medium text-gray-800 hover:text-brand-green dark:text-gray-100"
                                    >
                                        {entry.reference}
                                    </Link>{' '}
                                    <span className="text-gray-600 dark:text-gray-300">
                                        {entry.description}
                                    </span>
                                    <div className="text-xs text-gray-400 dark:text-gray-500">
                                        {formatRelative(entry.createdAt)}
                                        {entry.actor && ` · ${entry.actor}`}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </>
    );
}

DashboardIndex.layout = DashboardLayout;
