<?php

declare(strict_types=1);

use App\Auth\RegistrationGate;
use App\Enums\Role;
use App\Models\User;

use function Pest\Laravel\post;

function registrationPayload(): array
{
    return [
        'name' => 'John Driver',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ];
}

it('registers a driver and redirects them to their account', function (): void {
    $this->withSession([RegistrationGate::SESSION_KEY => now()->addMinutes(5)->timestamp])
        ->post('/register', registrationPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account'));

    expect(User::query()->where('email', 'john@example.com')->exists())->toBeTrue();
});

it('gives a newly registered user the driver role', function (): void {
    $this->withSession([RegistrationGate::SESSION_KEY => now()->addMinutes(5)->timestamp])
        ->post('/register', registrationPayload())
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'john@example.com')->sole()->hasRole(Role::Driver))
        ->toBeTrue();
});

it('captures the carried cellphone onto the new account', function (): void {
    $this->withSession([
        RegistrationGate::SESSION_KEY => now()->addMinutes(5)->timestamp,
        RegistrationGate::CELLPHONE_KEY => '+12015550123',
    ])
        ->post('/register', registrationPayload())
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'john@example.com')->sole()->cellphone)
        ->toBe('+12015550123');
});

it('stores the pending check-in on the new account', function (): void {
    $this->withSession([
        RegistrationGate::SESSION_KEY => now()->addMinutes(5)->timestamp,
        RegistrationGate::CLAIM_TYPE_KEY => 'check_in',
        RegistrationGate::CLAIM_ID_KEY => 42,
    ])
        ->post('/register', registrationPayload())
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'john@example.com')->sole())
        ->pending_check_in_id->toBe(42)
        ->pending_appointment_id->toBeNull();
});

it('rejects registration when the window is not open', function (): void {
    post('/register', registrationPayload())
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'john@example.com')->exists())->toBeFalse();
});

it('rejects registration when the window has expired', function (): void {
    $this->withSession([RegistrationGate::SESSION_KEY => now()->subMinute()->timestamp])
        ->post('/register', registrationPayload())
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'john@example.com')->exists())->toBeFalse();
});
