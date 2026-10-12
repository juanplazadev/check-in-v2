<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Mail\RecordDocumentMail;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\post;

function mailDriver(string $email = 'driver@example.com'): User
{
    return User::query()->create([
        'name' => 'John Driver',
        'email' => $email,
        'password' => 'secret-password',
        'email_verified_at' => now(),
    ])->assignRole(Role::Driver);
}

function mailLocation(): Location
{
    return Location::factory()->create(['abbreviation' => 'agu']);
}

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
});

it('redirects guests away from the email routes', function (): void {
    $checkIn = CheckIn::factory()->create();

    post(route('account.history.checkIn.email', $checkIn))->assertRedirect(route('login'));
});

it('queues the document to the driver on the pdf queue', function (): void {
    $driver = mailDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(mailLocation())->create();

    $this->actingAs($driver)
        ->post(route('account.history.checkIn.email', $checkIn))
        ->assertRedirect();

    // assertQueued, not assertSent: the mailable is ShouldQueue.
    Mail::assertQueued(
        RecordDocumentMail::class,
        fn (RecordDocumentMail $mail): bool => $mail->hasTo($driver->email)
            && $mail->queue === 'pdf'
    );
});

it('queues an appointment document too', function (): void {
    $driver = mailDriver();
    $appointment = Appointment::factory()->forUser($driver)->atLocation(mailLocation())->create();

    $this->actingAs($driver)
        ->post(route('account.history.appointment.email', $appointment))
        ->assertRedirect();

    Mail::assertQueued(RecordDocumentMail::class);
});

it('never sends to any address but the authenticated driver', function (): void {
    $driver = mailDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(mailLocation())->create([
        // A different address on the record must not be used as a recipient.
        'drivers_email' => 'someone-else@example.com',
    ]);

    $this->actingAs($driver)->post(route('account.history.checkIn.email', $checkIn));

    Mail::assertQueued(RecordDocumentMail::class, fn (RecordDocumentMail $mail): bool => count($mail->to) === 1
        && $mail->to[0]['address'] === $driver->email
        && $mail->cc === []
        && $mail->bcc === []);
});

it('queues nothing for a record belonging to someone else', function (): void {
    $driver = mailDriver();
    $other = mailDriver('other@example.com');
    $checkIn = CheckIn::factory()->forUser($other)->atLocation(mailLocation())->create();

    $this->actingAs($driver)
        ->post(route('account.history.checkIn.email', $checkIn))
        ->assertNotFound();

    Mail::assertNothingQueued();
});

it('rate limits repeated requests', function (): void {
    $driver = mailDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(mailLocation())->create();

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($driver)
            ->post(route('account.history.checkIn.email', $checkIn))
            ->assertRedirect();
    }

    $this->actingAs($driver)
        ->post(route('account.history.checkIn.email', $checkIn))
        ->assertStatus(429);
});

it('attaches the rendered pdf with a readable filename', function (): void {
    $driver = mailDriver();
    $checkIn = CheckIn::factory()->forUser($driver)->atLocation(mailLocation())->create();

    $this->actingAs($driver)->post(route('account.history.checkIn.email', $checkIn));

    Mail::assertQueued(RecordDocumentMail::class, function (RecordDocumentMail $mail) use ($checkIn): bool {
        $attachments = $mail->attachments();

        return count($attachments) === 1
            && $attachments[0]->as === sprintf('check-in-agu-%s.pdf', $checkIn->reference_number)
            && $attachments[0]->mime === 'application/pdf';
    });
});
