import { router } from '@inertiajs/react';
import { useId, useState } from 'react';

import LocationBuildingController from '@/actions/App/Http/Controllers/Dashboard/LocationBuildingController';
import LocationDockController from '@/actions/App/Http/Controllers/Dashboard/LocationDockController';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type {
    LocationBuilding,
    LocationDock,
} from '@/components/dashboard/types';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';

const PRIMARY_BUTTON =
    'btn bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white';

type Errors = Partial<Record<string, string>>;

interface DockDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    locationUuid: string;
    buildings: LocationBuilding[];
    /** The dock being edited; absent when adding one. */
    dock?: LocationDock;
}

/** Adds a dock to a location, or edits one. */
export function DockDialog({ open, onOpenChange, ...body }: DockDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="dashboard">
                {/* Radix unmounts the content on close, so each opening starts from the dock. */}
                <DockBody {...body} onDone={() => onOpenChange(false)} />
            </AlertDialogContent>
        </AlertDialog>
    );
}

function DockBody({
    locationUuid,
    buildings,
    dock,
    onDone,
}: Omit<DockDialogProps, 'open' | 'onOpenChange'> & { onDone: () => void }) {
    const fieldId = useId();
    const [name, setName] = useState(dock?.name ?? '');
    const [building, setBuilding] = useState(dock?.buildingId ?? '');
    const [isActive, setIsActive] = useState(dock?.isActive ?? true);
    const [acceptsAppointments, setAcceptsAppointments] = useState(
        dock?.acceptsAppointments ?? true,
    );
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Errors>({});

    const save = () => {
        const route = dock
            ? LocationDockController.update({
                  uuid: locationUuid,
                  dockUuid: dock.id,
              })
            : LocationDockController.store(locationUuid);

        router.visit(route, {
            data: {
                name: name.trim(),
                building: building === '' ? null : building,
                is_active: isActive,
                accepts_appointments: acceptsAppointments,
            },
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setErrors({});
            },
            onSuccess: onDone,
            onError: setErrors,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {dock ? `Edit ${dock.name}` : 'Add a dock'}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    Each active dock that takes appointments lets one more truck
                    book every time slot.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <Field data-invalid={errors.name !== undefined || undefined}>
                <FieldLabel htmlFor={`${fieldId}-name`}>Name</FieldLabel>
                <Input
                    id={`${fieldId}-name`}
                    value={name}
                    maxLength={50}
                    placeholder="Door 12"
                    aria-invalid={errors.name !== undefined}
                    onChange={(event) => setName(event.target.value)}
                />
                {errors.name !== undefined && (
                    <FieldError>{errors.name}</FieldError>
                )}
            </Field>

            {buildings.length > 0 && (
                <Field
                    data-invalid={errors.building !== undefined || undefined}
                >
                    <FieldLabel htmlFor={`${fieldId}-building`}>
                        Building
                    </FieldLabel>
                    <select
                        id={`${fieldId}-building`}
                        className="form-select w-full"
                        value={building}
                        onChange={(event) => setBuilding(event.target.value)}
                    >
                        <option value="">No building</option>
                        {buildings.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.name}
                            </option>
                        ))}
                    </select>
                    {errors.building !== undefined && (
                        <FieldError>{errors.building}</FieldError>
                    )}
                </Field>
            )}

            <div className="space-y-3">
                <SwitchRow
                    id={`${fieldId}-active`}
                    label="Active"
                    hint="Turn off a door that is out of service."
                    checked={isActive}
                    onChange={setIsActive}
                />
                <SwitchRow
                    id={`${fieldId}-appointments`}
                    label="Takes appointments"
                    hint="Turn off to keep this door for walk-in check-ins only."
                    checked={acceptsAppointments}
                    onChange={setAcceptsAppointments}
                />
                {errors.is_active !== undefined && (
                    <p className="text-sm text-rose-500">{errors.is_active}</p>
                )}
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel asChild>
                    <button
                        type="button"
                        disabled={busy}
                        className={SECONDARY_BUTTON}
                    >
                        Cancel
                    </button>
                </AlertDialogCancel>
                <button
                    type="button"
                    disabled={busy || name.trim() === ''}
                    onClick={save}
                    className={PRIMARY_BUTTON}
                >
                    {busy ? 'Saving…' : dock ? 'Save' : 'Add dock'}
                </button>
            </AlertDialogFooter>
        </>
    );
}

export function SwitchRow({
    id,
    label,
    hint,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    hint: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-4">
            <label htmlFor={id} className="min-w-0">
                <span className="block text-sm font-medium text-gray-800 dark:text-gray-100">
                    {label}
                </span>
                <span className="block text-sm text-gray-500 dark:text-gray-400">
                    {hint}
                </span>
            </label>
            <Switch id={id} checked={checked} onCheckedChange={onChange} />
        </div>
    );
}

interface BuildingDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    locationUuid: string;
    /** The building being renamed; absent when adding one. */
    building?: LocationBuilding;
}

/** Adds a building to a location's campus, or renames one. */
export function BuildingDialog({
    open,
    onOpenChange,
    ...body
}: BuildingDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="dashboard">
                <BuildingBody {...body} onDone={() => onOpenChange(false)} />
            </AlertDialogContent>
        </AlertDialog>
    );
}

function BuildingBody({
    locationUuid,
    building,
    onDone,
}: Omit<BuildingDialogProps, 'open' | 'onOpenChange'> & {
    onDone: () => void;
}) {
    const fieldId = useId();
    const [name, setName] = useState(building?.name ?? '');
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Errors>({});

    const save = () => {
        const route = building
            ? LocationBuildingController.update({
                  uuid: locationUuid,
                  buildingUuid: building.id,
              })
            : LocationBuildingController.store(locationUuid);

        router.visit(route, {
            data: { name: name.trim() },
            preserveScroll: true,
            onStart: () => {
                setBusy(true);
                setErrors({});
            },
            onSuccess: onDone,
            onError: setErrors,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {building ? `Rename ${building.name}` : 'Add a building'}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    Buildings group a campus's docks, so staff can tell drivers
                    which building to pull up to.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <Field data-invalid={errors.name !== undefined || undefined}>
                <FieldLabel htmlFor={`${fieldId}-name`}>Name</FieldLabel>
                <Input
                    id={`${fieldId}-name`}
                    value={name}
                    maxLength={50}
                    placeholder="Building B"
                    aria-invalid={errors.name !== undefined}
                    onChange={(event) => setName(event.target.value)}
                />
                {errors.name !== undefined && (
                    <FieldError>{errors.name}</FieldError>
                )}
            </Field>

            <AlertDialogFooter>
                <AlertDialogCancel asChild>
                    <button
                        type="button"
                        disabled={busy}
                        className={SECONDARY_BUTTON}
                    >
                        Cancel
                    </button>
                </AlertDialogCancel>
                <button
                    type="button"
                    disabled={busy || name.trim() === ''}
                    onClick={save}
                    className={PRIMARY_BUTTON}
                >
                    {busy ? 'Saving…' : building ? 'Save' : 'Add building'}
                </button>
            </AlertDialogFooter>
        </>
    );
}
