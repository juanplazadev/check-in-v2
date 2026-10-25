import { Head } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import { ToggleGroup } from 'radix-ui';
import { useState } from 'react';

import { update } from '@/actions/App/Http/Controllers/Dashboard/LocationAppointmentOptionsController';
import EditableSection from '@/components/dashboard/location/EditableSection';
import EmailAddressInputs from '@/components/dashboard/location/EmailAddressInputs';
import {
    NotificationEmails,
    OptionRow,
    OptionRows,
} from '@/components/dashboard/location/OptionRows';
import SectionHeader from '@/components/dashboard/location/SectionHeader';
import type {
    DashboardLocationSummary,
    LocationAppointmentOptions,
} from '@/components/dashboard/types';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldTitle,
} from '@/components/ui/field';
import { Slider } from '@/components/ui/slider';
import { useCan } from '@/hooks/useCan';
import DashboardLayout from '@/layouts/DashboardLayout';
import LocationLayout from '@/layouts/LocationLayout';

interface LocationAppointmentOptionsProps {
    location: DashboardLocationSummary;
    options: LocationAppointmentOptions;
}

type Section = 'booking' | 'emails';

/** The config keys App\Enums\AppointmentBookingSetting names, as the update endpoint takes them. */
interface BookingData {
    max_booking_days_ahead: number;
    slot_interval_minutes: number;
    min_lead_time_minutes: number;
    buffer_before_close_minutes: number;
}

/** 0 -> "0 min", 45 -> "45 min", 60 -> "1 h", 90 -> "1 h 30 min". */
function minutes(value: number): string {
    const hours = Math.floor(value / 60);
    const rest = value % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

function days(value: number): string {
    return value === 1 ? '1 day' : `${value} days`;
}

/** A labeled slider bound to one booking setting, with its value shown live beside the label. */
function SliderField({
    form,
    name,
    label,
    description,
    min,
    max,
    step,
    format,
    formatEnd = format,
}: {
    form: InertiaFormProps<BookingData>;
    name: keyof BookingData;
    label: string;
    description: string;
    min: number;
    max: number;
    step: number;
    format: (value: number) => string;
    /** How the range's two ends are labeled under the track; defaults to format. */
    formatEnd?: (value: number) => string;
}) {
    const error = form.errors[name];
    const value = form.data[name];
    const labelId = `appointment_${name}_label`;

    return (
        <Field data-invalid={!!error || undefined}>
            <div className="flex items-baseline justify-between gap-4">
                <FieldTitle id={labelId}>{label}</FieldTitle>
                <span className="text-sm font-semibold text-brand-green tabular-nums">
                    {format(value)}
                </span>
            </div>
            <Slider
                value={[value]}
                min={min}
                max={max}
                step={step}
                onValueChange={([next]) => form.setData(name, next)}
                aria-labelledby={labelId}
                aria-valuetext={format(value)}
                aria-invalid={!!error || undefined}
            />
            <div
                aria-hidden
                className="flex justify-between text-xs text-gray-400 tabular-nums dark:text-gray-500"
            >
                <span>{formatEnd(min)}</span>
                <span>{formatEnd(max)}</span>
            </div>
            <FieldDescription>{description}</FieldDescription>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

/** The allowed slot intervals as one segmented control; they are too uneven for a slider. */
function IntervalField({
    form,
    intervals,
}: {
    form: InertiaFormProps<BookingData>;
    intervals: number[];
}) {
    const error = form.errors.slot_interval_minutes;

    return (
        <Field data-invalid={!!error || undefined}>
            <FieldTitle id="appointment_slot_interval_minutes_label">
                Slot interval
            </FieldTitle>
            <ToggleGroup.Root
                type="single"
                value={String(form.data.slot_interval_minutes)}
                onValueChange={(next) => {
                    // Clicking the chosen interval again would clear it; one is always set.
                    if (next !== '') {
                        form.setData('slot_interval_minutes', Number(next));
                    }
                }}
                aria-labelledby="appointment_slot_interval_minutes_label"
                className="inline-flex w-full rounded-lg bg-gray-100 p-1 dark:bg-gray-900/40"
            >
                {intervals.map((interval) => (
                    <ToggleGroup.Item
                        key={interval}
                        value={String(interval)}
                        className="flex-1 cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-gray-500 tabular-nums outline-none hover:text-gray-700 focus-visible:ring-2 focus-visible:ring-brand-green/40 data-[state=on]:bg-white data-[state=on]:text-brand-green data-[state=on]:shadow-xs dark:text-gray-400 dark:hover:text-gray-200 dark:data-[state=on]:bg-gray-700"
                    >
                        {minutes(interval)}
                    </ToggleGroup.Item>
                ))}
            </ToggleGroup.Root>
            <FieldDescription>
                The gap between bookable start times.
            </FieldDescription>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

function BookingFields({
    form,
    limits,
}: {
    form: InertiaFormProps<BookingData>;
    limits: LocationAppointmentOptions['limits'];
}) {
    return (
        <>
            <div className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                <SliderField
                    form={form}
                    name="max_booking_days_ahead"
                    label="Book up to"
                    description="How many days ahead a driver can book."
                    min={1}
                    max={limits.maxDaysAhead}
                    step={1}
                    format={(value) => `${days(value)} ahead`}
                    formatEnd={days}
                />
                <IntervalField
                    form={form}
                    intervals={limits.slotIntervalMinutes}
                />
                <SliderField
                    form={form}
                    name="min_lead_time_minutes"
                    label="Minimum lead time"
                    description="How soon from now the earliest slot can be."
                    min={0}
                    max={limits.maxLeadTimeMinutes}
                    step={limits.minuteStep}
                    format={minutes}
                />
                <SliderField
                    form={form}
                    name="buffer_before_close_minutes"
                    label="Buffer before close"
                    description="No slots start this close to closing time."
                    min={0}
                    max={limits.maxBufferBeforeCloseMinutes}
                    step={limits.minuteStep}
                    format={minutes}
                />
            </div>
            <p className="text-xs text-gray-500 dark:text-gray-400">
                Changes apply to new bookings; booked appointments keep their
                time.
            </p>
        </>
    );
}

export default function LocationAppointmentOptionsPage({
    location,
    options,
}: LocationAppointmentOptionsProps) {
    const can = useCan();
    const [editing, setEditing] = useState<Section | null>(null);
    const { booking } = options;

    const bookingData: BookingData = {
        max_booking_days_ahead: booking.maxDaysAhead,
        slot_interval_minutes: booking.slotIntervalMinutes,
        min_lead_time_minutes: booking.minLeadTimeMinutes,
        buffer_before_close_minutes: booking.bufferBeforeCloseMinutes,
    };

    const sectionProps = (section: Section) => ({
        action: update(location.uuid),
        canEdit: can('edit appointment options'),
        editing: editing === section,
        locked: editing !== null && editing !== section,
        onEditingChange: (next: boolean) => setEditing(next ? section : null),
    });

    return (
        <>
            <Head title={`${location.name} appointment options`} />

            <SectionHeader
                title="Booking & notifications"
                description="How far ahead and how close to closing drivers can book, and who hears about it."
            />

            <EditableSection
                title="Booking rules"
                {...sectionProps('booking')}
                initialData={bookingData}
                view={
                    <OptionRows>
                        <OptionRow
                            label="Book up to"
                            description="How many days ahead a driver can book."
                        >
                            {days(booking.maxDaysAhead)} ahead
                        </OptionRow>
                        <OptionRow
                            label="Slot interval"
                            description="The gap between bookable start times."
                        >
                            {minutes(booking.slotIntervalMinutes)}
                        </OptionRow>
                        <OptionRow
                            label="Minimum lead time"
                            description="How soon from now the earliest slot can be."
                        >
                            {minutes(booking.minLeadTimeMinutes)}
                        </OptionRow>
                        <OptionRow
                            label="Buffer before close"
                            description="No slots start this close to closing time."
                        >
                            {minutes(booking.bufferBeforeCloseMinutes)}
                        </OptionRow>
                    </OptionRows>
                }
                form={(form) => (
                    <BookingFields form={form} limits={options.limits} />
                )}
            />

            <EditableSection
                title="Notify on booking"
                {...sectionProps('emails')}
                initialData={{ email_addresses: options.notificationEmails }}
                view={
                    <NotificationEmails
                        emails={options.notificationEmails}
                        empty="Nobody is emailed when an appointment is booked here."
                    />
                }
                form={(form) => (
                    <EmailAddressInputs
                        form={form}
                        empty="Nobody will be emailed when an appointment is booked or canceled here."
                    />
                )}
            />
        </>
    );
}

LocationAppointmentOptionsPage.layout = [DashboardLayout, LocationLayout];
