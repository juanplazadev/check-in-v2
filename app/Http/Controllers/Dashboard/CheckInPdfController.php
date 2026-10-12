<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\User;
use App\Pdf\RecordPdfDocumentFactory;
use App\Pdf\RecordPdfStore;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

/**
 * Serves a check-in's PDF inline, for the detail page's View and Print
 * actions. Print loads this same response into a hidden frame, so either
 * permission opens it. The lookup matches the detail page: a check-in at a
 * location the user may not see is a 404, and archived ones still open.
 */
final class CheckInPdfController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        string $uuid,
        RecordPdfDocumentFactory $documents,
        RecordPdfStore $store,
    ): Response {
        $checkIn = CheckIn::query()
            ->withTrashed()
            ->visibleTo($user)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $document = $documents->forCheckIn($checkIn);
        $bytes = $store->bytes($document);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $document->fileName),
            'Content-Length' => (string) mb_strlen($bytes, '8bit'),
        ]);
    }
}
