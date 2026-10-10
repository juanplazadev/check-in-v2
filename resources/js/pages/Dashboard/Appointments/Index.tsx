import { Head, Link } from '@inertiajs/react';

import { DataTableProvider } from '@/components/dashboard/data-table/context';
import DataTable from '@/components/dashboard/data-table/DataTable';
import DataTableFilter from '@/components/dashboard/data-table/DataTableFilter';
import DataTableSearch from '@/components/dashboard/data-table/DataTableSearch';
import DataTableTabs from '@/components/dashboard/data-table/DataTableTabs';
import { createDashboardColumnHelper } from '@/components/dashboard/data-table/features';
import PageHeader from '@/components/dashboard/PageHeader';
import type {
    AppointmentRecord,
    AppointmentStatus,
} from '@/components/dashboard/types';
import StatusBadge from '@/components/StatusBadge';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDate,
    formatLabel,
    formatPhone,
    formatTime,
} from '@/lib/dashboard/format';
import { MOCK_APPOINTMENTS, MOCK_LOCATIONS } from '@/lib/dashboard/mock';
import dashboardRoutes from '@/routes/dashboard';

const column = createDashboardColumnHelper<AppointmentRecord>();

const columns = column.columns([
    column.accessor('referenceNumber', {
        header: 'Reference',
        cell: ({ row, getValue }) => (
            <Link
                href={dashboardRoutes.appointments.show.url(row.original.uuid)}
                className="font-semibold text-gray-800 hover:text-brand-green dark:text-gray-100"
            >
                {getValue()}
            </Link>
        ),
    }),
    column.accessor('scheduledFor', {
        header: 'Scheduled for',
        cell: ({ getValue }) => (
            <div>
                <div className="font-medium text-gray-800 dark:text-gray-100">
                    {formatDate(getValue())}
                </div>
                <div className="text-xs text-gray-400 dark:text-gray-500">
                    {formatTime(getValue())}
                </div>
            </div>
        ),
    }),
    column.accessor((appointment) => appointment.driver.name, {
        id: 'driver',
        header: 'Driver',
        cell: ({ row, getValue }) => (
            <div>
                <div className="font-medium text-gray-800 dark:text-gray-100">
                    {getValue()}
                </div>
                <div className="text-xs text-gray-400 dark:text-gray-500">
                    {formatPhone(row.original.driver.cellphone)}
                </div>
            </div>
        ),
    }),
    column.accessor((appointment) => appointment.location.name, {
        id: 'location',
        header: 'Location',
        filterFn: 'inList',
        cell: ({ getValue }) => getValue().split(' - ')[0],
    }),
    column.accessor((appointment) => appointment.purchaseOrders.length, {
        id: 'purchaseOrders',
        header: 'POs',
        meta: {
            cellClassName: 'text-center',
            headerClassName: 'font-semibold text-center',
        },
    }),
    column.accessor('status', {
        header: 'Status',
        filterFn: 'inList',
        cell: ({ getValue }) => (
            <StatusBadge status={getValue()} label={formatLabel(getValue())} />
        ),
    }),
]);

const STATUSES: AppointmentStatus[] = [
    'scheduled',
    'checked-in',
    'completed',
    'no-show',
    'cancelled',
];

const tabs = [
    { label: 'All', value: null, count: MOCK_APPOINTMENTS.length },
    ...STATUSES.map((status) => ({
        label: formatLabel(status),
        value: status,
        count: MOCK_APPOINTMENTS.filter(
            (appointment) => appointment.status === status,
        ).length,
    })),
];

const locationOptions = MOCK_LOCATIONS.map((location) => ({
    label: location.name.split(' - ')[0],
    value: location.name,
}));

function searchText(appointment: AppointmentRecord): string {
    return [
        appointment.referenceNumber,
        appointment.driver.name,
        appointment.driver.cellphone,
        ...appointment.purchaseOrders,
    ].join(' ');
}

export default function AppointmentsIndex() {
    return (
        <DataTableProvider>
            <Head title="Appointments" />

            <PageHeader
                title="Appointments"
                actions={
                    <>
                        <DataTableSearch placeholder="Reference, driver, phone, PO…" />
                        <DataTableFilter
                            columnId="location"
                            title="Locations"
                            options={locationOptions}
                        />
                    </>
                }
            />

            <div className="mb-5">
                <DataTableTabs columnId="status" tabs={tabs} />
            </div>

            <DataTable
                data={MOCK_APPOINTMENTS}
                columns={columns}
                getRowId={(appointment) => appointment.uuid}
                getSearchText={searchText}
                title="All appointments"
                initialSorting={[{ id: 'scheduledFor', desc: true }]}
                emptyMessage="No appointments match these filters."
            />
        </DataTableProvider>
    );
}

AppointmentsIndex.layout = DashboardLayout;
