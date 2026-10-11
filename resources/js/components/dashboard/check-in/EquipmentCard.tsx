import type { LucideIcon } from 'lucide-react';
import { Container, Truck } from 'lucide-react';
import type { ReactNode } from 'react';

import Card from '@/components/dashboard/Card';
import PlateChip from '@/components/dashboard/check-in/PlateChip';
import { DetailRow } from '@/components/dashboard/DetailCard';
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

    return (
        <Card title="Truck & Trailer">
            <div className="grid grid-cols-1 divide-y divide-gray-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 dark:divide-gray-700/60">
                <EquipmentHalf icon={Truck} title="Truck">
                    <DetailRow label="Truck name">{truck.name}</DetailRow>
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
                    {weight !== 'hidden' && (
                        <DetailRow label="Empty weight" hint={hintFor(weight)}>
                            {checkIn.emptyWeightLbs !== null
                                ? `${checkIn.emptyWeightLbs.toLocaleString()} lbs`
                                : null}
                        </DetailRow>
                    )}
                </EquipmentHalf>

                <EquipmentHalf icon={Container} title="Trailer">
                    <PlateRow
                        plate={trailer.plate}
                        plateState={trailer.plateState}
                        plateCountry={trailer.plateCountry}
                        stateField={fieldState(
                            fields.showTrailerPlateState,
                            trailer.plateState,
                        )}
                        countryField={fieldState(
                            fields.showTrailerPlateCountry,
                            trailer.plateCountry,
                        )}
                    />
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

function EquipmentHalf({
    icon: Icon,
    title,
    children,
}: {
    icon: LucideIcon;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="px-5 py-4">
            <h3 className="mb-1 flex items-center gap-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                <Icon className="size-4" />
                {title}
            </h3>
            <dl className="divide-y divide-gray-100 text-sm dark:divide-gray-700/60">
                {children}
            </dl>
        </section>
    );
}
