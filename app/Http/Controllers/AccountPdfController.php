<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Account\HistoryRecordFinder;
use App\Http\Requests\HistoryRecordRequest;
use App\Mail\RecordDocumentMail;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\User;
use App\Pdf\RecordPdfDocument;
use App\Pdf\RecordPdfDocumentFactory;
use App\Pdf\RecordPdfStore;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Serves a driver's check-in or booking as a PDF, inline or by email.
 *
 * Split out of {@see AccountController} because it is the only part of the
 * account surface that touches App\Pdf and RecordDocumentMail at all: the whole
 * document-rendering dependency graph lives here and nowhere else in the HTTP
 * layer. Records are resolved through {@see HistoryRecordFinder}, so a document
 * belonging to another driver 404s rather than 403s.
 */
final class AccountPdfController extends Controller
{
    public function checkIn(
        HistoryRecordRequest $request,
        #[CurrentUser] User $user,
        HistoryRecordFinder $finder,
        RecordPdfDocumentFactory $documents,
        RecordPdfStore $store,
    ): HttpResponse {
        $document = $documents->forCheckIn($finder->checkIn($user, $request->uuid()));

        return $this->inlinePdf($document, $store->bytes($document));
    }

    public function appointment(
        HistoryRecordRequest $request,
        #[CurrentUser] User $user,
        HistoryRecordFinder $finder,
        RecordPdfDocumentFactory $documents,
        RecordPdfStore $store,
    ): HttpResponse {
        $document = $documents->forAppointment($finder->appointment($user, $request->uuid()));

        return $this->inlinePdf($document, $store->bytes($document));
    }

    public function emailCheckIn(
        HistoryRecordRequest $request,
        #[CurrentUser] User $user,
        HistoryRecordFinder $finder,
    ): RedirectResponse {
        return $this->queueDocument($user, $finder->checkIn($user, $request->uuid()));
    }

    public function emailAppointment(
        HistoryRecordRequest $request,
        #[CurrentUser] User $user,
        HistoryRecordFinder $finder,
    ): RedirectResponse {
        return $this->queueDocument($user, $finder->appointment($user, $request->uuid()));
    }

    /**
     * Sent only to the driver's own address, never one supplied by the request.
     * The locale is captured now so the worker renders the language the driver
     * was actually using, matching how the verification email already works.
     */
    private function queueDocument(User $user, CheckIn|Appointment $record): RedirectResponse
    {
        Mail::to($user->email)->queue(
            new RecordDocumentMail($record)
                ->onQueue('pdf')
                ->locale(app()->getLocale()),
        );

        // The page confirms via useForm's recentlySuccessful, the pattern the
        // profile page already uses; there is no flash plumbing in this app.
        return back();
    }

    /**
     * Inline, not attachment. An attachment disappears into the phone's
     * Downloads folder; inline opens the document in the built-in viewer, where
     * the share sheet still offers Save to Files, Print and Mail.
     */
    private function inlinePdf(RecordPdfDocument $document, string $bytes): HttpResponse
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $document->fileName),
            'Content-Length' => (string) mb_strlen($bytes, '8bit'),
        ]);
    }
}
