<?php

declare(strict_types=1);

use App\ShortLink\ShortLinkUrlGenerator;
use Illuminate\Support\Facades\Config;

function shortUrlFor(string $code = 'QRSTUVW'): string
{
    return resolve(ShortLinkUrlGenerator::class)->fromCode($code);
}

it('falls back to the application host when no link host is configured', function (): void {
    Config::set('app.url', 'https://martori.localhost');
    Config::set('shortlink.host');
    Config::set('shortlink.prefix', 'r');
    Config::set('shortlink.scheme');

    expect(shortUrlFor())->toBe('https://martori.localhost/r/QRSTUVW');
});

it('keeps the port, so a local link still resolves', function (): void {
    Config::set('app.url', 'https://martori.localhost:8443');
    Config::set('shortlink.host');

    expect(shortUrlFor())->toBe('https://martori.localhost:8443/r/QRSTUVW');
});

/*
 * The point of the whole exercise: a client brings their own domain, and the
 * only thing that has to change is configuration.
 */
it('builds links on a client domain without touching anything else', function (): void {
    Config::set('shortlink.host', 'go.dockin.app');
    Config::set('shortlink.prefix', 'r');

    expect(shortUrlFor())->toBe('https://go.dockin.app/r/QRSTUVW');
});

it('drops the prefix on a link-only host, saving two characters', function (): void {
    Config::set('shortlink.host', 'go.dockin.app');
    Config::set('shortlink.prefix', '');

    expect(shortUrlFor())->toBe('https://go.dockin.app/QRSTUVW');
});

it('tolerates a host written with stray slashes', function (): void {
    Config::set('shortlink.host', 'go.dockin.app/');
    Config::set('shortlink.prefix', '/r/');

    expect(shortUrlFor())->toBe('https://go.dockin.app/r/QRSTUVW');
});

it('follows the application scheme unless one is set', function (): void {
    Config::set('app.url', 'http://martori.localhost');
    Config::set('shortlink.host');
    Config::set('shortlink.scheme');

    expect(shortUrlFor())->toStartWith('http://');

    Config::set('shortlink.scheme', 'https');

    expect(shortUrlFor())->toStartWith('https://');
});

/*
 * The budget this whole change exists to protect. A GSM-7 segment is 160
 * characters and the old link was about 87 on its own.
 */
it('stays short enough to matter, even on a long client domain', function (): void {
    Config::set('shortlink.host', 'go.a-rather-long-distribution-center.com');
    Config::set('shortlink.prefix', '');

    expect(mb_strlen(shortUrlFor()))->toBeLessThan(60);
});
