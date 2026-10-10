<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Queries\LiveQueueAppointments;
use App\Queries\LiveQueueCheckIns;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Random\RandomException;

/**
 * Fills the live queue's rolling windows with pending check-ins and upcoming
 * appointments. The windows move with the clock, so this is run on demand
 * rather than from DatabaseSeeder; every run adds records and removes none.
 */
final class LiveQueueSeeder extends Seeder
{
    private const int CHECK_INS = 20;

    private const int LATE_APPOINTMENTS = 3;

    private const int UPCOMING_APPOINTMENTS = 12;

    /** Every Nth record belongs to the seeded admin driver; the rest are guests. */
    private const int DRIVER_EVERY = 4;

    /**
     * @throws RandomException
     */
    public function run(): void
    {
        $locations = Location::query()->where('is_active', true)->get();

        if ($locations->isEmpty()) {
            $this->command?->warn('LiveQueueSeeder skipped: run LocationSeeder first.');

            return;
        }

        $driver = User::query()->where('email', UserSeeder::ADMIN_EMAIL)->first();

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

        $factory = Appointment::factory()
            ->scheduled()
            ->atLocation($locations->random())
            ->scheduledFor($slot);

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
