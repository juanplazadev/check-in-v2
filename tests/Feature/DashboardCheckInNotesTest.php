<?php

declare(strict_types=1);

use App\Enums\RecordHistoryEvent;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\CheckIn;
use App\Models\CheckInNote;
use App\Models\Location;
use App\Models\RecordHistory;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia;

/*
 * Internal staff notes on a check-in: who may leave one, the two trails each
 * note writes, and how the detail page shows them.
 */

it('stores a staff note and logs it on the check-in timeline and the author trail', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create();
    $checkIn = CheckIn::factory()->create();

    $this->actingAs($staff)
        ->from(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->post(route('dashboard.checkIns.notes.store', ['uuid' => $checkIn->uuid]), ['body' => 'Driver asked for dock 4.'])
        ->assertRedirect(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]));

    $note = CheckInNote::query()->sole();
    expect($note->check_in_id)->toBe($checkIn->id)
        ->and($note->user_id)->toBe($staff->id)
        ->and($note->body)->toBe('Driver asked for dock 4.');

    $entry = RecordHistory::query()->where('event', RecordHistoryEvent::NoteAdded)->sole();
    expect($entry->recordable_id)->toBe($checkIn->id)
        ->and($entry->user_id)->toBe($staff->id)
        ->and($entry->context)->toBeNull();

    $trail = UserHistory::query()->sole();
    expect($trail->user_id)->toBe($staff->id)
        ->and($trail->event)->toBe(UserHistoryEvent::CheckInNoteAdded)
        ->and($trail->changes)->toBe(['check_in' => $checkIn->uuid]);
});

it('lets staff note an archived check-in', function (): void {
    $checkIn = CheckIn::factory()->create();
    $checkIn->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.notes.store', ['uuid' => $checkIn->uuid]), ['body' => 'Archived by mistake.'])
        ->assertRedirect();

    expect(CheckInNote::query()->sole()->check_in_id)->toBe($checkIn->id);
});

it('forbids a driver from leaving a note', function (): void {
    $checkIn = CheckIn::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Driver)->create())
        ->post(route('dashboard.checkIns.notes.store', ['uuid' => $checkIn->uuid]), ['body' => 'Hello'])
        ->assertForbidden();

    expect(CheckInNote::query()->count())->toBe(0);
});

it('sends a guest to sign in before leaving a note', function (): void {
    $checkIn = CheckIn::factory()->create();

    $this->post(route('dashboard.checkIns.notes.store', ['uuid' => $checkIn->uuid]), ['body' => 'Hello'])
        ->assertRedirect(route('login'));

    expect(CheckInNote::query()->count())->toBe(0);
});

it('returns not found for a check-in at a location the user does not work', function (): void {
    $checkIn = CheckIn::factory()->create();
    $shipping = User::factory()->withRole(Role::Shipping)->worksAt(Location::factory()->create())->create();

    $this->actingAs($shipping)
        ->post(route('dashboard.checkIns.notes.store', ['uuid' => $checkIn->uuid]), ['body' => 'Hello'])
        ->assertNotFound();

    expect(CheckInNote::query()->count())->toBe(0)
        ->and(RecordHistory::query()->where('event', RecordHistoryEvent::NoteAdded)->count())->toBe(0)
        ->and(UserHistory::query()->count())->toBe(0);
});

it('rejects an invalid note body', function (array $payload, string $message): void {
    $checkIn = CheckIn::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->post(route('dashboard.checkIns.notes.store', ['uuid' => $checkIn->uuid]), $payload)
        ->assertSessionHasErrors(['body' => $message]);

    expect(CheckInNote::query()->count())->toBe(0);
})->with([
    'blank' => [['body' => '   '], 'The body field is required.'],
    'too long' => [['body' => str_repeat('a', 2001)], 'The body field must not be greater than 2000 characters.'],
]);

it('shows the notes newest first with a timeline entry for each', function (): void {
    $staff = User::factory()->withRole(Role::Admin)->create(['name' => 'Dana Lee']);
    $checkIn = CheckIn::factory()->create();

    Date::setTestNow(now()->subMinute());
    $this->actingAs($staff)->post(route('dashboard.checkIns.notes.store', ['uuid' => $checkIn->uuid]), ['body' => 'First']);
    Date::setTestNow();
    $this->actingAs($staff)->post(route('dashboard.checkIns.notes.store', ['uuid' => $checkIn->uuid]), ['body' => 'Second']);

    $this->actingAs($staff)
        ->get(route('dashboard.checkIns.show', ['uuid' => $checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('notes', 2)
            ->where('notes.0.body', 'Second')
            ->where('notes.0.author', 'Dana Lee')
            ->where('notes.1.body', 'First')
            ->where('checkIn.history.1.description', 'Note added')
            ->where('checkIn.history.1.actor', 'Dana Lee'));
});

it('keeps a note after its author account is deleted', function (): void {
    $note = CheckInNote::factory()->create();
    $note->user->delete();

    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('dashboard.checkIns.show', ['uuid' => $note->checkIn->uuid]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notes.0.body', $note->body)
            ->where('notes.0.author', null));
});
