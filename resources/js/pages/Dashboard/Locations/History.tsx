import { Head, router } from '@inertiajs/react';

import DataTablePagination from '@/components/dashboard/data-table/DataTablePagination';
import { HistoryEntries } from '@/components/dashboard/HistoryTimeline';
import SectionHeader from '@/components/dashboard/location/SectionHeader';
import type {
    DashboardLocationSummary,
    LocationHistory,
} from '@/components/dashboard/types';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';
import dashboardRoutes from '@/routes/dashboard';

interface LocationHistoryProps {
    location: DashboardLocationSummary;
    history: LocationHistory;
}

export default function LocationHistoryPage({
    location,
    history,
}: LocationHistoryProps) {
    const changePage = (pageIndex: number) =>
        router.get(
            dashboardRoutes.locations.history.url(location.uuid),
            pageIndex > 0 ? { page: pageIndex + 1 } : {},
            { only: ['history'], preserveState: true },
        );

    return (
        <>
            <Head title={`${location.name} history`} />

            <SectionHeader
                title="History"
                description="Every change to this location's details and staff, newest first, with who made it."
            />

            <HistoryEntries entries={history.data} className="max-w-2xl" />

            {history.total > history.per_page && (
                <div className="mt-6 max-w-2xl">
                    <DataTablePagination
                        pageIndex={history.current_page - 1}
                        pageSize={history.per_page}
                        totalRows={history.total}
                        onPageChange={changePage}
                    />
                </div>
            )}
        </>
    );
}

LocationHistoryPage.layout = [DashboardLayout, LocationLayout];
