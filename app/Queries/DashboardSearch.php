<?php

declare(strict_types=1);

namespace App\Queries;

use App\Encryption\BlindIndex;
use App\Enums\Permission;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The dashboard header search: a handful of the newest check-ins, appointments
 * and (with view users) users matching one free-text term.
 *
 * A term that is a full UUID also matches check-ins and appointments by uuid,
 * and locations the user may see (with view locations), which are searchable
 * by uuid alone. It is an
 * exact match on the unique index: the term is checked with Str::isUuid()
 * first, since Postgres errors on comparing a uuid column to anything else.
 *
 * Plain ilike on Postgres rather than a search engine, so location scoping is
 * the same visibleTo() every other dashboard read uses. If `%term%` ever gets
 * slow, pg_trgm GIN indexes on these columns are the next step.
 *
 * Each field is only searched where the column exists: appointments carry no
 * plates or license, and reach an email only through the booking account.
 *
 * Every row says which field matched, so a plate or PO search shows the plate
 * or PO rather than leaving the user to guess.
 *
 * @phpstan-type SearchMatch array{field: string, value: string}|null
 */
final readonly class DashboardSearch
{
    public const int PER_TYPE = 5;

    /**
     * Fewer digits than this would match almost every phone number.
     */
    private const int MIN_PHONE_DIGITS = 3;

    public function __construct(
        private BlindIndex $blindIndex,
        private DashboardUsers $users,
    ) {}

    /**
     * @return array{
     *     checkIns: list<array{uuid: string, referenceNumber: string, driverName: string, location: string, status: string, match: SearchMatch}>,
     *     appointments: list<array{uuid: string, referenceNumber: string, driverName: string, location: string, status: string, scheduledFor: string, match: SearchMatch}>,
     *     users: list<array{uuid: string, name: string, email: string}>,
     *     locations: list<array{uuid: string, name: string, abbreviation: string}>,
     * }
     */
    public function execute(User $user, string $term): array
    {
        $digits = $this->phoneDigits($term);
        $uuid = Str::isUuid($term) ? Str::lower($term) : null;

        return [
            'checkIns' => $this->checkIns($user, $term, $digits, $uuid),
            'appointments' => $this->appointments($user, $term, $digits, $uuid),
            'users' => $user->can(Permission::ViewUsers) ? $this->users($term) : [],
            'locations' => $user->can(Permission::ViewLocations) && $uuid !== null ? $this->locations($user, $uuid) : [],
        ];
    }

    /**
     * @return list<array{uuid: string, referenceNumber: string, driverName: string, location: string, status: string, match: SearchMatch}>
     */
    private function checkIns(User $user, string $term, ?string $digits, ?string $uuid): array
    {
        $licenseHash = $this->blindIndex->hash($term);

        return array_values(CheckIn::query()
            ->visibleTo($user)
            ->where(fn (Builder $query): Builder => $this->matchesSharedFields($query, $term, $digits, $uuid)
                ->orWhereContains('drivers_email', $term)
                ->orWhereContains('customer', $term)
                ->orWhereContains('truck_plate', $term)
                ->orWhereContains('trailer_plate', $term)
                ->when($licenseHash, fn (Builder $query, string $hash): Builder => $query->orWhere('drivers_license_hash', $hash)))
            ->with(['location:id,name', 'purchaseOrders:id,purchasable_type,purchasable_id,number'])
            ->latest()
            ->orderByDesc('id')
            ->limit(self::PER_TYPE)
            ->get()
            ->map(fn (CheckIn $checkIn): array => [
                'uuid' => $checkIn->uuid,
                'referenceNumber' => $checkIn->reference_number,
                'driverName' => $checkIn->drivers_name,
                'location' => $checkIn->location->name,
                'status' => $checkIn->status->value,
                'match' => $this->checkInMatch($checkIn, $term, $digits, $uuid, $licenseHash),
            ])
            ->all());
    }

    /**
     * @return list<array{uuid: string, referenceNumber: string, driverName: string, location: string, status: string, scheduledFor: string, match: SearchMatch}>
     */
    private function appointments(User $user, string $term, ?string $digits, ?string $uuid): array
    {
        return array_values(Appointment::query()
            ->visibleTo($user)
            ->where(fn (Builder $query): Builder => $this->matchesSharedFields($query, $term, $digits, $uuid)
                ->orWhereHas('user', fn (Builder $query): Builder => $query->whereContains('email', $term)))
            ->with(['location:id,name', 'purchaseOrders:id,purchasable_type,purchasable_id,number', 'user:id,email'])
            ->latest()
            ->orderByDesc('id')
            ->limit(self::PER_TYPE)
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'uuid' => $appointment->uuid,
                'referenceNumber' => $appointment->reference_number,
                'driverName' => $appointment->drivers_name,
                'location' => $appointment->location->name,
                'status' => $appointment->status->value,
                'scheduledFor' => $appointment->scheduled_for->toIso8601String(),
                'match' => $this->appointmentMatch($appointment, $term, $digits, $uuid),
            ])
            ->all());
    }

    /**
     * @return list<array{uuid: string, name: string, email: string}>
     */
    private function users(string $term): array
    {
        return array_values($this->users->search($term, self::PER_TYPE)
            ->map(fn (User $user): array => ['uuid' => $user->uuid, 'name' => $user->name, 'email' => $user->email])
            ->all());
    }

    /**
     * @return list<array{uuid: string, name: string, abbreviation: string}>
     */
    private function locations(User $user, string $uuid): array
    {
        $locationIds = $user->dashboardLocationIds();

        return array_values(Location::query()
            ->where('uuid', $uuid)
            ->when($locationIds !== null, fn (Builder $query): Builder => $query->whereKey($locationIds))
            ->limit(self::PER_TYPE)
            ->get(['uuid', 'name', 'abbreviation'])
            ->map(fn (Location $location): array => [
                'uuid' => $location->uuid,
                'name' => $location->name,
                'abbreviation' => $location->abbreviation,
            ])
            ->all());
    }

    /**
     * The columns check-ins and appointments both have.
     *
     * @template TModel of CheckIn|Appointment
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function matchesSharedFields(Builder $query, string $term, ?string $digits, ?string $uuid): Builder
    {
        return $query
            ->whereContains('reference_number', $term)
            ->orWhereContains('drivers_name', $term)
            ->when($digits, fn (Builder $query, string $digits): Builder => $query->orWhereContains('drivers_cellphone', $digits))
            ->orWhereHas('purchaseOrders', fn (Builder $query): Builder => $query->whereContains('number', $term))
            ->when($uuid, fn (Builder $query, string $uuid): Builder => $query->orWhere('uuid', $uuid));
    }

    /**
     * The term's digits when it reads as a phone number, else null. Phones are
     * stored as +1XXXXXXXXXX, so "(787) 555-0142" has to match on its digits;
     * but a plate like "XQZ9081" must not match every phone holding "9081".
     */
    private function phoneDigits(string $term): ?string
    {
        if (preg_match('/^[\d\s()+.\-]+$/', $term) !== 1) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $term);

        return mb_strlen($digits) >= self::MIN_PHONE_DIGITS ? $digits : null;
    }

    /**
     * Null when the reference or driver name matched, as the row shows both.
     *
     * @return SearchMatch
     */
    private function checkInMatch(CheckIn $checkIn, string $term, ?string $digits, ?string $uuid, ?string $licenseHash): ?array
    {
        if ($uuid !== null && $checkIn->uuid === $uuid) {
            return ['field' => 'uuid', 'value' => $checkIn->uuid];
        }

        if ($this->contains($checkIn->reference_number, $term) || $this->contains($checkIn->drivers_name, $term)) {
            return null;
        }

        $match = $this->purchaseOrderMatch($checkIn->purchaseOrders, $term)
            ?? $this->textMatch($term, [
                'truckPlate' => $checkIn->truck_plate,
                'trailerPlate' => $checkIn->trailer_plate,
                'email' => $checkIn->drivers_email,
            ])
            ?? $this->phoneMatch($checkIn->drivers_cellphone, $digits)
            ?? $this->textMatch($term, ['customer' => $checkIn->customer]);

        if ($match !== null || $licenseHash === null || $checkIn->drivers_license_hash !== $licenseHash) {
            return $match;
        }

        // Last four only, like the detail page: the full number never leaves the server.
        return ['field' => 'license', 'value' => mb_substr($checkIn->drivers_license_number, -4)];
    }

    /**
     * @return SearchMatch
     */
    private function appointmentMatch(Appointment $appointment, string $term, ?string $digits, ?string $uuid): ?array
    {
        if ($uuid !== null && $appointment->uuid === $uuid) {
            return ['field' => 'uuid', 'value' => $appointment->uuid];
        }

        if ($this->contains($appointment->reference_number, $term) || $this->contains($appointment->drivers_name, $term)) {
            return null;
        }

        return $this->purchaseOrderMatch($appointment->purchaseOrders, $term)
            ?? $this->phoneMatch($appointment->drivers_cellphone, $digits)
            ?? $this->textMatch($term, ['email' => $appointment->user?->email]);
    }

    /**
     * @param  iterable<PurchaseOrder>  $purchaseOrders
     * @return SearchMatch
     */
    private function purchaseOrderMatch(iterable $purchaseOrders, string $term): ?array
    {
        foreach ($purchaseOrders as $purchaseOrder) {
            if ($this->contains($purchaseOrder->number, $term)) {
                return ['field' => 'purchaseOrder', 'value' => $purchaseOrder->number];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return SearchMatch
     */
    private function textMatch(string $term, array $fields): ?array
    {
        foreach ($fields as $field => $value) {
            if ($this->contains($value, $term)) {
                return ['field' => $field, 'value' => (string) $value];
            }
        }

        return null;
    }

    /**
     * @return SearchMatch
     */
    private function phoneMatch(string $cellphone, ?string $digits): ?array
    {
        return $digits !== null && str_contains((string) preg_replace('/\D/', '', $cellphone), $digits)
            ? ['field' => 'cellphone', 'value' => $cellphone]
            : null;
    }

    private function contains(?string $value, string $term): bool
    {
        return $value !== null && mb_stripos($value, $term) !== false;
    }
}
