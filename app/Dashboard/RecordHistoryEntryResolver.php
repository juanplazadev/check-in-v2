<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\CheckInFormField;
use App\Enums\RecordHistoryEvent;
use App\Enums\ScheduleFeature;
use App\Models\RecordHistory;
use App\Phone\PhoneFormatter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Shapes one history row into the RecordHistoryEntry contract in
 * resources/js/components/dashboard/types.ts, for every record the dashboard
 * shows a timeline for.
 */
final readonly class RecordHistoryEntryResolver
{
    /**
     * What a staff edit can change, as the timeline labels it: a check-in's
     * fields, then a location's, with its address fields prefixed `address.`
     * and its config settings by config path.
     * A location's check-in form flags are labelled by {@see CheckInFormField}.
     *
     * @var array<string, string>
     */
    private const array EDITED_FIELD_LABELS = [
        'drivers_name' => 'Driver name',
        'drivers_cellphone' => 'Driver cellphone',
        'drivers_email' => 'Driver email',
        'drivers_license_number' => 'License number',
        'drivers_license_state' => 'License state',
        'drivers_license_country' => 'License country',
        'drivers_license_expiration_date' => 'License expiration',
        'customer' => 'Customer',
        'destination_city' => 'Destination city',
        'destination_state' => 'Destination state',
        'destination_country' => 'Destination country',
        'po_numbers' => 'Purchase orders',
        'loading_instructions' => 'Loading instructions',
        'truck_name' => 'Truck name',
        'truck_plate' => 'Truck plate',
        'truck_plate_state' => 'Truck plate state',
        'truck_plate_country' => 'Truck plate country',
        'truck_color' => 'Truck color',
        'trailer_plate' => 'Trailer plate',
        'trailer_plate_state' => 'Trailer plate state',
        'trailer_plate_country' => 'Trailer plate country',
        'trailer_chute' => 'Trailer chute',
        'empty_weight_lbs' => 'Empty weight (lbs)',
        'name' => 'Name',
        'abbreviation' => 'Abbreviation',
        'timezone' => 'Timezone',
        'phone' => 'Phone',
        'phone_ext' => 'Phone ext.',
        'email' => 'Email',
        'max_distance_allowed' => 'Check-in radius (mi)',
        'is_active' => 'Status',
        'is_checkins_enabled' => 'Check-ins',
        'is_appointments_enabled' => 'Appointments',
        'address.street1' => 'Street',
        'address.street2' => 'Street line 2',
        'address.city' => 'City',
        'address.state' => 'State',
        'address.zip_code' => 'ZIP code',
        'address.zip_code_4' => 'ZIP+4',
        'address.country' => 'Country',
        'checkin.email_addresses' => 'Notification emails',
        'appointment.max_booking_days_ahead' => 'Book up to (days ahead)',
        'appointment.slot_interval_minutes' => 'Slot interval (min)',
        'appointment.min_lead_time_minutes' => 'Minimum lead time (min)',
        'appointment.buffer_before_close_minutes' => 'Buffer before close (min)',
        'appointment.email_addresses' => 'Notification emails',
    ];

    public function __construct(private PhoneFormatter $phone) {}

    /**
     * @param  string  $createdLabel  How the record came to be, e.g. "Check-in submitted".
     * @return array{id: int, event: string, description: string, changes: list<array{field: string, label: string, from: string|null, to: string|null, redacted: bool}>|null, actor: string|null, createdAt: string}
     */
    public function entry(RecordHistory $entry, string $createdLabel): array
    {
        return [
            'id' => $entry->id,
            'event' => $entry->event->value,
            'description' => $this->describe($entry, $createdLabel),
            'changes' => in_array($entry->event, [RecordHistoryEvent::DetailsEdited, RecordHistoryEvent::CheckInHoursEdited, RecordHistoryEvent::AppointmentHoursEdited, RecordHistoryEvent::CheckInOptionsEdited, RecordHistoryEvent::AppointmentOptionsEdited], true) ? $this->changes($entry) : null,
            'actor' => $entry->user?->name,
            'createdAt' => $entry->created_at->toIso8601String(),
        ];
    }

    private function describe(RecordHistory $entry, string $createdLabel): string
    {
        $context = $entry->context ?? [];
        $subject = $this->subject((string) $entry->subject);

        return match ($entry->event) {
            RecordHistoryEvent::Created => ($context['guest'] ?? false)
                ? $createdLabel.' by a guest'
                : $createdLabel,
            RecordHistoryEvent::NotificationSent => sprintf('%s sent %s', $subject, $this->destination($entry)),
            RecordHistoryEvent::NotificationFailed => sprintf('%s failed %s', $subject, $this->destination($entry)),
            RecordHistoryEvent::NotificationSkipped => sprintf(
                "%s not sent by %s: no cellphone on the driver's profile",
                $subject,
                $this->channelLabel($entry->channel),
            ),
            RecordHistoryEvent::EmployeeNotificationQueued => 'Staff notified',
            RecordHistoryEvent::StatusChanged => sprintf(
                '%s changed from %s to %s',
                $entry->subject === 'erp_status' ? 'ERP status' : 'Status',
                $this->statusLabel($context['from'] ?? null),
                $this->statusLabel($context['to'] ?? null),
            ),
            RecordHistoryEvent::Claimed => "Linked to the driver's account",
            RecordHistoryEvent::AccountDeleted => 'Driver deleted their account',
            RecordHistoryEvent::NoteAdded => 'Note added',
            RecordHistoryEvent::LicenseViewed => "Driver's license viewed",
            RecordHistoryEvent::DetailsEdited => 'Details edited',
            RecordHistoryEvent::Reinstated => isset($context['cancelled_reason'])
                ? sprintf('Reinstated (was canceled: %s)', $context['cancelled_reason'])
                : 'Reinstated',
            RecordHistoryEvent::Archived => isset($context['reason'])
                ? sprintf('Archived (reason: %s)', $context['reason'])
                : 'Archived',
            RecordHistoryEvent::Restored => 'Restored from archive',
            RecordHistoryEvent::CheckInDeleted => sprintf(
                'Check-in %s permanently deleted (reason: %s)',
                $context['reference_number'] ?? '',
                $context['reason'] ?? '',
            ),
            RecordHistoryEvent::AppointmentDeleted => sprintf(
                'Appointment %s permanently deleted (reason: %s)',
                $context['reference_number'] ?? '',
                $context['reason'] ?? '',
            ),
            RecordHistoryEvent::AppointmentLinked => sprintf(
                $entry->recordable_type === 'appointment' ? 'Linked to check-in %s' : 'Linked to appointment %s',
                $context['reference_number'] ?? '',
            ),
            RecordHistoryEvent::AppointmentUnlinked => sprintf(
                $entry->recordable_type === 'appointment' ? 'Unlinked from check-in %s' : 'Unlinked from appointment %s',
                $context['reference_number'] ?? '',
            ),
            RecordHistoryEvent::PdfEmailed => isset($context['status'])
                ? sprintf('PDF emailed to %s (status: %s)', $context['recipient'] ?? 'the driver', $this->statusLabel($context['status']))
                : sprintf('PDF emailed to %s', $context['recipient'] ?? 'the driver'),
            RecordHistoryEvent::LocationUserAttached => sprintf('%s added to this location', $context['name'] ?? 'A user'),
            RecordHistoryEvent::LocationUserDetached => sprintf('%s removed from this location', $context['name'] ?? 'A user'),
            RecordHistoryEvent::CheckInsPaused => sprintf(
                'Check-ins paused %s (reason: %s)',
                isset($context['until']) ? 'until '.Date::parse($context['until'])->format('g:i A') : 'until resumed',
                $context['reason'] ?? '',
            ),
            RecordHistoryEvent::CheckInsResumed => 'Check-ins resumed',
            RecordHistoryEvent::AppointmentsPaused => sprintf(
                'Appointments paused %s (reason: %s)',
                isset($context['until']) ? 'until '.Date::parse($context['until'])->format('M j, g:i A') : 'until resumed',
                $context['reason'] ?? '',
            ),
            RecordHistoryEvent::AppointmentsResumed => 'Appointments resumed',
            RecordHistoryEvent::LocationDeleted => sprintf('Location deleted (reason: %s)', $context['reason'] ?? ''),
            RecordHistoryEvent::LocationRestored => 'Location restored',
            RecordHistoryEvent::CheckInHoursEdited => 'Check-in hours edited',
            RecordHistoryEvent::AppointmentHoursEdited => 'Appointment hours edited',
            RecordHistoryEvent::CheckInOptionsEdited => 'Check-in form & notifications edited',
            RecordHistoryEvent::AppointmentOptionsEdited => 'Appointment booking & notifications edited',
            RecordHistoryEvent::ClosureAdded => sprintf(
                '%s added for %s: %s (%s)',
                ($context['hours'] ?? 'Closed') === 'Closed' ? 'Closure' : 'Special hours '.$context['hours'],
                $this->closureDate($context),
                $context['reason'] ?? '',
                $this->closureFeatures($context),
            ),
            RecordHistoryEvent::ClosureRemoved => sprintf(
                'Closure removed for %s: %s (%s)',
                $this->closureDate($context),
                $context['reason'] ?? '',
                $this->closureFeatures($context),
            ),
        };
    }

    /**
     * "Fri, Dec 25, 2026" from a closure entry's Y-m-d.
     *
     * @param  array<string, mixed>  $context
     */
    private function closureDate(array $context): string
    {
        return isset($context['date']) ? Date::parse((string) $context['date'])->format('D, M j, Y') : '';
    }

    /**
     * "check-ins, appointments" from a closure entry's appliesTo.
     *
     * @param  array<string, mixed>  $context
     */
    private function closureFeatures(array $context): string
    {
        return implode(', ', array_map(
            fn (mixed $key): string => mb_strtolower(ScheduleFeature::fromClosureKey((string) $key)->label()).'s',
            (array) ($context['appliesTo'] ?? []),
        ));
    }

    /**
     * A staff edit's {from, to} pairs as display rows. A redacted field (the
     * license number) shows that it changed and nothing else.
     *
     * @return list<array{field: string, label: string, from: string|null, to: string|null, redacted: bool}>
     */
    private function changes(RecordHistory $entry): array
    {
        $rows = [];

        foreach ($entry->context ?? [] as $field => $change) {
            if (! is_array($change)) {
                continue;
            }

            $redacted = (bool) ($change['redacted'] ?? false);

            $rows[] = [
                'field' => (string) $field,
                'label' => $this->changedFieldLabel((string) $field),
                'from' => $redacted ? null : $this->changedValue((string) $field, $change['from'] ?? null),
                'to' => $redacted ? null : $this->changedValue((string) $field, $change['to'] ?? null),
                'redacted' => $redacted,
            ];
        }

        return $rows;
    }

    private function changedFieldLabel(string $field): string
    {
        if (str_starts_with($field, 'checkin.')) {
            $formField = CheckInFormField::tryFrom(mb_substr($field, mb_strlen('checkin.')));

            if ($formField instanceof CheckInFormField) {
                return $formField->label();
            }
        }

        return self::EDITED_FIELD_LABELS[$field] ?? $this->subject($field);
    }

    private function changedValue(string $field, mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map(strval(...), $value));
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return match ($field) {
                'is_active' => $value ? 'Active' : 'Inactive',
                default => str_starts_with($field, 'checkin.show_')
                    ? ($value ? 'Shown' : 'Hidden')
                    : ($value ? 'On' : 'Off'),
            };
        }

        if ($field === 'drivers_cellphone') {
            return (string) $this->phone->format((string) $value);
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * "check-in-copy" -> "Check-in copy". Only the first hyphen of "check-in"
     * is a real one, so it is put back after the slug is spaced out.
     */
    private function subject(string $slug): string
    {
        return Str::of($slug)
            ->replace(['-', '_'], ' ')
            ->replace('check in', 'check-in')
            ->ucfirst()
            ->toString();
    }

    /**
     * "by SMS to +1 (555) 123-4567". Rows written before the recipient was
     * captured have none, and fall back to the channel alone.
     */
    private function destination(RecordHistory $entry): string
    {
        $channel = $this->channelLabel($entry->channel);
        $recipient = $entry->context['recipient'] ?? null;

        if (! is_string($recipient) || $recipient === '') {
            return 'by '.$channel;
        }

        if ($entry->channel === 'sms') {
            $recipient = (string) $this->phone->format($recipient);
        }

        return sprintf('by %s to %s', $channel, $recipient);
    }

    /**
     * A status value as the timeline spells it. The stored value is
     * "cancelled", but the UI uses the one-l spelling "canceled".
     */
    private function statusLabel(mixed $status): string
    {
        if (! is_string($status) || $status === '') {
            return 'unknown';
        }

        return $status === 'cancelled' ? 'canceled' : $status;
    }

    private function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'sms' => 'SMS',
            'mail' => 'email',
            default => (string) $channel,
        };
    }
}
