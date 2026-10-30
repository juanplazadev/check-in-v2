<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class AppointmentConfirmationDTO
{
    /**
     * @param  list<string>  $purchaseOrders
     */
    public function __construct(
        public string $uuid,
        public string $referenceNumber,
        public string $scheduledDate,
        /** Start to end, like "9:00 AM - 10:30 AM". */
        public string $scheduledTime,
        public ?string $loadType,
        public string $driversName,
        public string $locationName,
        public string $locationAddress,
        public array $purchaseOrders,
    ) {}
}
