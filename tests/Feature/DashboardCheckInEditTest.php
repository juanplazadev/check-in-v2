<?php

declare(strict_types=1);

use App\Encryption\BlindIndex;
use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\TrailerChute;
use App\Enums\UserHistoryEvent;
use App\Events\CheckInUpdated;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;

/*
 * Editing a check-in's driver, load and equipment details from the dashboard:
 * who may do it, the before and after values both trails record, and the
 * payloads it refuses.
 */

/**
 * The check-in's current values as the edit form submits them, for a
 * location that shows none of the optional fields.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function editPayload(CheckIn $checkIn, array $overrides = []): array
{
    return [
        'customer' => $checkIn->customer,
        'destination_city' => $checkIn->destination_city,
        'destination_state' => $checkIn->destination_state,
        'destination_country' => $checkIn->destination_country,
        'po_numbers' => $checkIn->purchaseOrders()->pluck('number')->all(),
        'truck_name' => $checkIn->truck_name,
        'truck_plate' => $checkIn->truck_plate,
        'trailer_plate' => $checkIn->trailer_plate,
        'trailer_chute' => $checkIn->trailer_chute?->value,
        'drivers_name' => $checkIn->drivers_name,
        'drivers_cellphone' => $checkIn->drivers_cellphone,
        'drivers_email' => $checkIn->drivers_email,
        'drivers_license_number' => null,
        'loading_instructions' => $checkIn->loading_instructions,
        ...$overrides,
    ];
}

function editableCheckIn(Location $location, array $attributes = []): CheckIn
{
    $checkIn = CheckIn::factory()->atLocation($location)->create([
        'drivers_name' => 'Ana Ruiz',
        'truck_plate' => 'ABC123',
        'trailer_chute' => TrailerChute::SideChute,
        'drivers_license_number' => 'D1234567',
        ...$attributes,
    ]);
    $checkIn->purchaseOrders()->createMany([['number' => 'PO-1111'], ['number' => 'SO-2222']]);

    return $checkIn;
}

it('edits a check-in and logs each changed field on the timeline and the staff trail', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);
    $staff = User::factory()->withRole($role)->worksAt($location)->create();

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'drivers_name' => 'Ana Ruiz Soto',
            'truck_plate' => 'xyz 789',
            'trailer_chute' => TrailerChute::CenterChute->value,
        ]))
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertSessionHasNoErrors();

    $checkIn->refresh();
    expect($checkIn->drivers_name)->toBe('Ana Ruiz Soto')
        ->and($checkIn->truck_plate)->toBe('XYZ 789')
        ->and($checkIn->trailer_chute)->toBe(TrailerChute::CenterChute);

    $changes = [
        'drivers_name' => ['from' => 'Ana Ruiz', 'to' => 'Ana Ruiz Soto'],
        'truck_plate' => ['from' => 'ABC123', 'to' => 'XYZ 789'],
        'trailer_chute' => ['from' => 'side-chute', 'to' => 'center-chute'],
    ];

    $entry = RecordHistory::query()->where('event', RecordHistoryEvent::DetailsEdited)->sole();
    expect($entry->recordable_id)->toBe($checkIn->id)
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->context)->toEqualCanonicalizing($changes);

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInEdited)
        ->and($trail->changes)->toEqualCanonicalizing(['check_in' => $checkIn->uuid, ...$changes]);
})->with([
    'admin' => [Role::Admin],
    'shipping manager' => [Role::ShippingManager],
]);

it('edits a completed check-in', function (): void {
    $location = Location::factory()->create();
    $checkIn = CheckIn::factory()->atLocation($location)->completed()->create(['customer' => 'Acme']);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'po_numbers' => ['PO-1234'],
            'customer' => 'Acme Foods',
        ]))
        ->assertSessionHasNoErrors();

    expect($checkIn->refresh()->customer)->toBe('Acme Foods');
});

it('adds and removes purchase orders and logs both lists', function (): void {
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'po_numbers' => ['so-2222', 'pu-3333'],
        ]))
        ->assertSessionHasNoErrors();

    expect($checkIn->purchaseOrders()->pluck('number')->all())->toEqualCanonicalizing(['SO-2222', 'PU-3333'])
        ->and(PurchaseOrder::query()->count())->toBe(2);

    $entry = RecordHistory::query()->where('event', RecordHistoryEvent::DetailsEdited)->sole();
    expect($entry->context)->toBe([
        'po_numbers' => ['from' => ['PO-1111', 'SO-2222'], 'to' => ['SO-2222', 'PU-3333']],
    ]);
});

it('changes the license number and keeps it out of both trails', function (): void {
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'drivers_license_number' => 'Z9999999',
        ]))
        ->assertSessionHasNoErrors();

    $checkIn->refresh();
    expect($checkIn->drivers_license_number)->toBe('Z9999999')
        ->and($checkIn->drivers_license_hash)->toBe(resolve(BlindIndex::class)->hash('Z9999999'))
        ->and(RecordHistory::query()->where('event', RecordHistoryEvent::DetailsEdited)->sole()->context)
        ->toBe(['drivers_license_number' => ['redacted' => true]])
        ->and(UserHistory::query()->sole()->changes)
        ->toBe(['check_in' => $checkIn->uuid, 'drivers_license_number' => ['changed' => true]]);
});

it('keeps the license number when it is left blank or resubmitted unchanged', function (?string $licenseNumber): void {
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'drivers_license_number' => $licenseNumber,
        ]))
        ->assertSessionHasNoErrors();

    expect($checkIn->refresh()->drivers_license_number)->toBe('D1234567')
        ->and(RecordHistory::query()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'blank' => [null],
    'unchanged' => ['D1234567'],
]);

it('writes no history when nothing changed', function (): void {
    Event::fake([CheckInUpdated::class]);
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'po_numbers' => ['so-2222', 'po-1111'],
        ]))
        ->assertSessionHasNoErrors();

    expect(RecordHistory::query()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
    Event::assertNotDispatched(CheckInUpdated::class);
});

it('broadcasts the edited check-in to open dashboards', function (): void {
    Event::fake([CheckInUpdated::class]);
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'drivers_name' => 'Ana Ruiz Soto',
        ]));

    Event::assertDispatched(CheckInUpdated::class, fn (CheckInUpdated $event): bool => $event->checkIn->is($checkIn)
        && $event->checkIn->drivers_name === 'Ana Ruiz Soto');
});

it('validates the optional fields a location shows on its form', function (): void {
    $location = Location::factory()->checkinAllOptionalFields()->create();
    $checkIn = editableCheckIn($location, ['empty_weight_lbs' => 15000]);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn))
        ->assertSessionHasErrors(['truck_color', 'empty_weight_lbs', 'drivers_license_state']);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'truck_plate_state' => $checkIn->truck_plate_state,
            'truck_plate_country' => $checkIn->truck_plate_country,
            'truck_color' => 'red',
            'trailer_plate_state' => $checkIn->trailer_plate_state,
            'trailer_plate_country' => $checkIn->trailer_plate_country,
            'empty_weight_lbs' => 18000,
            'drivers_license_state' => $checkIn->drivers_license_state,
            'drivers_license_country' => $checkIn->drivers_license_country,
            'drivers_license_expiration_date' => '2020-01-31',
        ]))
        ->assertSessionHasNoErrors();

    $checkIn->refresh();
    expect((int) $checkIn->empty_weight_lbs)->toBe(18000)
        ->and($checkIn->truck_color)->toBe('red')
        ->and($checkIn->drivers_license_expiration_date)->toBe('2020-01-31');
});

it('rejects the whole edit when the payload carries a field the edit does not cover', function (string $field, mixed $value): void {
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patchJson(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'drivers_name' => 'Ana Ruiz Soto',
            $field => $value,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($checkIn->refresh()->drivers_name)->toBe('Ana Ruiz')
        ->and(RecordHistory::query()->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'status' => ['status', 'completed'],
    'location' => ['location_id', 999],
    'license hash' => ['drivers_license_hash', 'forged'],
]);

it('rejects an invalid value and writes nothing', function (): void {
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'drivers_name' => 'Ana Ruiz Soto',
            'truck_plate' => 'BAD!PLATE',
            'po_numbers' => ['PO-1111', 'po-1111'],
        ]))
        ->assertSessionHasErrors([
            'truck_plate' => __('messages.checkInForm.plateInvalid'),
            'po_numbers.1' => __('messages.purchaseOrders.duplicate'),
        ]);

    expect($checkIn->refresh()->drivers_name)->toBe('Ana Ruiz')
        ->and(RecordHistory::query()->count())->toBe(0);
});

it('forbids other roles from editing a check-in', function (Role $role): void {
    $location = Location::factory()->create();
    $checkIn = editableCheckIn($location);

    $this->actingAs(User::factory()->withRole($role)->worksAt($location)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'drivers_name' => 'Ana Ruiz Soto',
        ]))
        ->assertForbidden();

    expect($checkIn->refresh()->drivers_name)->toBe('Ana Ruiz')
        ->and(UserHistory::query()->count())->toBe(0);
})->with([
    'shipping' => [Role::Shipping],
    'sales' => [Role::Sales],
    'driver' => [Role::Driver],
]);

it('sends a guest to sign in before editing a check-in', function (): void {
    $checkIn = editableCheckIn(Location::factory()->create());

    $this->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn))
        ->assertRedirect(route('login'));
});

it('returns not found for a check-in at a location the user does not work, even with a bad payload', function (array $overrides): void {
    $checkIn = editableCheckIn(Location::factory()->create());
    $manager = User::factory()->withRole(Role::ShippingManager)->worksAt(Location::factory()->create())->create();

    $this->actingAs($manager)
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, $overrides))
        ->assertNotFound();

    expect($checkIn->refresh()->drivers_name)->toBe('Ana Ruiz');
})->with([
    'valid' => [['drivers_name' => 'Ana Ruiz Soto']],
    'invalid' => [['drivers_name' => '']],
]);

it('returns not found for an archived check-in', function (): void {
    $checkIn = editableCheckIn(Location::factory()->create());
    $checkIn->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
            'drivers_name' => 'Ana Ruiz Soto',
        ]))
        ->assertNotFound();

    expect(CheckIn::withTrashed()->find($checkIn->id)->drivers_name)->toBe('Ana Ruiz');
});

it('shows each edited field on the timeline, hiding the license number', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $checkIn = editableCheckIn(Location::factory()->create(), ['drivers_cellphone' => '+15551234567']);

    $this->actingAs($staff)->patch(route('dashboard.checkIns.update', ['uuid' => $checkIn->uuid]), editPayload($checkIn, [
        'drivers_cellphone' => '(555) 765-4321',
        'drivers_license_number' => 'Z9999999',
    ]));

    $this->actingAs($staff)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checkIn.history.0.description', 'Details edited')
            ->where('checkIn.history.0.actor', 'Dana Lee')
            ->where('checkIn.history.0.changes', [
                [
                    'field' => 'drivers_cellphone',
                    'label' => 'Driver cellphone',
                    'from' => '+1 (555) 123-4567',
                    'to' => '+1 (555) 765-4321',
                    'redacted' => false,
                ],
                [
                    'field' => 'drivers_license_number',
                    'label' => 'License number',
                    'from' => null,
                    'to' => null,
                    'redacted' => true,
                ],
            ]));
});
