<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\ShortLink;
use App\ShortLink\ShortLinkResolver;
use Illuminate\Http\RedirectResponse;

/**
 * Expands a short code into the account history page for the record.
 *
 * The target is behind auth, and deliberately so: a texted link that opened the
 * record outright would open it for anyone the text was forwarded to. Fortify's
 * login response redirects to the intended URL, so a signed-out driver signs in
 * once and lands on the record.
 */
final class ShortLinkController extends Controller
{
    public function __construct(
        private readonly ShortLinkResolver $resolver,
    ) {}

    public function __invoke(string $code): RedirectResponse
    {
        $link = $this->resolver->resolve($code);

        abort_if(! $link instanceof ShortLink, 404);

        $this->resolver->recordVisit($link);

        $record = $link->linkable;

        $target = match (true) {
            $record instanceof CheckIn => route('account.history.checkIn', $record->uuid),
            $record instanceof Appointment => route('account.history.appointment', $record->uuid),
            default => abort(404),
        };

        return redirect()->to($target);
    }
}
