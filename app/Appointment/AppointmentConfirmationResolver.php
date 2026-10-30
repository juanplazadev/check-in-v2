<?php

declare(strict_types=1);

namespace App\Appointment;

use App\Address\AddressManager;
use App\DTOs\AppointmentConfirmationDTO;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;

final readonly class AppointmentConfirmationResolver
{
    public function __construct(private AddressManager $address) {}

    /**
     * @return array{appointment: AppointmentConfirmationDTO, contact: array{phone: string, email: string}}
     */
    public function resolve(string $uuid): array
    {
        $appointment = Appointment::with(['location.address', 'purchaseOrders', 'loadType'])
            ->where('uuid', $uuid)
            ->where('status', AppointmentStatus::Scheduled)
            ->firstOrFail();

        $location = $appointment->location;
        // In the driver's language, so the month reads "noviembre" on a Spanish page.
        $scheduledFor = $appointment->scheduled_for->setTimezone($location->timezone)->settings(['locale' => app()->getLocale()]);
        $endsAt = $appointment->ends_at->setTimezone($location->timezone);

        /** @var list<string> $purchaseOrders */
        $purchaseOrders = $appointment->purchaseOrders->pluck('number')->all();

        return [
            'appointment' => new AppointmentConfirmationDTO(
                uuid: $appointment->uuid,
                referenceNumber: $appointment->reference_number,
                scheduledDate: $scheduledFor->translatedFormat('F j, Y'),
                scheduledTime: sprintf('%s - %s', $scheduledFor->format('g:i A'), $endsAt->format('g:i A')),
                loadType: $appointment->loadType?->name,
                driversName: $appointment->drivers_name,
                locationName: $location->name,
                locationAddress: $this->address->buildAddress($location->address->toArray()),
                purchaseOrders: $purchaseOrders,
            ),
            'contact' => [
                'phone' => $location->phone,
                'email' => $location->email,
            ],
        ];
    }
}
