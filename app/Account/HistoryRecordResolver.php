<?php

declare(strict_types=1);

namespace App\Account;

use App\Address\AddressManager;
use App\Enums\TrailerChute;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Phone\PhoneFormatter;
use Illuminate\Support\Facades\Date;

final readonly class HistoryRecordResolver
{
    public function __construct(
        private AddressManager $address,
        private PhoneFormatter $phone,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function checkIn(CheckIn $checkIn): array
    {
        $location = $checkIn->location;
        // In the current locale, so a driver's email and PDF name the month in their language.
        $when = $checkIn->created_at->setTimezone($location->timezone)->settings(['locale' => app()->getLocale()]);

        /** @var list<string> $purchaseOrders */
        $purchaseOrders = $checkIn->purchaseOrders->pluck('number')->all();

        return [
            'uuid' => $checkIn->uuid,
            'referenceNumber' => $checkIn->reference_number,
            'status' => $checkIn->status->value,
            'date' => $when->translatedFormat('M j, Y'),
            'time' => $when->format('g:i A T'),
            ...$this->locationContact($location),
            'customer' => $checkIn->customer,
            'destination' => $this->destination($checkIn),
            'truckName' => $checkIn->truck_name,
            'truckPlate' => $this->plate($checkIn->truck_plate, $checkIn->truck_plate_state, $checkIn->truck_plate_country),
            'truckColor' => $checkIn->truck_color,
            'trailerPlate' => $this->plate($checkIn->trailer_plate, $checkIn->trailer_plate_state, $checkIn->trailer_plate_country),
            'trailerChute' => $checkIn->trailer_chute instanceof TrailerChute
                ? $this->chuteLabel($checkIn->trailer_chute)
                : null,
            'emptyWeightLbs' => $checkIn->empty_weight_lbs,
            'driversName' => $checkIn->drivers_name,
            'driversCellphone' => $this->phone->format($checkIn->drivers_cellphone),
            'driversEmail' => $checkIn->drivers_email,
            'licenseMasked' => $this->maskLicense($checkIn->drivers_license_number),
            'licenseState' => $checkIn->drivers_license_state,
            'licenseExpiration' => $this->calendarDate($checkIn->drivers_license_expiration_date),
            'loadingInstructions' => $checkIn->loading_instructions,
            'purchaseOrders' => $purchaseOrders,
            'dock' => $checkIn->dock?->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function appointment(Appointment $appointment): array
    {
        $location = $appointment->location;
        // In the current locale, so a driver's emails and PDF name the month in their language.
        $locale = app()->getLocale();
        $when = $appointment->scheduled_for->setTimezone($location->timezone)->settings(['locale' => $locale]);
        $endsAt = $appointment->ends_at->setTimezone($location->timezone);
        $cancelledAt = $appointment->cancelled_at?->setTimezone($location->timezone)->settings(['locale' => $locale]);

        /** @var list<string> $purchaseOrders */
        $purchaseOrders = $appointment->purchaseOrders->pluck('number')->all();

        return [
            'uuid' => $appointment->uuid,
            'referenceNumber' => $appointment->reference_number,
            'status' => $appointment->status->value,
            'date' => $when->translatedFormat('M j, Y'),
            'time' => $when->format('g:i A T'),
            'timeRange' => sprintf('%s - %s', $when->format('g:i A'), $endsAt->format('g:i A T')),
            'loadType' => $appointment->loadType?->name,
            'isUpcoming' => $appointment->scheduled_for->isFuture(),
            ...$this->locationContact($location),
            'bookedOn' => $appointment->created_at->setTimezone($location->timezone)->settings(['locale' => $locale])->translatedFormat('M j, Y'),
            'driversName' => $appointment->drivers_name,
            'driversCellphone' => $this->phone->format($appointment->drivers_cellphone),
            'cancelledAt' => $cancelledAt?->translatedFormat('M j, Y'),
            'cancelledReason' => $appointment->cancelled_reason,
            'purchaseOrders' => $purchaseOrders,
            'dock' => $appointment->dock?->label(),
        ];
    }

    /**
     * Name, address and the contact block. Phone and email live on Location and
     * are already eager-loaded, but had never reached the payload.
     *
     * @return array<string, mixed>
     */
    private function locationContact(Location $location): array
    {
        return [
            'locationName' => $location->name,
            'locationAbbreviation' => $location->abbreviation,
            'locationAddress' => $this->address->buildAddress($location->address->toArray()),
            'locationPhone' => $this->phone->format($location->phone),
            'locationPhoneExt' => $location->phone_ext,
            'locationEmail' => $location->email,
        ];
    }

    /**
     * The enum's raw value ("center-chute") leaks straight to the screen today.
     */
    /**
     * Reuses the labels the check-in form already ships in all three locales,
     * rather than leaking the raw enum value ("center-chute") the way the
     * detail page did.
     */
    private function chuteLabel(TrailerChute $chute): string
    {
        return match ($chute) {
            TrailerChute::NotApplicable => __('messages.checkInForm.trailerChuteNa'),
            TrailerChute::CenterChute => __('messages.checkInForm.trailerChuteCenter'),
            TrailerChute::SideChute => __('messages.checkInForm.trailerChuteSide'),
        };
    }

    private function destination(CheckIn $checkIn): string
    {
        return implode(', ', array_filter([
            $checkIn->destination_city,
            $checkIn->destination_state,
            $checkIn->destination_country,
        ]));
    }

    private function plate(string $plate, ?string $state, ?string $country): string
    {
        $origin = implode(' ', array_filter([$state, $country]));

        return $origin === '' ? $plate : sprintf('%s (%s)', $plate, $origin);
    }

    /**
     * Never expose the full number. The driver already knows it, and the column
     * is encrypted at rest under a dedicated key.
     */
    private function maskLicense(?string $number): ?string
    {
        if ($number === null || $number === '') {
            return null;
        }

        return str_repeat('•', max(0, mb_strlen($number) - 4)).mb_substr($number, -4);
    }

    /**
     * A calendar date carries no time or zone, so it is formatted as-is. Running
     * it through setTimezone() would shift it a day for anyone west of UTC.
     */
    private function calendarDate(?string $date): ?string
    {
        return $date === null || $date === ''
            ? null
            : Date::parse($date)->settings(['locale' => app()->getLocale()])->translatedFormat('M j, Y');
    }
}
