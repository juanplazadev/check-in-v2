<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

/*
 * Pins who may do what, as granted by the create_default_permissions migration. Spelled out here
 * rather than read from Role::permissions(), so a change to the grants has to be made twice.
 */

$staffBasics = [
    'view overview', 'view live queue', 'view check-ins', 'view appointments', 'view locations',
    'view notifications', 'search dashboard', 'filter check-ins', 'sync check-ins to erp',
    'write check-in notes', 'write appointment notes', 'save check-in column display',
    'save check-in column order', 'view check-in pdf', 'print check-in pdf', 'filter appointments',
    'save appointment column display', 'save appointment column order', 'view appointment pdf',
    'print appointment pdf',
];

$shipping = [
    ...$staffBasics,
    'complete check-ins', 'email check-in pdf', 'email appointment pdf', 'cancel check-ins', 'cancel appointments',
    'reveal driver licenses', 'archive check-ins', 'restore check-ins', 'archive appointments',
    'restore appointments', 'export check-ins view', 'export appointments view',
];

dataset('role permissions', [
    'admin' => [Role::Admin, array_column(Permission::cases(), 'value')],
    'shipping manager' => [Role::ShippingManager, [
        ...$shipping,
        'reopen check-ins', 'unsync check-ins from erp', 'edit check-ins', 'reinstate check-ins', 'reinstate appointments',
        'link check-in appointments', 'pause check-ins',
    ]],
    'shipping' => [Role::Shipping, $shipping],
    'sales' => [Role::Sales, [...$staffBasics, 'view all locations']],
    'driver' => [Role::Driver, ['access driver account']],
]);

it('grants each role exactly its permissions', function (Role $role, array $expected): void {
    $user = User::factory()->withRole($role)->create();

    expect($user->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(collect($expected)->sort()->values()->all());
})->with('role permissions');

it('only lets admins view users or roles and permissions, manage location users, edit locations, change location status, pause appointments, delete check-ins or appointments, and export the master spreadsheets', function (Role $role): void {
    $user = User::factory()->withRole($role)->create();

    expect($user->can(Permission::ViewUsers))->toBe($role === Role::Admin)
        ->and($user->can(Permission::DeleteCheckIns))->toBe($role === Role::Admin)
        ->and($user->can(Permission::DeleteAppointments))->toBe($role === Role::Admin)
        ->and($user->can(Permission::ExportCheckInsMaster))->toBe($role === Role::Admin)
        ->and($user->can(Permission::ExportAppointmentsMaster))->toBe($role === Role::Admin)
        ->and($user->can(Permission::ViewRolesAndPermissions))->toBe($role === Role::Admin)
        ->and($user->can(Permission::ManageLocationUsers))->toBe($role === Role::Admin)
        ->and($user->can(Permission::EditLocations))->toBe($role === Role::Admin)
        ->and($user->can(Permission::ManageLocationStatus))->toBe($role === Role::Admin)
        ->and($user->can(Permission::PauseAppointments))->toBe($role === Role::Admin)
        ->and($user->can(Permission::DeleteLocations))->toBe($role === Role::Admin)
        ->and($user->can(Permission::RestoreLocations))->toBe($role === Role::Admin);
})->with(Role::cases());
