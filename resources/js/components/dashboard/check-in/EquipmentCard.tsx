import type { LucideIcon } from 'lucide-react';
import { Container, Truck } from 'lucide-react';
import type { ReactNode } from 'react';

import Card from '@/components/dashboard/Card';
import PlateChip from '@/components/dashboard/check-in/PlateChip';
import { DetailHint, DetailRow } from '@/components/dashboard/DetailCard';
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

/** A plate row whose state and country each follow the location's config. */
function PlateRow({
    plate,
    plateState,
    plateCountry,
    stateField,
    countryField,
}: {
    plate: string;
    plateState: string;
    plateCountry: string;
    stateField: FieldState;
    countryField: FieldState;
}) {
    return (
        <DetailRow
            label="Plate"
            hint={hintFor(combinedFieldState([stateField, countryField]))}
        >
            <PlateChip
                plate={plate}
                state={stateField === 'hidden' ? '' : plateState}
                country={countryField === 'hidden' ? '' : plateCountry}
            />
        </DetailRow>
    );
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

    const trailerStateField = fieldState(
        fields.showTrailerPlateState,
        trailer.plateState,
    );
    const trailerCountryField = fieldState(
        fields.showTrailerPlateCountry,
        trailer.plateCountry,
    );
    const trailerHint = hintFor(
        combinedFieldState([trailerStateField, trailerCountryField]),
    );

    return (
        <Card title="Truck & Trailer">
            <div className="grid grid-cols-1 divide-y divide-gray-100 md:grid-cols-2 md:divide-x md:divide-y-0 dark:divide-gray-700/60">
                <EquipmentHalf icon={Truck} label="Truck" identity={truck.name}>
                    <PlateRow
                        plate={truck.plate}
                        plateState={truck.plateState}
                        plateCountry={truck.plateCountry}
                        stateField={fieldState(
                            fields.showTruckPlateState,
                            truck.plateState,
                        )}
                        countryField={fieldState(
                            fields.showTruckPlateCountry,
                            truck.plateCountry,
                        )}
                    />
                    {color !== 'hidden' && (
                        <DetailRow label="Color" hint={hintFor(color)}>
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
                        </DetailRow>
                    )}
                    {weight !== 'hidden' && (
                        <DetailRow label="Empty weight" hint={hintFor(weight)}>
                            {checkIn.emptyWeightLbs !== null
                                ? `${checkIn.emptyWeightLbs.toLocaleString()} lbs`
                                : null}
                        </DetailRow>
                    )}
                </EquipmentHalf>

                <EquipmentHalf
                    icon={Container}
                    label="Trailer"
                    identity={
                        <span className="inline-flex items-center gap-1.5">
                            <PlateChip
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
                            />
                            {trailerHint !== undefined && (
                                <DetailHint hint={trailerHint} />
                            )}
                        </span>
                    }
                >
                    <DetailRow label="Chute">
                        {trailer.chute === 'n/a'
                            ? 'N/A'
                            : formatLabel(trailer.chute)}
                    </DetailRow>
                </EquipmentHalf>
            </div>
        </Card>
    );
}

/** One side of the card: an icon tile over its identifying value, then its detail rows. */
function EquipmentHalf({
    icon: Icon,
    label,
    identity,
    children,
}: {
    icon: LucideIcon;
    label: string;
    identity: ReactNode;
    children: ReactNode;
}) {
    return (
        <section>
            <div className="flex items-center gap-3 px-5 pt-5 pb-2">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
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
            <dl className="divide-y divide-gray-100 px-5 pb-2 text-sm dark:divide-gray-700/60">
                {children}
            </dl>
        </section>
    );
}
