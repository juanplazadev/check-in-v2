import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { CalendarPlus } from 'lucide-react';
import { useState } from 'react';

import LocationClosureController from '@/actions/App/Http/Controllers/Dashboard/LocationClosureController';
import AddClosureDialog from '@/components/dashboard/location/AddClosureDialog';
import ClosureCalendar from '@/components/dashboard/location/ClosureCalendar';
import ClosureList, {
    CLOSURE_FEATURES,
} from '@/components/dashboard/location/ClosureList';
import SectionHeader, {
    SectionGroup,
} from '@/components/dashboard/location/SectionHeader';
import { StatusConfirmDialog } from '@/components/dashboard/RecordActionDialogs';
import type {
    DashboardLocationSummary,
    LocationClosure,
    LocationClosures,
} from '@/components/dashboard/types';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';

interface LocationClosuresProps {
    location: DashboardLocationSummary;
    closures: LocationClosures;
}

/** Past closures listed under Recent; the calendar shows the whole past year. */
const RECENT_LIMIT = 10;

export default function LocationClosuresPage({
    location,
    closures,
}: LocationClosuresProps) {
    const can = useCan();
    const canEdit = can('edit location hours');
    const [adding, setAdding] = useState(false);
    const [addDate, setAddDate] = useState<string | null>(null);
    const [addReason, setAddReason] = useState<string | null>(null);
    const [removing, setRemoving] = useState<LocationClosure | null>(null);
    const [removeBusy, setRemoveBusy] = useState(false);

    const openAdd = (date: string | null, holidayName: string | null) => {
        setAddDate(date);
        setAddReason(holidayName);
        setAdding(true);
    };

    const remove = () => {
        if (removing === null) {
            return;
        }

        router.delete(
            LocationClosureController.destroy.url({
                uuid: location.uuid,
                date: removing.date,
            }),
            {
                data: { applies_to: removing.appliesTo },
                preserveScroll: true,
                onStart: () => setRemoveBusy(true),
                onSuccess: () => setRemoving(null),
                onFinish: () => setRemoveBusy(false),
            },
        );
    };

    return (
        <>
            <Head title={`${location.name} closures & holidays`} />

            <SectionHeader
                title="Closures & holidays"
                description="Dates when the location is closed or keeps special hours. These override the weekly check-in and appointment hours."
                action={
                    canEdit && (
                        <button
                            type="button"
                            onClick={() => openAdd(null, null)}
                            className="btn gap-2 bg-gray-900 text-gray-100 hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                        >
                            <CalendarPlus className="size-4" aria-hidden />
                            Add closure
                        </button>
                    )
                }
            />

            <SectionGroup title="Calendar">
                <ClosureCalendar
                    closures={[...closures.upcoming, ...closures.past]}
                    holidays={closures.holidays}
                    today={closures.today}
                    onSelectDate={canEdit ? openAdd : undefined}
                />
            </SectionGroup>

            <SectionGroup title="Upcoming">
                <ClosureList
                    closures={closures.upcoming}
                    empty="No closures or special hours coming up."
                    onRemove={canEdit ? setRemoving : undefined}
                />
            </SectionGroup>

            {closures.past.length > 0 && (
                <SectionGroup title="Recent">
                    <div className="opacity-70">
                        <ClosureList
                            closures={closures.past.slice(0, RECENT_LIMIT)}
                            empty=""
                        />
                    </div>
                </SectionGroup>
            )}

            {canEdit && (
                <>
                    <AddClosureDialog
                        open={adding}
                        onOpenChange={setAdding}
                        locationUuid={location.uuid}
                        locationName={location.name}
                        today={closures.today}
                        initialDate={addDate}
                        initialReason={addReason}
                        closures={closures.upcoming}
                        bookedAppointments={closures.bookedAppointments}
                    />

                    <StatusConfirmDialog
                        open={removing !== null}
                        onOpenChange={(open) => {
                            if (!open && !removeBusy) {
                                setRemoving(null);
                            }
                        }}
                        title="Remove this closure?"
                        description={
                            removing === null
                                ? ''
                                : `${removing.reason} on ${format(parseISO(removing.date), 'EEEE, MMMM d')} will be removed from ${removing.appliesTo
                                      .map((feature) =>
                                          CLOSURE_FEATURES[
                                              feature
                                          ].label.toLowerCase(),
                                      )
                                      .join(
                                          ' and ',
                                      )}. That day will follow the weekly hours again.`
                        }
                        keepLabel="Keep it"
                        confirmLabel={removeBusy ? 'Removing…' : 'Remove'}
                        busy={removeBusy}
                        onConfirm={remove}
                    />
                </>
            )}
        </>
    );
}

LocationClosuresPage.layout = [DashboardLayout, LocationLayout];
