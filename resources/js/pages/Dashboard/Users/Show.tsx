import { Head, usePage } from '@inertiajs/react';

import AdminOnly from '@/components/dashboard/AdminOnly';
import BackLink from '@/components/dashboard/BackLink';
import { DetailCard, DetailRow } from '@/components/dashboard/DetailCard';
import PageHeader from '@/components/dashboard/PageHeader';
import RecordNotFound from '@/components/dashboard/RecordNotFound';
import RoleChips from '@/components/dashboard/RoleChips';
import DashboardLayout from '@/layouts/DashboardLayout';
import {
    formatDateTime,
    formatPhone,
    initialsOf,
    lastPathSegment,
} from '@/lib/dashboard/format';
import { MOCK_USERS } from '@/lib/dashboard/mock';
import dashboardRoutes from '@/routes/dashboard';

export default function UserShow() {
    const { url } = usePage();
    const id = Number(lastPathSegment(url));
    const user = MOCK_USERS.find((record) => record.id === id);
    const backHref = dashboardRoutes.users.url();

    return (
        <AdminOnly>
            {user === undefined ? (
                <>
                    <Head title="User not found" />
                    <RecordNotFound
                        backHref={backHref}
                        backLabel="Back to users"
                    />
                </>
            ) : (
                <>
                    <Head title={user.name} />

                    <BackLink href={backHref} label="Users" />

                    <div className="mb-8 flex items-center gap-4">
                        <span className="flex size-16 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-green to-green-600 text-xl font-semibold text-white">
                            {initialsOf(user.name)}
                        </span>
                        <div className="min-w-0">
                            <PageHeader
                                title={user.name}
                                subtitle={user.email}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <DetailCard title="Account">
                            <DetailRow label="Email">{user.email}</DetailRow>
                            <DetailRow label="Email verified">
                                {user.emailVerifiedAt
                                    ? formatDateTime(user.emailVerifiedAt)
                                    : 'Not verified'}
                            </DetailRow>
                            <DetailRow label="Cellphone">
                                {user.cellphone
                                    ? formatPhone(user.cellphone)
                                    : null}
                            </DetailRow>
                            <DetailRow label="Joined">
                                {formatDateTime(user.createdAt)}
                            </DetailRow>
                            <DetailRow label="Last active">
                                {user.lastActiveAt
                                    ? formatDateTime(user.lastActiveAt)
                                    : 'Never'}
                            </DetailRow>
                        </DetailCard>

                        <DetailCard title="Access">
                            <DetailRow label="Roles">
                                <RoleChips roles={user.roles} />
                            </DetailRow>
                            <DetailRow label="Locations">
                                {user.locations.length > 0
                                    ? user.locations
                                          .map(
                                              (location) =>
                                                  location.name.split(' - ')[0],
                                          )
                                          .join(', ')
                                    : user.roles.includes('admin')
                                      ? 'All locations'
                                      : null}
                            </DetailRow>
                            {user.roles.length === 0 && (
                                <>
                                    <DetailRow label="Check-ins">
                                        {user.checkInsCount}
                                    </DetailRow>
                                    <DetailRow label="Appointments">
                                        {user.appointmentsCount}
                                    </DetailRow>
                                </>
                            )}
                        </DetailCard>
                    </div>
                </>
            )}
        </AdminOnly>
    );
}

UserShow.layout = DashboardLayout;
