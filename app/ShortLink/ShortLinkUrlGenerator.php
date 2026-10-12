<?php

declare(strict_types=1);

namespace App\ShortLink;

use App\Models\ShortLink;

/**
 * Builds the public URL for a short code.
 *
 * This is the single place that answers "which host do short links use", and it
 * is deliberately the only one. Clients bring their own domain, or a link-only
 * subdomain such as go.dockin.app, and some of those are long enough that the
 * character budget in a text matters.
 *
 * Note the asymmetry with {@see \App\Http\Controllers\ShortLinkController}: only
 * generation consults configuration. The redirect route carries no domain
 * constraint, so it answers on whatever host reaches the application. Adding a
 * client domain is therefore DNS plus a Caddy host, with no code change here.
 *
 * If per-client domains ever need to live in the database rather than in the
 * environment, this class is what changes.
 */
final class ShortLinkUrlGenerator
{
    public function for(ShortLink $link): string
    {
        return $this->fromCode($link->code);
    }

    public function fromCode(string $code): string
    {
        return $this->scheme().'://'.$this->authority().$this->path($code);
    }

    private function scheme(): string
    {
        $configured = config('shortlink.scheme');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
    }

    /**
     * Host plus port. The port is kept because local development runs on an
     * explicit one and a link that drops it would not resolve.
     */
    private function authority(): string
    {
        $configured = config('shortlink.host');

        if (is_string($configured) && $configured !== '') {
            return mb_trim($configured, '/');
        }

        $appUrl = (string) config('app.url');
        $host = (string) (parse_url($appUrl, PHP_URL_HOST) ?: 'localhost');
        $port = parse_url($appUrl, PHP_URL_PORT);

        return $port === null ? $host : $host.':'.$port;
    }

    /**
     * An empty prefix is the intended setup on a link-only host, where the code
     * sits at the root and saves the two characters.
     */
    private function path(string $code): string
    {
        $prefix = mb_trim((string) config('shortlink.prefix'), '/');

        return $prefix === '' ? '/'.$code : '/'.$prefix.'/'.$code;
    }
}
