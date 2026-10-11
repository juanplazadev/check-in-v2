<?php

declare(strict_types=1);

use App\Dashboard\CheckInExportFiles;
use App\Enums\CheckInExportScope;
use App\Enums\Role;
use App\Jobs\ExportCheckInsJob;
use App\Mail\CheckInExportMail;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use App\Notifications\CheckInExportFailed;
use App\Notifications\CheckInExportReady;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;
use Symfony\Component\Mime\Email;

use function Pest\Laravel\post;

/*
 * The check-in spreadsheets: who may ask for one, what the current view and
 * master files hold, and how the file reaches its owner by email. The queue
 * runs synchronously and mail goes to the array transport, so a request builds
 * the file and the tests read the attachment that was actually sent.
 */

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * The one email sent during the test.
 */
function sentExportEmail(): Email
{
    $messages = Mail::mailer()->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    return $messages->first()->getOriginalMessage();
}

/**
 * Every row of the emailed spreadsheet, header first.
 *
 * @return list<list<mixed>>
 */
function exportedRows(): array
{
    $attachments = sentExportEmail()->getAttachments();

    expect($attachments)->toHaveCount(1);

    $path = tempnam(sys_get_temp_dir(), 'export');
    file_put_contents($path, $attachments[0]->getBody());

    try {
        return SimpleExcelReader::create($path, 'xlsx')
            ->noHeaderRow()
            ->getRows()
            ->map(fn (array $row): array => array_values($row))
            ->all();
    } finally {
        unlink($path);
    }
}

it('forbids the master export to everyone but admins', function (Role $role): void {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->postJson(route('dashboard.checkIns.exports.store'), ['scope' => 'all'])
        ->assertForbidden();
})->with([Role::ShippingManager, Role::Shipping, Role::Sales, Role::Driver]);

it('forbids the current view export to sales and drivers', function (Role $role): void {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->postJson(route('dashboard.checkIns.exports.store'), ['scope' => 'view', 'columns' => ['reference']])
        ->assertForbidden();
})->with([Role::Sales, Role::Driver]);

it('redirects guests to the login page', function (): void {
    post(route('dashboard.checkIns.exports.store'), ['scope' => 'all'])
        ->assertRedirect(route('login'));
});

it('queues the export on its own low-priority queue', function (Role $role): void {
    Queue::fake();

    $this->actingAs(User::factory()->withRole($role)->create())
        ->postJson(route('dashboard.checkIns.exports.store'), [
            'scope' => 'view',
            'columns' => ['customer', 'reference'],
            'status' => 'pending',
            'tz' => 'America/Chicago',
        ])
        ->assertAccepted();

    Queue::assertPushedOn('exports', ExportCheckInsJob::class, fn (ExportCheckInsJob $job): bool => $job->scope === CheckInExportScope::View
        && $job->columns === ['reference', 'customer']
        && $job->filters['status'] === 'pending'
        && $job->filters['timezone'] === 'America/Chicago');
})->with([Role::Admin, Role::ShippingManager, Role::Shipping]);

it('rejects an export without a scope or a current view without columns', function (): void {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->postJson(route('dashboard.checkIns.exports.store'), [])
        ->assertJsonValidationErrors('scope');

    $this->postJson(route('dashboard.checkIns.exports.store'), ['scope' => 'view'])
        ->assertJsonValidationErrors('columns');
});

it('rejects a column the user is not offered', function (): void {
    // A plain location does not collect truck color, so the column is not offered.
    $location = Location::factory()->create();

    $this->actingAs(User::factory()->withRole(Role::ShippingManager)->worksAt($location)->create())
        ->postJson(route('dashboard.checkIns.exports.store'), ['scope' => 'view', 'columns' => ['reference', 'truckColor']])
        ->assertJsonValidationErrors('columns.1');
});

it('exports the current view with its filters, sort and columns', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    CheckIn::factory()->pending()->create(['customer' => 'Acme']);
    CheckIn::factory()->pending()->create(['customer' => 'Zenith']);
    CheckIn::factory()->completed()->create(['customer' => 'Middle']);

    $this->actingAs($user)
        ->postJson(route('dashboard.checkIns.exports.store'), [
            'scope' => 'view',
            'status' => 'pending',
            'sort' => 'customer',
            'direction' => 'desc',
            'columns' => ['customer', 'status'],
        ])
        ->assertAccepted();

    expect(exportedRows())->sequence(
        fn ($row) => $row->toBe(['Reference', 'Customer', 'Status']),
        fn ($row) => $row->{1}->toBe('Zenith')->{2}->toBe('Pending'),
        fn ($row) => $row->{1}->toBe('Acme')->{2}->toBe('Pending'),
    );
});

it('exports every check-in to the master file, archived included, with only the last four of the license', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    $archived = CheckIn::factory()->create(['drivers_license_number' => 'D1234-5678', 'locale' => 'es']);
    $archived->delete();
    CheckIn::factory()->completed()->create();

    $this->actingAs($user)
        ->postJson(route('dashboard.checkIns.exports.store'), ['scope' => 'all', 'status' => 'completed'])
        ->assertAccepted();

    $rows = exportedRows();
    $header = $rows[0];
    $archivedRow = collect($rows)->firstWhere(0, $archived->reference_number);

    expect($rows)->toHaveCount(3)
        ->and($archivedRow[array_search('License (last 4)', $header, true)])->toBe('5678')
        ->and($archivedRow[array_search('Locale', $header, true)])->toBe('es')
        ->and($archivedRow[array_search('Archived at', $header, true)])->not->toBeEmpty()
        ->and(collect($rows)->flatten()->contains('D1234-5678'))->toBeFalse();
});

it('emails the spreadsheet to the requester and confirms in the bell', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();
    CheckIn::factory()->count(2)->create();

    $this->actingAs($user)
        ->postJson(route('dashboard.checkIns.exports.store'), ['scope' => 'all'])
        ->assertAccepted();

    $email = sentExportEmail();
    $notification = $user->notifications()->sole();

    expect($email->getTo()[0]->getAddress())->toBe($user->email)
        ->and($email->getAttachments()[0]->getFilename())->toBe($notification->data['filename'])
        ->and($email->getAttachments()[0]->getContentType())->toBe(CheckInExportMail::MIME)
        ->and($notification->type)->toBe(CheckInExportReady::TYPE)
        ->and($notification->data['rowCount'])->toBe(2)
        ->and($notification->data['attached'])->toBeTrue()
        ->and($notification->data['filename'])->toStartWith('check-ins-master-')
        ->and(Storage::disk('local')->allFiles(CheckInExportFiles::DIRECTORY))->toBe([]);
});

it('emails without the file when it is over the attachment limit', function (): void {
    config(['mail.export_attachment_max_kb' => 0]);
    $user = User::factory()->withRole(Role::Admin)->create();

    $this->actingAs($user)
        ->postJson(route('dashboard.checkIns.exports.store'), ['scope' => 'all'])
        ->assertAccepted();

    $email = sentExportEmail();

    expect($email->getAttachments())->toBe([])
        ->and($email->getHtmlBody())->toContain('too large to email')
        ->and($user->notifications()->sole()->data['attached'])->toBeFalse()
        ->and(Storage::disk('local')->allFiles(CheckInExportFiles::DIRECTORY))->toBe([]);
});

it('tells the requester when the export fails', function (): void {
    $user = User::factory()->withRole(Role::Admin)->create();

    new ExportCheckInsJob($user, CheckInExportScope::All, [], [], (string) Str::uuid(), 'check-ins.xlsx')
        ->failed(new RuntimeException('disk full'));

    expect($user->notifications()->sole()->type)->toBe(CheckInExportFailed::TYPE);
});

it('prunes exports left behind for over a day', function (): void {
    $files = resolve(CheckInExportFiles::class);
    $old = $files->path((string) Str::uuid());
    $recent = $files->path((string) Str::uuid());
    Storage::disk('local')->put($old, 'old');
    Storage::disk('local')->put($recent, 'recent');
    touch(Storage::disk('local')->path($old), now()->subDays(CheckInExportFiles::KEEP_DAYS + 1)->getTimestamp());

    $files->prune(now());

    expect(Storage::disk('local')->exists($old))->toBeFalse()
        ->and(Storage::disk('local')->exists($recent))->toBeTrue();
});
