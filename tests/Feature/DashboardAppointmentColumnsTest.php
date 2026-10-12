<?php

declare(strict_types=1);

use App\Enums\AppointmentColumn;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * Which columns the appointment table offers each staff member, and saving
 * their show/hide choices and order to their account.
 */

it('offers every appointment column', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Shipping)->create())
        ->get(route('dashboard.appointments'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('columns.options', array_column(AppointmentColumn::cases(), 'value')));
});

it('returns saved choices and order, dropping unknown columns and appending ones never placed', function (): void {
    $user = User::factory()->withRole(Role::Shipping)->create();
    $user->update(['config' => ['dashboard' => ['appointments' => [
        'columns' => ['driver' => false, 'bogus' => true],
        'column_order' => ['status', 'bogus', 'driver'],
    ]]]]);

    $this->actingAs($user)
        ->get(route('dashboard.appointments'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('columns.visibility', ['driver' => false])
            ->where('columns.order', fn ($order): bool => array_slice($order->all(), 0, 3) === ['status', 'driver', 'date']
                && ! in_array('bogus', $order->all(), true)
                && ! in_array('reference', $order->all(), true)));
});

it('saves column choices to the account, keeping earlier choices and other settings', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    $user->update(['config' => ['other' => 'kept', 'dashboard' => ['appointments' => ['columns' => ['cellphone' => true]]]]]);

    $this->actingAs($user)
        ->patchJson(route('dashboard.appointments.columns'), ['columns' => ['driver' => false]])
        ->assertNoContent();

    expect($user->refresh()->config)->toBe([
        'other' => 'kept',
        'dashboard' => ['appointments' => ['columns' => ['cellphone' => true, 'driver' => false]]],
    ]);
});

it('saves a column order, keeping the saved show/hide choices', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    $user->update(['config' => ['dashboard' => ['appointments' => ['columns' => ['cellphone' => true]]]]]);

    $this->actingAs($user)
        ->patchJson(route('dashboard.appointments.columns'), ['order' => ['status', 'driver']])
        ->assertNoContent();

    expect($user->refresh()->config)->toBe([
        'dashboard' => ['appointments' => [
            'columns' => ['cellphone' => true],
            'column_order' => ['status', 'driver'],
        ]],
    ]);
});

it('rejects invalid column choices', function (array $payload, string $error): void {
    $this->actingAs(User::factory()->withRole(Role::Shipping)->create())
        ->patchJson(route('dashboard.appointments.columns'), $payload)
        ->assertJsonValidationErrors($error);
})->with([
    'unknown column' => [['columns' => ['bogus' => true]], 'columns'],
    'the reference, which is always shown' => [['columns' => ['reference' => false]], 'columns'],
    'a non-boolean value' => [['columns' => ['driver' => 'maybe']], 'columns.driver'],
    'the reference in the order' => [['order' => ['reference']], 'order.0'],
    'a repeated column' => [['order' => ['driver', 'driver']], 'order.0'],
]);

it('forbids saving column choices without a staff role', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->patchJson(route('dashboard.appointments.columns'), ['columns' => ['driver' => false]])
        ->assertForbidden();
});

it('forbids saving the part the user has no permission for', function (array $payload): void {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::ViewAppointments, Permission::SaveAppointmentColumnDisplay);

    $this->actingAs($user)
        ->patchJson(route('dashboard.appointments.columns'), $payload)
        ->assertForbidden();
})->with([
    'order only' => [['order' => ['status']]],
    'display and order' => [['columns' => ['driver' => false], 'order' => ['status']]],
]);
