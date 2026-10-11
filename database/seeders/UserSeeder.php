<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;

final class UserSeeder extends Seeder
{
    public const string ADMIN_EMAIL = 'admin@juanplaza.dev';

    public const int USERS_PER_ROLE = 10;

    /**
     * Predictable addresses (e.g. shipping-manager3@juanplaza.dev) so any role
     * can be signed into locally and reruns skip existing accounts.
     */
    public static function roleUserEmail(Role $role, int $number): string
    {
        return $role->value.$number.'@juanplaza.dev';
    }

    /**
     * Seeds known-credential accounts, so it refuses to run in production.
     */
    public function run(): void
    {
        if (App::isProduction()) {
            return;
        }

        $password = config('app.seed_admin_password');

        $admin = User::query()->firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Juan Plaza',
                'password' => $password,
                'cellphone' => '+15551234567',
                'drivers_license_number' => 'D4821955',
                'drivers_license_state' => 'Arizona',
                'drivers_license_country' => 'US',
                'drivers_license_expiration_date' => Date::now()->addYears(3)->format('Y-m-d'),
            ],
        );

        $admin->forceFill(['email_verified_at' => Date::now()])->save();
        $admin->assignRole(Role::Admin);

        $locationIds = Location::query()->orderBy('id')->pluck('id');

        foreach (Role::cases() as $role) {
            foreach (range(1, self::USERS_PER_ROLE) as $number) {
                $email = self::roleUserEmail($role, $number);

                if (User::query()->where('email', $email)->exists()) {
                    continue;
                }

                $user = User::factory()
                    ->withRole($role)
                    ->create(['email' => $email, 'password' => $password]);

                // Round-robin, so every location has local shipping accounts
                // to sign in as and none of them sees everything.
                if (in_array($role, Role::locationScoped(), true) && $locationIds->isNotEmpty()) {
                    $user->locations()->attach($locationIds[($number - 1) % $locationIds->count()]);
                }
            }
        }
    }
}
