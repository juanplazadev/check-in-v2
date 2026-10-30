<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LoadDirection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The same starter set of load types at every location, so booking shows the
 * load type step out of the box.
 */
final class LoadTypeSeeder extends Seeder
{
    /**
     * @var list<array{name: string, direction: LoadDirection, duration_minutes: int}>
     */
    private const array LOAD_TYPES = [
        ['name' => 'Live unload, palletized', 'direction' => LoadDirection::Inbound, 'duration_minutes' => 60],
        ['name' => 'Live unload, floor loaded', 'direction' => LoadDirection::Inbound, 'duration_minutes' => 180],
        ['name' => 'Drop trailer', 'direction' => LoadDirection::Inbound, 'duration_minutes' => 30],
        ['name' => 'Live load', 'direction' => LoadDirection::Outbound, 'duration_minutes' => 90],
    ];

    public function run(): void
    {
        $locationIds = DB::table('locations')->pluck('id');
        $now = now();

        $rows = [];

        foreach ($locationIds as $locationId) {
            foreach (self::LOAD_TYPES as $sortOrder => $loadType) {
                $rows[] = [
                    'uuid' => Str::uuid()->toString(),
                    'location_id' => $locationId,
                    'name' => $loadType['name'],
                    'direction' => $loadType['direction']->value,
                    'duration_minutes' => $loadType['duration_minutes'],
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('load_types')->insertOrIgnore($rows);
    }
}
