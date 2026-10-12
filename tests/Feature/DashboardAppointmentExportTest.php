<?php

declare(strict_types=1);

use App\Dashboard\ExportFiles;
use App\Enums\ExportScope;
use App\Enums\Role;
use App\Jobs\ExportAppointmentsJob;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\User;
use App\Notifications\ExportFailed;
use App\Notifications\ExportReady;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\post;

/*
 * The appointment spreadsheets: who may ask for one, what the current view and
 * master files hold, and how the file reaches its owner. The pipeline shared
 * with check-ins (attachment limit, pruning) is covered in
 * DashboardCheckInExportTest.
 */

beforeEach(function (): void {
    Storage::fake('local');
});

it('forbids the master export to everyone but admins', function (Role $role): void {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->postJson(route('dashboard.appointments.exports.store'), ['scope' => 'all'])
        ->assertForbidden();
})->with([Role::ShippingManager, Role::Shipping, Role::Sales, Role::Driver]);

it('forbids the current view export to sales and drivers', function (Role $role): void {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->postJson(route('dashboard.appointments.exports.store'), ['scope' => 'view', 'columns' => ['reference']])
        ->assertForbidden();
})->with([Role::Sales, Role::Driver]);

it('redirects guests to the login page', function (): void {
    post(route('dashboard.appointments.exports.store'), ['scope' => 'all'])
        ->assertRedirect(route('login'));
});

it('queues the export on the low-priority exports queue', function (Role $role): void {
    Queue::fake();

    $this->actingAs(User::factory()->withRole($role)->create())
        ->postJson(route('dashboard.appointments.exports.store'), [
            'scope' => 'view',
            'columns' => ['driver', 'reference'],
            'status' => 'scheduled',
            'tz' => 'America/Chicago',
        ])
        ->assertAccepted();

    Queue::assertPushedOn('exports', ExportAppointmentsJob::class, fn (ExportAppointmentsJob $job): bool => $job->scope === ExportScope::View
        && $job->columns === ['reference', 'driver']
        && $job->filters['status'] === 'scheduled'
        && $job->filters['timezone'] === 'America/Chicago');
})->with([Role::Admin, Role::ShippingManager, Role::Shipping]);

it('rejects an export without a scope, a current view without columns, or an unknown column', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->postJson(route('dashboard.appointments.exports.store'), [])
        ->assertJsonValidationErrors('scope');

    $this->postJson(route('dashboard.appointments.exports.store'), ['scope' => 'view'])
        ->assertJsonValidationErrors('columns');

    $this->postJson(route('dashboard.appointments.exports.store'), ['scope' => 'view', 'columns' => ['reference', 'customer']])
        ->assertJsonValidationErrors('columns.1');
});

it('exports the current view with its filters, sort and columns', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    Appointment::factory()->scheduled()->create(['drivers_name' => 'Alice']);
    Appointment::factory()->scheduled()->create(['drivers_name' => 'Zoe']);
    Appointment::factory()->completed()->create(['drivers_name' => 'Mike']);
    $today = now()->format('Y-m-d');

    $this->actingAs($user)
        ->postJson(route('dashboard.appointments.exports.store'), [
            'scope' => 'view',
            'status' => 'scheduled',
            'date_field' => 'booked',
            'from' => $today,
            'to' => $today,
            'sort' => 'driver',
            'direction' => 'desc',
            'columns' => ['driver', 'date', 'status'],
        ])
        ->assertAccepted();

    expect(exportedRows())->sequence(
        fn ($row) => $row->toBe(['Reference', 'Driver', 'Date (Booked)', 'Status']),
        fn ($row) => $row->{1}->toBe('Zoe')->{2}->toBe(now()->format('Y-m-d H:i'))->{3}->toBe('Scheduled'),
        fn ($row) => $row->{1}->toBe('Alice')->{3}->toBe('Scheduled'),
    );
});

it('exports every appointment to the master file, archived included', function (): void {
    $location = Location::factory()->create();
    $user = User::factory()->withRole(Role::Admin)->create();
    $archived = Appointment::factory()->cancelled('Truck broke down')->atLocation($location)->create();
    $archived->delete();
    Appointment::factory()->completed()->atLocation($location)->create();

    $this->actingAs($user)
        ->postJson(route('dashboard.appointments.exports.store'), ['scope' => 'all', 'status' => 'completed'])
        ->assertAccepted();

    $rows = exportedRows();
    $header = $rows[0];
    $archivedRow = collect($rows)->firstWhere(0, $archived->reference_number);

    expect($rows)->toHaveCount(3)
        ->and($archivedRow[array_search('Status', $header, true)])->toBe('Cancelled')
        ->and($archivedRow[array_search('Cancelation reason', $header, true)])->toBe('Truck broke down')
        ->and($archivedRow[array_search('Archived at', $header, true)])->not->toBeEmpty();
});

it('emails the spreadsheet to the requester and confirms in the bell', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    Appointment::factory()->count(2)->create();

    $this->actingAs($user)
        ->postJson(route('dashboard.appointments.exports.store'), ['scope' => 'all'])
        ->assertAccepted();

    $email = sentExportEmail();
    $notification = $user->notifications()->sole();

    expect($email->getTo()[0]->getAddress())->toBe($user->email)
        ->and($email->getSubject())->toStartWith('Appointment export - ')
        ->and($email->getAttachments()[0]->getFilename())->toBe($notification->data['filename'])
        ->and($notification->type)->toBe(ExportReady::TYPE)
        ->and($notification->data['subject'])->toBe('appointments')
        ->and($notification->data['rowCount'])->toBe(2)
        ->and($notification->data['filename'])->toStartWith('appointments-master-')
        ->and(Storage::disk('local')->allFiles(ExportFiles::DIRECTORY))->toBe([]);
});

it('tells the requester when the export fails, linking back to the appointments', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();

    new ExportAppointmentsJob($user, ExportScope::All, [], [], (string) Str::uuid(), 'appointments.xlsx')
        ->failed(new RuntimeException('disk full'));

    $notification = $user->notifications()->sole();

    expect($notification->type)->toBe(ExportFailed::TYPE)
        ->and($notification->data['subject'])->toBe('appointments');

    $this->actingAs($user)
        ->post(route('dashboard.notifications.read', $notification->id))
        ->assertRedirect(route('dashboard.appointments'));
});
