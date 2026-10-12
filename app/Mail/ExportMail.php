<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\ExportScope;
use App\Enums\ExportSubject;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/**
 * A finished dashboard spreadsheet, mailed to the staff member who asked for
 * it. There is no shared file storage to download from, so the file travels
 * as an attachment.
 *
 * Never queued: the export job ({@see \App\Jobs\ExportCheckInsJob},
 * {@see \App\Jobs\ExportAppointmentsJob}) sends it while the file still exists
 * and deletes the file straight after. A file over the attachment limit is
 * left off, and the mail says so.
 */
final class ExportMail extends Mailable
{
    public const string MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  string|null  $path  Absolute path of the file, or null when it was too large to attach.
     */
    public function __construct(
        public ExportSubject $exportSubject,
        public string $filename,
        public ExportScope $scope,
        public int $rowCount,
        public CarbonInterface $generatedAt,
        public ?string $path,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: Str::ucfirst($this->exportSubject->singular()).' export - '.$this->filename);
    }

    public function content(): Content
    {
        $singular = $this->exportSubject->singular();

        return new Content(
            view: 'mail.notification',
            with: [
                'preheader' => $this->path === null
                    ? "Your {$singular} export was too large to email"
                    : "Your {$singular} export is attached",
                'eyebrow' => Str::title($singular).' Export',
                'title' => $this->path === null
                    ? 'Your export was too large to email'
                    : 'Your export is ready',
                'intro' => $this->path === null
                    ? 'The spreadsheet is over the email size limit, so it is not attached. Export the current view with narrower filters, such as a date range, to get a smaller file.'
                    : 'The spreadsheet you asked for is attached.',
                'referenceLabel' => 'File',
                'referenceNumber' => $this->filename,
                'rows' => [
                    ['label' => 'Export', 'value' => $this->scope === ExportScope::All ? 'All '.$this->exportSubject->plural() : 'Current view'],
                    ['label' => 'Rows', 'value' => number_format($this->rowCount)],
                    ['label' => 'Generated', 'value' => $this->generatedAt->format('M j, Y g:i A T')],
                ],
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->path === null) {
            return [];
        }

        $path = $this->path;

        // Read into the message as it is built, not lazily from the path,
        // since the job deletes the file as soon as the mail is sent.
        return [
            Attachment::fromData(fn (): string => (string) file_get_contents($path), $this->filename)->withMime(self::MIME),
        ];
    }
}
