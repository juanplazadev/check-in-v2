<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Pdf\FakeRecordPdfRenderer;
use App\Pdf\RecordPdfRenderer;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

function pdfDriver(string $email = 'driver@example.com'): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => $email,
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);
}

function pdfLocation(): Location
{
    return Location::factory()->create([
        'timezone' => 'America/Phoenix',
        'abbreviation' => 'agu',
        'phone' => '(480) 998-1444',
        'email' => 'aguila@martorifarms.com',
    ]);
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('redirects guests away from the pdf routes', function (): void {
    $checkIn = CheckIn::factory()->create();

    get(route('account.history.checkIn.pdf', $checkIn))->assertRedirect(route('login'));
});

it('serves the check-in document inline with a readable filename', function (): void {
    $driver = pdfDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(pdfLocation())->completed()->create();

    $response = $this->actingAs($driver)->get(route('account.history.checkIn.pdf', $checkIn));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    // Inline rather than attachment is the whole point: an attachment vanishes
    // into the phone's Downloads folder.
    expect($response->headers->get('content-disposition'))
        ->toBe(sprintf('inline; filename="check-in-agu-%s.pdf"', $checkIn->reference_number));
});

it('serves the appointment document inline', function (): void {
    $driver = pdfDriver();
    $appointment = Appointment::factory()->forUser($driver)->atLocation(pdfLocation())->create();

    $response = $this->actingAs($driver)->get(route('account.history.appointment.pdf', $appointment));

    $response->assertSuccessful();

    expect($response->headers->get('content-disposition'))
        ->toContain('inline; filename="appointment-agu-');
});

it('renders the reference, location and purchase orders into the document', function (): void {
    $driver = pdfDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(pdfLocation())->create([
        'customer' => 'Acme Produce',
    ]);
    $checkIn->purchaseOrders()->save(new PurchaseOrder(['number' => 'PO-12345']));

    $content = $this->actingAs($driver)
        ->get(route('account.history.checkIn.pdf', $checkIn))
        ->getContent();

    expect($content)->toContain($checkIn->reference_number)
        ->toContain('Acme Produce')
        ->toContain('PO-12345')
        // The location contact block, which had never reached the payload.
        ->toContain('aguila@martorifarms.com');
});

it('never renders the full licence number into the document', function (): void {
    $driver = pdfDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(pdfLocation())->create([
        'drivers_license_number' => 'D1234567',
    ]);

    $content = $this->actingAs($driver)
        ->get(route('account.history.checkIn.pdf', $checkIn))
        ->getContent();

    // A PDF is downloadable and forwardable, so masking matters more here than
    // it does on screen.
    expect($content)->not->toContain('D1234567')
        ->and($content)->toContain('4567');
});

it('embeds a scannable barcode of the reference number', function (): void {
    $driver = pdfDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(pdfLocation())->create();

    $content = $this->actingAs($driver)
        ->get(route('account.history.checkIn.pdf', $checkIn))
        ->getContent();

    expect($content)->toContain('<svg')
        ->toContain(sprintf('aria-label="%s"', $checkIn->reference_number));
});

it('returns 404 for a record belonging to someone else', function (): void {
    $driver = pdfDriver();
    $other = pdfDriver('other@example.com');
    $location = pdfLocation();

    $checkIn = CheckIn::factory()->forUser($other)->atLocation($location)->create();
    $appointment = Appointment::factory()->forUser($other)->atLocation($location)->create();

    $this->actingAs($driver)->get(route('account.history.checkIn.pdf', $checkIn))->assertNotFound();
    $this->actingAs($driver)->get(route('account.history.appointment.pdf', $appointment))->assertNotFound();
});

it('renders once and serves the cached bytes thereafter', function (): void {
    $driver = pdfDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(pdfLocation())->create();

    /** @var FakeRecordPdfRenderer $renderer */
    $renderer = resolve(RecordPdfRenderer::class);

    $this->actingAs($driver)->get(route('account.history.checkIn.pdf', $checkIn))->assertSuccessful();
    $this->actingAs($driver)->get(route('account.history.checkIn.pdf', $checkIn))->assertSuccessful();

    expect($renderer->rendered)->toHaveCount(1);
});

it('re-renders when the record changes', function (): void {
    $driver = pdfDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(pdfLocation())->create();

    /** @var FakeRecordPdfRenderer $renderer */
    $renderer = resolve(RecordPdfRenderer::class);

    $this->actingAs($driver)->get(route('account.history.checkIn.pdf', $checkIn))->assertSuccessful();

    // updated_at is part of the cache key, so a cached copy can never go stale.
    $checkIn->forceFill(['updated_at' => now()->addMinute()])->save();

    $this->actingAs($driver)->get(route('account.history.checkIn.pdf', $checkIn))->assertSuccessful();

    expect($renderer->rendered)->toHaveCount(2);
});
