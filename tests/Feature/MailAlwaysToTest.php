<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Re-run the provider the way a real boot would, once the environment and
 * config for the case under test are in place.
 */
function bootMailRedirect(): void
{
    app()->register(AppServiceProvider::class, force: true);
}

function sendProbe(): Email
{
    Mail::raw('probe', function (Message $message): void {
        $message->to('ci-pompano@martorifarms.com')
            ->cc('shipping@martorifarms.com')
            ->subject('probe');
    });

    $message = Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();

    expect($message)->toBeInstanceOf(Email::class);

    return $message;
}

/**
 * @return list<string>
 */
function recipientsOf(Email $email): array
{
    return array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo());
}

it('redirects every recipient to the always_to inbox outside production', function (): void {
    config()->set('mail.always_to', 'staging-inbox@example.com');

    bootMailRedirect();

    $email = sendProbe();

    expect(recipientsOf($email))->toBe(['staging-inbox@example.com'])
        ->and($email->getCc())->toBe([]);
});

it('redirects in staging', function (): void {
    config()->set('mail.always_to', 'staging-inbox@example.com');
    app()->detectEnvironment(fn (): string => 'staging');

    bootMailRedirect();

    expect(recipientsOf(sendProbe()))->toBe(['staging-inbox@example.com']);
});

it('leaves recipients alone when no always_to address is configured', function (): void {
    // Set explicitly rather than leaning on the ambient environment: .env.example
    // ships `MAIL_ALWAYS_TO=`, so CI resolves the config to '' where a local .env
    // without the key at all resolves it to null.
    config(['mail.always_to' => null]);

    bootMailRedirect();

    expect(recipientsOf(sendProbe()))->toBe(['ci-pompano@martorifarms.com']);
});

it('ignores an empty always_to address', function (): void {
    config()->set('mail.always_to', '');

    bootMailRedirect();

    expect(recipientsOf(sendProbe()))->toBe(['ci-pompano@martorifarms.com']);
});

it('never redirects in production, so a stray value cannot divert customer mail', function (): void {
    config()->set('mail.always_to', 'staging-inbox@example.com');
    app()->detectEnvironment(fn (): string => 'production');

    bootMailRedirect();

    expect(recipientsOf(sendProbe()))->toBe(['ci-pompano@martorifarms.com']);
});
