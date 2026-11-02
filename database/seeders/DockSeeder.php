<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Dock;
use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The same two buildings of docks at every location but one, which stays
 * without any so the app can be compared with and without docks.
 */
final class DockSeeder extends Seeder
{
    public const string WITHOUT_DOCKS = 'mar';

    /**
     * @var array<string, list<array{name: string, is_active: bool, accepts_appointments: bool}>>
     */
    private const array BUILDINGS = [
        'Building A' => [
            ['name' => 'Door 1', 'is_active' => true, 'accepts_appointments' => true],
            ['name' => 'Door 2', 'is_active' => true, 'accepts_appointments' => true],
            ['name' => 'Door 3', 'is_active' => true, 'accepts_appointments' => true],
            ['name' => 'Door 4', 'is_active' => true, 'accepts_appointments' => true],
        ],
        'Building B' => [
            ['name' => 'Door 5', 'is_active' => true, 'accepts_appointments' => true],
            ['name' => 'Door 6', 'is_active' => true, 'accepts_appointments' => true],
            ['name' => 'Door 7', 'is_active' => false, 'accepts_appointments' => true],
            ['name' => 'Door 8', 'is_active' => true, 'accepts_appointments' => false],
        ],
    ];

    public function run(): void
    {
        $locations = Location::query()->where('abbreviation', '!=', self::WITHOUT_DOCKS)->get();

        foreach ($locations as $location) {
            $this->seedLocation($location);
        }
    }

    private function seedLocation(Location $location): void
    {
        $buildingOrder = 0;

        foreach (self::BUILDINGS as $buildingName => $docks) {
            $building = Building::query()->firstOrCreate(
                ['location_id' => $location->id, 'name' => $buildingName],
                ['uuid' => Str::uuid()->toString(), 'sort_order' => $buildingOrder++],
            );

            foreach ($docks as $sortOrder => $dock) {
                Dock::query()->firstOrCreate(
                    ['location_id' => $location->id, 'name' => $dock['name']],
                    [
                        'uuid' => Str::uuid()->toString(),
                        'building_id' => $building->id,
                        'is_active' => $dock['is_active'],
                        'accepts_appointments' => $dock['accepts_appointments'],
                        'sort_order' => $sortOrder,
                    ],
                );
            }
        }
    }
}
