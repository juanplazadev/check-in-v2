<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Appointment\AppointmentScheduleResolver;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Dock;
use App\Models\LoadType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Queries\LiveQueueAppointments;
use App\Queries\LiveQueueCheckIns;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Random\RandomException;

/**
 * Fills the live queue's rolling windows with pending check-ins and upcoming
 * appointments, and each location's today for the dock view. Both move with the
 * clock, so this is run on demand rather than from DatabaseSeeder. A location's
 * docks are filled once a day; every other record adds up with each run.
 */
final class LiveQueueSeeder extends Seeder
{
    private const int CHECK_INS = 20;

    private const int LATE_APPOINTMENTS = 3;

    private const int UPCOMING_APPOINTMENTS = 12;

    /** Every Nth record belongs to the seeded admin driver; the rest are guests. */
    private const int DRIVER_EVERY = 4;

    private const int UNASSIGNED_APPOINTMENTS = 4;

    /** A location without docks has only the unassigned column to fill. */
    private const int UNASSIGNED_APPOINTMENTS_WITHOUT_DOCKS = 12;

    private const int MAX_GAP_MINUTES = 45;

    /** Every Nth finished booking stays scheduled, so one can be dragged after closing. */
    private const int LATE_EVERY = 4;

    /**
     * @throws RandomException
     */
    public function run(): void
    {
        $this->call([LoadTypeSeeder::class, DockSeeder::class]);

        $locations = Location::query()
            ->where('is_active', true)
            ->with(['loadTypes' => fn ($query) => $query->active()])
            ->get();

        if ($locations->isEmpty()) {
            $this->command?->warn('LiveQueueSeeder skipped: run LocationSeeder first.');

            return;
        }

        $driver = User::query()->where('email', UserSeeder::ADMIN_EMAIL)->first();

        $dockDayAppointments = $locations->sum(fn (Location $location): int => $this->seedDockDay($location, $driver));

        $windowMinutes = LiveQueueCheckIns::WINDOW_HOURS * 60;

        for ($i = 1; $i <= self::CHECK_INS; $i++) {
            $this->seedCheckIn($locations, $this->driverFor($driver, $i), Date::now()->subMinutes(random_int(5, $windowMinutes - 60)));
        }

        for ($i = 1; $i <= self::LATE_APPOINTMENTS; $i++) {
            $this->seedAppointment($locations, $this->driverFor($driver, $i), Date::now()->subMinutes(random_int(30, (LiveQueueAppointments::LATE_GRACE_HOURS - 1) * 60)));
        }

        for ($i = 1; $i <= self::UPCOMING_APPOINTMENTS; $i++) {
            $this->seedAppointment($locations, $this->driverFor($driver, $i), Date::now()->addMinutes(random_int(15, (LiveQueueAppointments::AHEAD_HOURS - 1) * 60)));
        }

        $this->command?->info(sprintf(
            'Seeded %d pending check-ins and %d scheduled appointments.',
            self::CHECK_INS,
            self::LATE_APPOINTMENTS + self::UPCOMING_APPOINTMENTS,
        ));

        $this->command?->info(sprintf('Seeded %d dock view appointments for today.', $dockDayAppointments));
    }

    /**
     * Fills the location's local today: each bookable dock from opening to
     * closing, plus a few bookings with no dock. Returns how many it made.
     *
     * @throws RandomException
     */
    private function seedDockDay(Location $location, ?User $driver): int
    {
        $schedule = resolve(AppointmentScheduleResolver::class);
        $date = Date::now($location->timezone)->toDateString();
        $opensAt = $schedule->opensAt($location, $date);
        $closesAt = $schedule->closesAt($location, $date);

        if (! $opensAt instanceof CarbonInterface || ! $closesAt instanceof CarbonInterface) {
            $this->command?->warn(sprintf('%s is closed today, so its dock view was not filled.', $location->name));

            return 0;
        }

        $opensAt = $opensAt->toImmutable();
        $closesAt = $closesAt->toImmutable();

        if ($this->hasDockedBookings($location, $opensAt, $closesAt)) {
            return 0;
        }

        $docks = Dock::query()->whereBelongsTo($location)->bookable()->get();
        $seeded = 0;

        foreach ($docks as $dock) {
            $start = $opensAt;

            while (true) {
                $loadType = $this->randomLoadType($location);
                $start = $start->addMinutes($this->randomGap($location));
                $end = $start->addMinutes($location->appointmentDurationMinutes($loadType));

                if ($end->gt($closesAt)) {
                    break;
                }

                $this->seedDayBooking($location, $driver, $start, $end, $loadType, $dock, ++$seeded);
                $start = $end;
            }
        }

        $unassigned = $docks->isEmpty() ? self::UNASSIGNED_APPOINTMENTS_WITHOUT_DOCKS : self::UNASSIGNED_APPOINTMENTS;
        $interval = $location->appointmentSlotIntervalMinutes();

        for ($i = 0; $i < $unassigned; $i++) {
            $loadType = $this->randomLoadType($location);
            $duration = $location->appointmentDurationMinutes($loadType);
            $latestStart = (int) $opensAt->diffInMinutes($closesAt) - $duration;
            $start = $opensAt->addMinutes(random_int(0, intdiv($latestStart, $interval)) * $interval);

            $this->seedDayBooking($location, $driver, $start, $start->addMinutes($duration), $loadType, null, ++$seeded);
        }

        return $seeded;
    }

    private function hasDockedBookings(Location $location, CarbonImmutable $opensAt, CarbonImmutable $closesAt): bool
    {
        return Appointment::query()
            ->whereBelongsTo($location)
            ->whereNotNull('dock_id')
            ->where('scheduled_for', '>=', $opensAt->utc())
            ->where('scheduled_for', '<', $closesAt->utc())
            ->exists();
    }

    /**
     * @throws RandomException
     */
    private function randomGap(Location $location): int
    {
        $interval = $location->appointmentSlotIntervalMinutes();

        return random_int(0, intdiv(self::MAX_GAP_MINUTES, $interval)) * $interval;
    }

    private function randomLoadType(Location $location): ?LoadType
    {
        return $location->loadTypes->isEmpty() ? null : $location->loadTypes->random();
    }

    /**
     * @throws RandomException
     */
    private function seedDayBooking(Location $location, ?User $driver, CarbonImmutable $start, CarbonImmutable $end, ?LoadType $loadType, ?Dock $dock, int $index): void
    {
        $factory = $this->withClockStatus(Appointment::factory(), $start, $end, $index)
            ->atLocation($location)
            // Stored as given, so a local time would be saved as if it were UTC.
            ->scheduledFor($start->utc())
            ->lasting((int) $start->diffInMinutes($end));

        if ($loadType instanceof LoadType) {
            $factory = $factory->state(['load_type_id' => $loadType->id]);
        }

        if ($dock instanceof Dock) {
            $factory = $factory->onDock($dock);
        }

        $bookingDriver = $this->driverFor($driver, $index);

        if ($bookingDriver instanceof User) {
            $factory = $factory->forUser($bookingDriver);
        }

        $this->attachPurchaseOrders($factory->create());
    }

    private function withClockStatus(AppointmentFactory $factory, CarbonImmutable $start, CarbonImmutable $end, int $index): AppointmentFactory
    {
        $now = Date::now();

        return match (true) {
            $start->gt($now) => $factory->scheduled(),
            $end->gt($now) => $factory->checkedIn(),
            $index % self::LATE_EVERY === 0 => $factory->scheduled(),
            default => $factory->completed(),
        };
    }

    /**
     * @param  Collection<int, Location>  $locations
     */
    private function seedCheckIn(Collection $locations, ?User $driver, CarbonImmutable $when): void
    {
        $factory = CheckIn::factory()
            ->pending()
            ->atLocation($locations->random())
            ->createdAt($when);

        if ($driver instanceof User) {
            $factory = $factory->forUser($driver);
        }

        $this->attachPurchaseOrders($factory->create());
    }

    /**
     * @param  Collection<int, Location>  $locations
     */
    private function seedAppointment(Collection $locations, ?User $driver, CarbonImmutable $when): void
    {
        $slot = $when->setTime($when->hour, intdiv($when->minute, 15) * 15);

        $location = $locations->random();
        $loadType = $this->randomLoadType($location);

        $factory = Appointment::factory()
            ->scheduled()
            ->atLocation($location)
            ->scheduledFor($slot);

        if ($loadType instanceof LoadType) {
            $factory = $factory->ofLoadType($loadType);
        }

        if ($driver instanceof User) {
            $factory = $factory->forUser($driver);
        }

        $this->attachPurchaseOrders($factory->create());
    }

    private function driverFor(?User $driver, int $index): ?User
    {
        return $index % self::DRIVER_EVERY === 0 ? $driver : null;
    }

    private function attachPurchaseOrders(CheckIn|Appointment $record): void
    {
        // Sequential suffix rather than a second random draw, so the
        // (purchasable, number) unique index can never collide within a record.
        $base = random_int(10000, 99900);

        for ($i = 0; $i < random_int(1, 3); $i++) {
            $record->purchaseOrders()->save(new PurchaseOrder([
                'number' => sprintf('PO-%05d', $base + $i),
            ]));
        }
    }
}
