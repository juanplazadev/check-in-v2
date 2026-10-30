<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;

/**
 * Start from a plain http request, as Octane sees it behind the TLS proxy, so
 * only the provider can turn it into https.
 */
beforeEach(function (): void {
    url()->setRequest(Request::create('http://check-in.test'));
});

it('forces https urls behind the production and staging proxies', function (string $environment): void {
    app()->detectEnvironment(fn (): string => $environment);

    app()->register(AppServiceProvider::class, force: true);

    expect(url('/'))->toBe('https://check-in.test');
})->with(['production', 'staging']);

it('leaves the url scheme alone locally', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    app()->register(AppServiceProvider::class, force: true);

    expect(url('/'))->toBe('http://check-in.test');
});
