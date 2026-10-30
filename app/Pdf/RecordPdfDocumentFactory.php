<?php

declare(strict_types=1);

namespace App\Pdf;

use App\Account\HistoryRecordResolver;
use App\Barcode\Code39;
use App\Models\Appointment;
use App\Models\CheckIn;
use Illuminate\Support\Facades\Date;

final readonly class RecordPdfDocumentFactory
{
    public function __construct(
        private HistoryRecordResolver $resolver,
        private Code39 $barcode,
    ) {}

    public function forCheckIn(CheckIn $checkIn): RecordPdfDocument
    {
        $payload = $this->resolver->checkIn($checkIn);

        return new RecordPdfDocument(
            view: 'pdf.check-in',
            data: [
                'checkIn' => $payload,
                ...$this->chrome($payload['referenceNumber'], $payload['status']),
            ],
            fileName: $this->fileName('check-in', $payload),
            cacheKey: $this->cacheKey('check-ins', $checkIn->uuid, $checkIn->updated_at->getTimestamp()),
        );
    }

    public function forAppointment(Appointment $appointment): RecordPdfDocument
    {
        $payload = $this->resolver->appointment($appointment);

        return new RecordPdfDocument(
            view: 'pdf.appointment',
            data: [
                'appointment' => $payload,
                ...$this->chrome($payload['referenceNumber'], $payload['status']),
            ],
            fileName: $this->fileName('appointment', $payload),
            cacheKey: $this->cacheKey('appointments', $appointment->uuid, $appointment->updated_at->getTimestamp()),
        );
    }

    /**
     * The bits the shared layout needs on top of the record payload.
     *
     * @return array<string, string>
     */
    private function chrome(string $reference, string $status): array
    {
        return [
            'barcode' => $this->barcode->svg($reference),
            'statusLabel' => $this->statusLabel($status),
            'generatedOn' => Date::now()->settings(['locale' => app()->getLocale()])->translatedFormat('M j, Y'),
        ];
    }

    /**
     * Literal translation keys rather than a concatenated one, so the type
     * resolves to string instead of the array|string|null __() can return.
     * Covers both the CheckInStatus and AppointmentStatus value sets.
     */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => __('messages.accountHistoryRecord.statusPending'),
            'completed' => __('messages.accountHistoryRecord.statusCompleted'),
            'cancelled' => __('messages.accountHistoryRecord.statusCancelled'),
            'scheduled' => __('messages.accountHistoryRecord.statusScheduled'),
            'no-show' => __('messages.accountHistoryRecord.statusNoShow'),
            'checked-in' => __('messages.accountHistoryRecord.statusCheckedIn'),
            default => $status,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fileName(string $type, array $payload): string
    {
        return sprintf(
            '%s-%s-%s.pdf',
            $type,
            mb_strtolower((string) $payload['locationAbbreviation']),
            $payload['referenceNumber'],
        );
    }

    /**
     * updated_at is part of the key, so an edited record renders afresh and a
     * cached copy can never go stale.
     */
    private function cacheKey(string $type, string $uuid, int $updatedAt): string
    {
        return sprintf('pdfs/%s/%s-%d.pdf', $type, $uuid, $updatedAt);
    }
}
