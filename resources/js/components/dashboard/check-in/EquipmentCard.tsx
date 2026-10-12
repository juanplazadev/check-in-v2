import type { LucideIcon } from 'lucide-react';
import { Container, Truck } from 'lucide-react';
import { Children } from 'react';
import type { ReactNode } from 'react';

import Card from '@/components/dashboard/Card';
import LicensePlate from '@/components/dashboard/check-in/LicensePlate';
import { DetailHint } from '@/components/dashboard/DetailCard';
import type {
    CheckInFormFields,
    CheckInRecord,
} from '@/components/dashboard/types';
import type { FieldState } from '@/lib/dashboard/checkInFields';
import {
    combinedFieldState,
    fieldState,
    LEGACY_FIELD_HINT,
} from '@/lib/dashboard/checkInFields';
import { formatLabel } from '@/lib/dashboard/format';

/** Swatches for the slugs in config('app.truck_colors'); unknown slugs get a neutral dot. */
const TRUCK_COLOR_SWATCHES: Record<string, string> = {
    white: '#ffffff',
    black: '#111827',
    gray: '#9ca3af',
    red: '#dc2626',
    orange: '#f97316',
    yellow: '#facc15',
    green: '#16a34a',
    blue: '#2563eb',
    purple: '#9333ea',
    brown: '#92400e',
    beige: '#e7d8b5',
    maroon: '#7f1d1d',
    gold: '#d4a017',
    multicolor:
        'conic-gradient(#dc2626, #facc15, #16a34a, #2563eb, #9333ea, #dc2626)',
};

function hintFor(state: FieldState): string | undefined {
    return state === 'legacy' ? LEGACY_FIELD_HINT : undefined;
}

export default function EquipmentCard({
    checkIn,
    fields,
}: {
    checkIn: CheckInRecord;
    fields: CheckInFormFields;
}) {
    const { truck, trailer } = checkIn;

    const color = fieldState(fields.showTruckColor, truck.color);
    const weight = fieldState(
        fields.showEmptyWeightLbs,
        checkIn.emptyWeightLbs,
    );

    const truckStateField = fieldState(
        fields.showTruckPlateState,
        truck.plateState,
    );
    const truckCountryField = fieldState(
        fields.showTruckPlateCountry,
        truck.plateCountry,
    );
    const trailerStateField = fieldState(
        fields.showTrailerPlateState,
        trailer.plateState,
    );
    const trailerCountryField = fieldState(
        fields.showTrailerPlateCountry,
        trailer.plateCountry,
    );

    const chute = trailer.chute === 'n/a' ? 'N/A' : formatLabel(trailer.chute);

    return (
        <Card title="Truck & Trailer">
            <div className="grid gap-4 p-5 md:grid-cols-2">
                <UnitTile
                    icon={Truck}
                    label="Truck"
                    identity={truck.name}
                    plate={
                        <LicensePlate
                            plate={truck.plate}
                            state={
                                truckStateField === 'hidden'
                                    ? ''
                                    : truck.plateState
                            }
                            country={
                                truckCountryField === 'hidden'
                                    ? ''
                                    : truck.plateCountry
                            }
                            hint={hintFor(
                                combinedFieldState([
                                    truckStateField,
                                    truckCountryField,
                                ]),
                            )}
                        />
                    }
                >
                    {color !== 'hidden' && (
                        <SpecCell label="Color" hint={hintFor(color)}>
                            {truck.color !== '' ? (
                                <span className="inline-flex items-center gap-2">
                                    <span
                                        className="size-3 rounded-full ring-1 ring-gray-300 dark:ring-gray-600"
                                        style={{
                                            background:
                                                TRUCK_COLOR_SWATCHES[
                                                    truck.color
                                                ] ?? 'transparent',
                                        }}
                                        aria-hidden="true"
                                    />
                                    {formatLabel(truck.color)}
                                </span>
                            ) : null}
                        </SpecCell>
                    )}
                    {weight !== 'hidden' && (
                        <SpecCell label="Empty weight" hint={hintFor(weight)}>
                            {checkIn.emptyWeightLbs !== null
                                ? `${checkIn.emptyWeightLbs.toLocaleString()} lbs`
                                : null}
                        </SpecCell>
                    )}
                </UnitTile>

                <UnitTile
                    icon={Container}
                    label="Trailer"
                    identity={chute}
                    plate={
                        <LicensePlate
                            plate={trailer.plate}
                            state={
                                trailerStateField === 'hidden'
                                    ? ''
                                    : trailer.plateState
                            }
                            country={
                                trailerCountryField === 'hidden'
                                    ? ''
                                    : trailer.plateCountry
                            }
                            hint={hintFor(
                                combinedFieldState([
                                    trailerStateField,
                                    trailerCountryField,
                                ]),
                            )}
                        />
                    }
                >
                    <SpecCell label="Chute">{chute}</SpecCell>
                </UnitTile>
            </div>
        </Card>
    );
}

/** One unit on a gray tile: an icon over its identifying value, its plate, then its specs. */
function UnitTile({
    icon: Icon,
    label,
    identity,
    plate,
    children,
}: {
    icon: LucideIcon;
    label: string;
    identity: ReactNode;
    plate: ReactNode;
    children: ReactNode;
}) {
    const hasSpecs = Children.toArray(children).length > 0;

    return (
        <section className="flex h-full flex-col rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40">
            <div className="flex items-center gap-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-gray-600 shadow-xs dark:bg-gray-800 dark:text-gray-300">
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <div className="text-xs text-gray-500 dark:text-gray-400">
                        {label}
                    </div>
                    <div className="truncate font-semibold text-gray-800 dark:text-gray-100">
                        {identity}
                    </div>
                </div>
            </div>

            <div className="flex justify-center py-5">{plate}</div>

            {hasSpecs && (
                <dl className="mt-auto grid grid-cols-2 gap-4 border-t border-gray-200/70 pt-4 dark:border-gray-700/60">
                    {children}
                </dl>
            )}
        </section>
    );
}

/** A label over its value, for the specs at the foot of a unit tile. */
function SpecCell({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="flex items-center gap-1.5 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                {label}
                {hint !== undefined && <DetailHint hint={hint} />}
            </dt>
            <dd className="mt-1 text-sm font-medium text-gray-800 dark:text-gray-100">
                {children ?? (
                    <span className="text-gray-400 dark:text-gray-500">-</span>
                )}
            </dd>
        </div>
    );
}
