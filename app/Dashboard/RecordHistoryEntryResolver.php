<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\RecordHistoryEvent;
use App\Models\RecordHistory;
use Illuminate\Support\Str;

/**
 * Shapes one history row into the RecordHistoryEntry contract in
 * resources/js/components/dashboard/types.ts, for every record the dashboard
 * shows a timeline for.
 */
final readonly class RecordHistoryEntryResolver
{
    /**
     * @param  string  $createdLabel  How the record came to be, e.g. "Check-in submitted".
     * @return array{id: int, event: string, description: string, actor: string|null, createdAt: string}
     */
    public function entry(RecordHistory $entry, string $createdLabel): array
    {
        return [
            'id' => $entry->id,
            'event' => $entry->event->value,
            'description' => $this->describe($entry, $createdLabel),
            'actor' => $entry->user?->name,
            'createdAt' => $entry->created_at->toIso8601String(),
        ];
    }

    private function describe(RecordHistory $entry, string $createdLabel): string
    {
        $context = $entry->context ?? [];
        $subject = Str::of((string) $entry->subject)->replace(['-', '_'], ' ')->ucfirst()->toString();

        return match ($entry->event) {
            RecordHistoryEvent::Created => ($context['guest'] ?? false)
                ? $createdLabel.' by a guest'
                : $createdLabel,
            RecordHistoryEvent::NotificationSent => sprintf('%s sent via %s', $subject, $entry->channel),
            RecordHistoryEvent::NotificationFailed => sprintf('%s failed via %s', $subject, $entry->channel),
            RecordHistoryEvent::EmployeeNotificationQueued => 'Staff notified',
            RecordHistoryEvent::StatusChanged => sprintf(
                '%s changed from %s to %s',
                $entry->subject === 'erp_status' ? 'ERP status' : 'Status',
                $context['from'] ?? 'unknown',
                $context['to'] ?? 'unknown',
            ),
            RecordHistoryEvent::Claimed => "Linked to the driver's account",
            RecordHistoryEvent::AccountDeleted => 'Driver deleted their account',
        };
    }
}
