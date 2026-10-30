import { Head, Link, router } from '@inertiajs/react';
import { Package, Plus, TriangleAlert } from 'lucide-react';
import { useState } from 'react';

import LocationLoadTypeController from '@/actions/App/Http/Controllers/Dashboard/LocationLoadTypeController';
import {
    LoadTypeDialog,
    formatDuration,
} from '@/components/dashboard/location/LoadTypeDialog';
import SectionHeader, {
    SectionGroup,
} from '@/components/dashboard/location/SectionHeader';
import { StatusConfirmDialog } from '@/components/dashboard/RecordActionDialogs';
import type {
    DashboardLocationSummary,
    LocationLoadType,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';
import dashboardRoutes from '@/routes/dashboard';

interface LocationLoadTypesProps {
    location: DashboardLocationSummary;
    loadTypes: LocationLoadType[];
    slotIntervalMinutes: number;
    /** Bookings the location holds at once; null when it has no docks that take appointments. */
    dockCapacity: number | null;
}

const SMALL_BUTTON =
    'btn-sm border-gray-200 text-gray-600 hover:border-gray-300 hover:text-gray-800 dark:border-gray-700/60 dark:text-gray-300 dark:hover:border-gray-600';
const REMOVE_BUTTON =
    'btn-sm border-gray-200 text-gray-600 hover:border-rose-300 hover:text-rose-600 dark:border-gray-700/60 dark:text-gray-300 dark:hover:border-rose-500/60 dark:hover:text-rose-400';

const DIRECTIONS = [
    { value: 'inbound', title: 'Inbound' },
    { value: 'outbound', title: 'Outbound' },
] as const;

function NoDocksWarning({ locationUuid }: { locationUuid: string }) {
    return (
        <div className="mb-4 flex gap-3 rounded-lg border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
            <p>
                This location has no docks that take appointments yet, so{' '}
                <strong>any number of trucks can book the same time</strong> and
                load durations don't block any slots. Durations still stop a
                load from running past closing time.{' '}
                <Link
                    href={dashboardRoutes.locations.docks.url(locationUuid)}
                    className="font-semibold underline"
                >
                    Add docks
                </Link>{' '}
                to put them into effect.
            </p>
        </div>
    );
}

function BookingNote({
    hasActive,
    slotIntervalMinutes,
    dockCapacity,
}: {
    hasActive: boolean;
    slotIntervalMinutes: number;
    dockCapacity: number | null;
}) {
    return (
        <div className="mb-6 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-900/20 dark:text-gray-300">
            {hasActive ? (
                <>
                    Drivers <strong>pick one of these when they book</strong>. A
                    booking holds its dock for the whole duration, so a long
                    load blocks the slots it runs through and has to finish by
                    closing time.
                    {dockCapacity !== null && (
                        <>
                            {' '}
                            Up to{' '}
                            <strong>
                                {dockCapacity}{' '}
                                {dockCapacity === 1 ? 'truck' : 'trucks'} at
                                once
                            </strong>
                            , one per dock.
                        </>
                    )}
                </>
            ) : (
                <>
                    No active load types, so drivers book without choosing one
                    and{' '}
                    <strong>
                        every booking lasts one slot (
                        {formatDuration(slotIntervalMinutes)})
                    </strong>
                    .
                </>
            )}
        </div>
    );
}

function LoadTypeRow({
    loadType,
    onEdit,
    onRemove,
}: {
    loadType: LocationLoadType;
    /** Absent when the viewer may not manage load types. */
    onEdit?: () => void;
    onRemove?: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center gap-3 py-3">
            <span className="min-w-0 flex-1">
                <span className="block truncate font-medium text-gray-800 dark:text-gray-100">
                    {loadType.name}
                </span>
                <span className="block text-sm text-gray-500 dark:text-gray-400">
                    {formatDuration(loadType.durationMinutes)}
                    {loadType.upcomingCount > 0 &&
                        ` · ${loadType.upcomingCount} upcoming ${
                            loadType.upcomingCount === 1
                                ? 'appointment'
                                : 'appointments'
                        }`}
                </span>
            </span>
            {loadType.isActive ? (
                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    Bookable
                </span>
            ) : (
                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                    Off
                </span>
            )}
            {onEdit && (
                <button type="button" onClick={onEdit} className={SMALL_BUTTON}>
                    Edit
                </button>
            )}
            {onRemove && !loadType.hasRecords && (
                <button
                    type="button"
                    onClick={onRemove}
                    className={REMOVE_BUTTON}
                >
                    Remove
                </button>
            )}
        </li>
    );
}

function EmptyLoadTypes() {
    return (
        <div className="flex items-start gap-4 rounded-lg border border-dashed border-gray-200 p-5 dark:border-gray-700/60">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                <Package className="size-5" aria-hidden />
            </span>
            <div className="min-w-0">
                <h4 className="font-semibold text-gray-800 dark:text-gray-100">
                    No load types yet
                </h4>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Add the kinds of loads this location takes, like a live
                    unload or a drop trailer, with how long each one holds a
                    dock.
                </p>
            </div>
        </div>
    );
}

export default function LocationLoadTypesPage({
    location,
    loadTypes,
    slotIntervalMinutes,
    dockCapacity,
}: LocationLoadTypesProps) {
    const can = useCan();
    const canManage = can('manage load types');
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<LocationLoadType | null>(null);
    const [removing, setRemoving] = useState<LocationLoadType | null>(null);
    const [busy, setBusy] = useState(false);
    const hasActive = loadTypes.some((loadType) => loadType.isActive);

    const groups = DIRECTIONS.map((direction) => ({
        ...direction,
        loadTypes: loadTypes.filter(
            (loadType) => loadType.direction === direction.value,
        ),
    })).filter((group) => group.loadTypes.length > 0);

    const remove = () => {
        if (removing === null) {
            return;
        }

        router.visit(
            LocationLoadTypeController.destroy({
                uuid: location.uuid,
                loadTypeUuid: removing.id,
            }),
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setRemoving(null);
                },
            },
        );
    };

    return (
        <>
            <Head title={`${location.name} load types`} />

            <SectionHeader
                title="Load types"
                description="The kinds of loads drivers book, and how long each one holds a dock."
                action={
                    canManage && (
                        <button
                            type="button"
                            onClick={() => setAdding(true)}
                            className="btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                        >
                            <Plus className="size-4" aria-hidden />
                            Add load type
                        </button>
                    )
                }
            />

            {hasActive && dockCapacity === null && (
                <NoDocksWarning locationUuid={location.uuid} />
            )}

            <BookingNote
                hasActive={hasActive}
                slotIntervalMinutes={slotIntervalMinutes}
                dockCapacity={dockCapacity}
            />

            {groups.length === 0 ? (
                <EmptyLoadTypes />
            ) : (
                groups.map((group) => (
                    <SectionGroup key={group.value} title={group.title}>
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                            {group.loadTypes.map((loadType) => (
                                <LoadTypeRow
                                    key={loadType.id}
                                    loadType={loadType}
                                    onEdit={
                                        canManage
                                            ? () => setEditing(loadType)
                                            : undefined
                                    }
                                    onRemove={
                                        canManage
                                            ? () => setRemoving(loadType)
                                            : undefined
                                    }
                                />
                            ))}
                        </ul>
                    </SectionGroup>
                ))
            )}

            {canManage && (
                <>
                    <LoadTypeDialog
                        open={adding || editing !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setAdding(false);
                                setEditing(null);
                            }
                        }}
                        locationUuid={location.uuid}
                        loadType={editing ?? undefined}
                    />
                    <StatusConfirmDialog
                        open={removing !== null}
                        onOpenChange={(open) => !open && setRemoving(null)}
                        title={`Remove ${removing?.name ?? ''}?`}
                        description="This load type has never been booked, so nothing else changes."
                        keepLabel="Keep"
                        confirmLabel={busy ? 'Removing…' : 'Remove'}
                        busy={busy}
                        onConfirm={remove}
                    />
                </>
            )}
        </>
    );
}

LocationLoadTypesPage.layout = [DashboardLayout, LocationLayout];
