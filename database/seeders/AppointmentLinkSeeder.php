<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Random\RandomException;

/**
 * Sets up the check-in detail page's "Link appointment" dialog for a manual
 * try-out: one walk-in with three bookings it should suggest and three it must
 * not, and one walk-in with nothing to suggest. Run on demand rather than from
 * DatabaseSeeder; every run adds records and removes none, and draws a fresh
 * phone so runs never match each other.
 */
final class AppointmentLinkSeeder extends Seeder
{
    private const string DRIVER_NAME = 'Maria Gonzalez';

    /**
     * @throws RandomException
     */
    public function run(): void
    {
        $locations = Location::query()->where('is_active', true)->get();

        if ($locations->isEmpty()) {
            $this->command?->warn('AppointmentLinkSeeder skipped: run LocationSeeder first.');

            return;
        }

        $location = $locations->first();
        $otherLocation = $locations->skip(1)->first();
        $phone = '+1787555'.random_int(1000, 9999);
        $match = ['drivers_name' => self::DRIVER_NAME, 'drivers_cellphone' => $phone];

        $withSuggestions = CheckIn::factory()->pending()->atLocation($location)->createdAt(Date::now())->create($match);

        // Suggested, in this order: phone and name, phone only (a late no-show), name only.
        Appointment::factory()->scheduled()->atLocation($location)->scheduledFor(Date::now()->subMinutes(30))->create($match);
        Appointment::factory()->noShow()->atLocation($location)->scheduledFor(Date::now()->subHours(5))->create([
            'drivers_name' => 'M. Gonzalez',
            'drivers_cellphone' => $phone,
        ]);
        Appointment::factory()->scheduled()->atLocation($location)->scheduledFor(Date::now()->addHours(2))->create([
            'drivers_name' => 'Maria Gonzales',
            'drivers_cellphone' => '+1787555'.random_int(1000, 9999),
        ]);

        // Never suggested: cancelled, and outside the window. The far-off one is
        // still linkable by its reference number.
        Appointment::factory()->cancelled('Seeded decoy')->atLocation($location)->scheduledFor(Date::now())->create($match);
        $farOff = Appointment::factory()->scheduled()->atLocation($location)->scheduledFor(Date::now()->addDays(3))->create($match);

        if ($otherLocation instanceof Location) {
            Appointment::factory()->scheduled()->atLocation($otherLocation)->scheduledFor(Date::now())->create($match);
        }

        $withoutSuggestions = CheckIn::factory()->pending()->atLocation($location)->createdAt(Date::now())->create([
            'drivers_name' => 'Zebulon Quackenbush',
            'drivers_cellphone' => '+1939555'.random_int(1000, 9999),
        ]);

        $this->command?->info('Seeded appointment-link scenarios at '.$location->name.'.');
        $this->command?->line('  3 suggestions:  '.route('dashboard.checkIns.show', ['uuid' => $withSuggestions->uuid]));
        $this->command?->line('  No suggestions: '.route('dashboard.checkIns.show', ['uuid' => $withoutSuggestions->uuid]));
        $this->command?->line('  Link by reference (3 days out, not suggested): '.$farOff->reference_number);
    }
}
