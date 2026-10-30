<?php

declare(strict_types=1);

use App\Account\HistoryRecordResolver;
use App\Models\Appointment;
use App\Models\CheckIn;
use Illuminate\Support\Facades\Date;

/*
 * The record payload behind a driver's emails and PDFs names months in the
 * current locale, which the mail is sent in.
 */

it('names the check-in date and license expiry months in the current locale', function (): void {
    $checkIn = CheckIn::factory()->create([
        'created_at' => Date::parse('2026-11-02 15:00:00', 'UTC'),
        'drivers_license_expiration_date' => '2028-01-15',
    ]);

    app()->setLocale('es');

    $record = resolve(HistoryRecordResolver::class)->checkIn($checkIn->load(['location.address', 'purchaseOrders']));

    expect($record['date'])->toBe('nov. 2, 2026')
        ->and($record['licenseExpiration'])->toBe('ene. 15, 2028');
});

it('names the appointment date month in the current locale', function (): void {
    $appointment = Appointment::factory()->scheduledFor(Date::parse('2026-11-02 15:00:00', 'UTC'))->create();

    app()->setLocale('fr');

    $record = resolve(HistoryRecordResolver::class)->appointment($appointment->load(['location.address', 'purchaseOrders']));

    expect($record['date'])->toBe('nov. 2, 2026');
});
