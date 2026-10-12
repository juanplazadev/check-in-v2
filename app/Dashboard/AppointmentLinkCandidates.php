<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The bookings a staff member may tie a check-in to, and the ranked few the
 * detail page suggests.
 *
 * A booking is linkable only at the check-in's own location, while still
 * scheduled or marked no-show (a late driver), and while no other check-in
 * holds it. Suggestions are never linked automatically: a shared fleet phone
 * or a common name would merge the wrong trails, so staff confirm each one.
 */
final readonly class AppointmentLinkCandidates
{
    /** How many suggestions the dialog shows. */
    private const int LIMIT = 5;

    /** How far either side of the check-in's arrival a booking may be scheduled. */
    private const int WINDOW_HOURS = 24;

    /** How alike two names must be, as a similar_text() percentage, to count as a match. */
    private const float NAME_THRESHOLD = 70.0;

    /**
     * Every booking the check-in may be linked to, with no time window. An
     * archived check-in still holds its booking, hence withTrashed.
     *
     * @return Builder<Appointment>
     */
    public function linkable(CheckIn $checkIn, User $user): Builder
    {
        return Appointment::query()
            ->visibleTo($user)
            ->where('location_id', $checkIn->location_id)
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::NoShow])
            ->whereDoesntHave('checkIn', fn (Builder $query): Builder => $query->withTrashed());
    }

    /**
     * The best few bookings near the check-in's arrival that share its phone
     * or a similar name, phone matches first, then the closest in time.
     *
     * @return array<int, array{uuid: string, referenceNumber: string, scheduledFor: string, driverName: string, cellphoneLastFour: string, phoneMatch: bool, nameMatch: bool}>
     */
    public function suggest(CheckIn $checkIn, User $user): array
    {
        $arrival = $checkIn->created_at;

        return $this->linkable($checkIn, $user)
            ->whereBetween('scheduled_for', [$arrival->subHours(self::WINDOW_HOURS), $arrival->addHours(self::WINDOW_HOURS)])
            ->limit(100)
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'appointment' => $appointment,
                'phoneMatch' => $appointment->drivers_cellphone === $checkIn->drivers_cellphone,
                'nameMatch' => $this->namesMatch($appointment->drivers_name, $checkIn->drivers_name),
                'distance' => abs($appointment->scheduled_for->diffInSeconds($arrival)),
            ])
            ->filter(fn (array $candidate): bool => $candidate['phoneMatch'] || $candidate['nameMatch'])
            ->sortBy([
                ['phoneMatch', 'desc'],
                ['nameMatch', 'desc'],
                ['distance', 'asc'],
            ])
            ->take(self::LIMIT)
            ->map(fn (array $candidate): array => [
                'uuid' => $candidate['appointment']->uuid,
                'referenceNumber' => $candidate['appointment']->reference_number,
                'scheduledFor' => $candidate['appointment']->scheduled_for->toIso8601String(),
                'driverName' => $candidate['appointment']->drivers_name,
                'cellphoneLastFour' => mb_substr($candidate['appointment']->drivers_cellphone, -4),
                'phoneMatch' => $candidate['phoneMatch'],
                'nameMatch' => $candidate['nameMatch'],
            ])
            ->values()
            ->all();
    }

    private function namesMatch(string $first, string $second): bool
    {
        $normalize = static fn (string $name): string => Str::of($name)->ascii()->lower()->squish()->toString();

        similar_text($normalize($first), $normalize($second), $percent);

        return $percent >= self::NAME_THRESHOLD;
    }
}
