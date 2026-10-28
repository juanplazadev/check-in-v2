import { Head, router } from '@inertiajs/react';
import { Building2, Plus, Warehouse } from 'lucide-react';
import { useState } from 'react';

import LocationBuildingController from '@/actions/App/Http/Controllers/Dashboard/LocationBuildingController';
import LocationDockController from '@/actions/App/Http/Controllers/Dashboard/LocationDockController';
import {
    BuildingDialog,
    DockDialog,
} from '@/components/dashboard/location/DockDialog';
import SectionHeader, {
    SectionGroup,
} from '@/components/dashboard/location/SectionHeader';
import { StatusConfirmDialog } from '@/components/dashboard/RecordActionDialogs';
import type {
    DashboardLocationSummary,
    LocationBuilding,
    LocationDock,
    LocationDocks,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';

interface LocationDocksProps {
    location: DashboardLocationSummary;
    docks: LocationDocks;
}

type Removal =
    | { kind: 'dock'; dock: LocationDock }
    | { kind: 'building'; building: LocationBuilding };

const SMALL_BUTTON =
    'btn-sm border-gray-200 text-gray-600 hover:border-gray-300 hover:text-gray-800 dark:border-gray-700/60 dark:text-gray-300 dark:hover:border-gray-600';
const REMOVE_BUTTON =
    'btn-sm border-gray-200 text-gray-600 hover:border-rose-300 hover:text-rose-600 dark:border-gray-700/60 dark:text-gray-300 dark:hover:border-rose-500/60 dark:hover:text-rose-400';

function CapacityNote({ capacity }: { capacity: number | null }) {
    return (
        <div className="mb-6 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-900/20 dark:text-gray-300">
            {capacity === null ? (
                <>
                    No dock takes appointments yet, so{' '}
                    <strong>any number of trucks can book the same time</strong>
                    . Add docks to limit each time slot.
                </>
            ) : (
                <>
                    Each appointment time slot takes up to{' '}
                    <strong>
                        {capacity} {capacity === 1 ? 'truck' : 'trucks'}
                    </strong>
                    , one per active dock that takes appointments.
                </>
            )}
        </div>
    );
}

function DockStatus({ dock }: { dock: LocationDock }) {
    if (!dock.isActive) {
        return (
            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                Off
            </span>
        );
    }

    if (!dock.acceptsAppointments) {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                Walk-ins only
            </span>
        );
    }

    return (
        <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
            Takes appointments
        </span>
    );
}

function DockRow({
    dock,
    onEdit,
    onRemove,
}: {
    dock: LocationDock;
    /** Absent when the viewer may not manage docks. */
    onEdit?: () => void;
    onRemove?: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center gap-3 py-3">
            <span className="min-w-0 flex-1">
                <span className="block truncate font-medium text-gray-800 dark:text-gray-100">
                    {dock.name}
                </span>
                {dock.upcomingCount > 0 && (
                    <span className="block text-sm text-gray-500 dark:text-gray-400">
                        {dock.upcomingCount} upcoming{' '}
                        {dock.upcomingCount === 1
                            ? 'appointment'
                            : 'appointments'}
                    </span>
                )}
            </span>
            <DockStatus dock={dock} />
            {onEdit && (
                <button type="button" onClick={onEdit} className={SMALL_BUTTON}>
                    Edit
                </button>
            )}
            {onRemove && !dock.hasRecords && (
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

function EmptyDocks() {
    return (
        <div className="flex items-start gap-4 rounded-lg border border-dashed border-gray-200 p-5 dark:border-gray-700/60">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700/60 dark:text-gray-400">
                <Warehouse className="size-5" aria-hidden />
            </span>
            <div className="min-w-0">
                <h4 className="font-semibold text-gray-800 dark:text-gray-100">
                    No docks yet
                </h4>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Add the location's dock doors to limit how many trucks book
                    the same time and to place appointments on a door. If the
                    site has more than one building, add the buildings first.
                </p>
            </div>
        </div>
    );
}

export default function LocationDocksPage({
    location,
    docks,
}: LocationDocksProps) {
    const can = useCan();
    const canManage = can('manage docks');
    const [addingDock, setAddingDock] = useState(false);
    const [editingDock, setEditingDock] = useState<LocationDock | null>(null);
    const [addingBuilding, setAddingBuilding] = useState(false);
    const [renaming, setRenaming] = useState<LocationBuilding | null>(null);
    const [removing, setRemoving] = useState<Removal | null>(null);
    const [busy, setBusy] = useState(false);

    // Grouped as the page reads them: each building, then docks with none.
    const groups = [
        ...docks.buildings.map((building) => ({
            building,
            docks: docks.docks.filter(
                (dock) => dock.buildingId === building.id,
            ),
        })),
        {
            building: null,
            docks: docks.docks.filter((dock) => dock.buildingId === null),
        },
    ].filter((group) => group.building !== null || group.docks.length > 0);

    const remove = () => {
        if (removing === null) {
            return;
        }

        const route =
            removing.kind === 'dock'
                ? LocationDockController.destroy({
                      uuid: location.uuid,
                      dockUuid: removing.dock.id,
                  })
                : LocationBuildingController.destroy({
                      uuid: location.uuid,
                      buildingUuid: removing.building.id,
                  });

        router.visit(route, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => {
                setBusy(false);
                setRemoving(null);
            },
        });
    };

    const removalName =
        removing?.kind === 'dock'
            ? removing.dock.name
            : (removing?.building.name ?? '');

    return (
        <>
            <Head title={`${location.name} docks`} />

            <SectionHeader
                title="Docks & buildings"
                description="The dock doors trucks are sent to, grouped by building."
                action={
                    canManage && (
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => setAddingBuilding(true)}
                                className="btn gap-2 border-gray-200 text-gray-800 hover:border-gray-300 dark:border-gray-700/60 dark:text-gray-100"
                            >
                                <Building2 className="size-4" aria-hidden />
                                Add building
                            </button>
                            <button
                                type="button"
                                onClick={() => setAddingDock(true)}
                                className="btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                            >
                                <Plus className="size-4" aria-hidden />
                                Add dock
                            </button>
                        </div>
                    )
                }
            />

            <CapacityNote capacity={docks.capacity} />

            {groups.length === 0 ? (
                <EmptyDocks />
            ) : (
                groups.map(({ building, docks: groupDocks }) => (
                    <SectionGroup
                        key={building?.id ?? 'none'}
                        title={
                            building
                                ? building.name
                                : docks.buildings.length > 0
                                  ? 'No building'
                                  : 'Docks'
                        }
                    >
                        {building && canManage && (
                            <div className="mb-2 flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setRenaming(building)}
                                    className={SMALL_BUTTON}
                                >
                                    Rename
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setRemoving({
                                            kind: 'building',
                                            building,
                                        })
                                    }
                                    className={REMOVE_BUTTON}
                                >
                                    Remove building
                                </button>
                            </div>
                        )}
                        {groupDocks.length === 0 ? (
                            <p className="py-3 text-sm text-gray-500 dark:text-gray-400">
                                No docks in this building yet.
                            </p>
                        ) : (
                            <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                                {groupDocks.map((dock) => (
                                    <DockRow
                                        key={dock.id}
                                        dock={dock}
                                        onEdit={
                                            canManage
                                                ? () => setEditingDock(dock)
                                                : undefined
                                        }
                                        onRemove={
                                            canManage
                                                ? () =>
                                                      setRemoving({
                                                          kind: 'dock',
                                                          dock,
                                                      })
                                                : undefined
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </SectionGroup>
                ))
            )}

            {canManage && (
                <>
                    <DockDialog
                        open={addingDock || editingDock !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setAddingDock(false);
                                setEditingDock(null);
                            }
                        }}
                        locationUuid={location.uuid}
                        buildings={docks.buildings}
                        dock={editingDock ?? undefined}
                    />
                    <BuildingDialog
                        open={addingBuilding || renaming !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setAddingBuilding(false);
                                setRenaming(null);
                            }
                        }}
                        locationUuid={location.uuid}
                        building={renaming ?? undefined}
                    />
                    <StatusConfirmDialog
                        open={removing !== null}
                        onOpenChange={(open) => !open && setRemoving(null)}
                        title={`Remove ${removalName}?`}
                        description={
                            removing?.kind === 'building'
                                ? `Its docks stay and become ungrouped. Appointments keep their docks.`
                                : `This dock has never held an appointment or a check-in, so nothing else changes.`
                        }
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

LocationDocksPage.layout = [DashboardLayout, LocationLayout];
