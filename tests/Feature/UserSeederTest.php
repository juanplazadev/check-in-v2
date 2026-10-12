<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;

it('seeds the admin account and a fixed number of users per role', function (Role $role): void {
    (new UserSeeder)->run();

    $expected = UserSeeder::USERS_PER_ROLE + ($role === Role::Admin ? 1 : 0);

    expect(User::query()->role($role)->count())->toBe($expected)
        ->and(User::query()->where('email', UserSeeder::ADMIN_EMAIL)->sole()->hasRole(Role::Admin))->toBeTrue();
})->with(Role::cases());

it('does not duplicate role users when run twice', function (): void {
    (new UserSeeder)->run();
    $users = User::query()->count();

    (new UserSeeder)->run();

    expect(User::query()->count())->toBe($users);
});

it('seeds nothing in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    (new UserSeeder)->run();

    expect(User::query()->count())->toBe(0);
});
