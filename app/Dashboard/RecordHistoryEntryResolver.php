<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\RecordHistoryEvent;
use App\Models\RecordHistory;
use App\Phone\PhoneFormatter;
use Illuminate\Support\Str;

/**
 * Shapes one history row into the RecordHistoryEntry contract in
 * resources/js/components/dashboard/types.ts, for every record the dashboard
 * shows a timeline for.
 */
final readonly class RecordHistoryEntryResolver
{
    /**
     * What a staff edit can change, as the timeline labels it.
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
            'changes' => $entry->event === RecordHistoryEvent::DetailsEdited ? $this->changes($entry) : null,
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
        };
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
                'label' => self::EDITED_FIELD_LABELS[$field] ?? $this->subject((string) $field),
                'from' => $redacted ? null : $this->changedValue((string) $field, $change['from'] ?? null),
                'to' => $redacted ? null : $this->changedValue((string) $field, $change['to'] ?? null),
                'redacted' => $redacted,
            ];
        }

        return $rows;
    }

    private function changedValue(string $field, mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map(strval(...), $value));
        }

        if ($value === null || $value === '') {
            return null;
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
