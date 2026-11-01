<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentSchedule;
use App\Models\Building;
use App\Models\Dock;
use App\Models\LoadType;
use App\Models\Location;
use Database\Seeders\DockSeeder;
use Database\Seeders\LiveQueueSeeder;
use Database\Seeders\LoadTypeSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

use function Pest\Laravel\travelTo;

/**
 * A Tuesday at noon in the location's timezone, open 9am to 5pm.
 */
function locationOpenAtNoon(string $abbreviation = 'pmp'): Location
{
    travelTo(Date::parse('2026-11-03 12:00:00', 'America/Phoenix'));

    $location = Location::factory()->create(['abbreviation' => $abbreviation, 'timezone' => 'America/Phoenix']);

    AppointmentSchedule::factory()->forDay(2)->hours('09:00:00', '17:00:00')->create(['location_id' => $location->id]);

    return $location;
}

/**
 * @return Collection<int, Appointment>
 */
function dockedBookings(Location $location): Collection
{
    return Appointment::query()->whereBelongsTo($location)->whereNotNull('dock_id')->get();
}

it('leaves the location without docks with no buildings or docks', function (): void {
    $withoutDocks = locationOpenAtNoon(DockSeeder::WITHOUT_DOCKS);
    $withDocks = Location::factory()->create(['abbreviation' => 'pmp']);

    (new DockSeeder)->run();

    expect(Building::query()->whereBelongsTo($withoutDocks)->count())->toBe(0)
        ->and(Dock::query()->whereBelongsTo($withoutDocks)->count())->toBe(0)
        ->and(Building::query()->whereBelongsTo($withDocks)->count())->toBe(2)
        ->and(Dock::query()->whereBelongsTo($withDocks)->bookable()->count())->toBe(6);
});

it('removes load types from the location kept without them and gives others theirs', function (): void {
    $withoutLoadTypes = locationOpenAtNoon(LoadTypeSeeder::WITHOUT_LOAD_TYPES);
    $withLoadTypes = Location::factory()->create(['abbreviation' => 'pmp']);
    LoadType::factory()->create(['location_id' => $withoutLoadTypes->id]);

    (new LoadTypeSeeder)->run();

    expect(LoadType::query()->whereBelongsTo($withoutLoadTypes)->count())->toBe(0)
        ->and(LoadType::query()->whereBelongsTo($withLoadTypes)->count())->toBe(4);
});

it('fills every bookable dock within opening hours without overlapping bookings', function (): void {
    $location = locationOpenAtNoon();

    (new LiveQueueSeeder)->run();

    $bookings = dockedBookings($location);
    $opensAt = Date::parse('2026-11-03 09:00:00', 'America/Phoenix');
    $closesAt = Date::parse('2026-11-03 17:00:00', 'America/Phoenix');

    expect($bookings->pluck('dock_id')->unique())->toHaveCount(6);

    foreach ($bookings->groupBy('dock_id') as $dockBookings) {
        $previousEnd = $opensAt;

        foreach ($dockBookings->sortBy('scheduled_for') as $booking) {
            expect($booking->scheduled_for->gte($previousEnd))->toBeTrue()
                ->and($booking->ends_at->lte($closesAt))->toBeTrue();

            $previousEnd = $booking->ends_at;
        }
    }
});

it('makes a docked booking last as long as its load type', function (): void {
    $location = locationOpenAtNoon();

    (new LiveQueueSeeder)->run();

    foreach (dockedBookings($location)->load('loadType') as $booking) {
        expect((int) $booking->scheduled_for->diffInMinutes($booking->ends_at))->toBe($booking->loadType->duration_minutes);
    }
});

it('sets a dock day booking status from the clock, leaving some finished ones late', function (): void {
    $location = locationOpenAtNoon();

    (new LiveQueueSeeder)->run();

    $bookings = dockedBookings($location);
    $finished = $bookings->filter(fn (Appointment $booking): bool => $booking->ends_at->lte(Date::now()));
    $running = $bookings->filter(fn (Appointment $booking): bool => $booking->scheduled_for->lte(Date::now()) && $booking->ends_at->gt(Date::now()));
    $upcoming = $bookings->filter(fn (Appointment $booking): bool => $booking->scheduled_for->gt(Date::now()));

    expect($finished->pluck('status')->contains(AppointmentStatus::Completed))->toBeTrue()
        ->and($running->every(fn (Appointment $booking): bool => $booking->status === AppointmentStatus::CheckedIn))->toBeTrue()
        ->and($upcoming)->not->toBeEmpty()
        ->and($upcoming->every(fn (Appointment $booking): bool => $booking->status === AppointmentStatus::Scheduled))->toBeTrue();
});

it('books only the unassigned column at the location without docks', function (): void {
    $location = locationOpenAtNoon(DockSeeder::WITHOUT_DOCKS);

    (new LiveQueueSeeder)->run();

    expect(dockedBookings($location))->toBeEmpty()
        ->and(Appointment::query()->whereBelongsTo($location)->whereNotNull('load_type_id')->count())->toBeGreaterThanOrEqual(12);
});

it('books the default slot length at the location without load types', function (): void {
    $location = locationOpenAtNoon(LoadTypeSeeder::WITHOUT_LOAD_TYPES);

    (new LiveQueueSeeder)->run();

    $bookings = dockedBookings($location);

    expect($bookings)->not->toBeEmpty()
        ->and($bookings->whereNotNull('load_type_id'))->toBeEmpty();

    foreach ($bookings as $booking) {
        expect((int) $booking->scheduled_for->diffInMinutes($booking->ends_at))->toBe($location->appointmentSlotIntervalMinutes());
    }
});

it('adds no docks, load types or docked bookings on a second run', function (): void {
    $location = locationOpenAtNoon();

    (new LiveQueueSeeder)->run();

    $docks = Dock::query()->count();
    $loadTypes = LoadType::query()->count();
    $bookings = dockedBookings($location)->count();

    (new LiveQueueSeeder)->run();

    expect(Dock::query()->count())->toBe($docks)
        ->and(LoadType::query()->count())->toBe($loadTypes)
        ->and(dockedBookings($location)->count())->toBe($bookings);
});

it('does not fill the dock view of a location closed today', function (): void {
    travelTo(Date::parse('2026-11-03 12:00:00', 'America/Phoenix'));
    $location = Location::factory()->create(['abbreviation' => 'pmp', 'timezone' => 'America/Phoenix']);
    $location->appointmentSchedule()->delete();

    (new LiveQueueSeeder)->run();

    expect(dockedBookings($location))->toBeEmpty();
});
