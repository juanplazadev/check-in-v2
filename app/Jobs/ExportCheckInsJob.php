<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Dashboard\CheckInExportFiles;
use App\Dashboard\CheckInSpreadsheet;
use App\Enums\CheckInDateField;
use App\Enums\CheckInExportScope;
use App\Mail\CheckInExportMail;
use App\Models\CheckIn;
use App\Models\User;
use App\Notifications\CheckInExportFailed;
use App\Notifications\CheckInExportReady;
use App\Queries\DashboardCheckIns;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Throwable;

/**
 * Builds a check-in spreadsheet for one staff member, emails it to them as an
 * attachment and confirms in their notification bell.
 *
 * Runs on its own low-priority supervisor (config/horizon.php) so a large
 * export never holds a worker that mail or PDFs need. Where the queue is not
 * Redis (tests, a sync local setup) it runs on the default connection.
 *
 * Rows are read a chunk at a time in the table's order, so memory stays flat
 * however many check-ins match. There is no shared file storage, so the file
 * lives only on this worker's disk between writing and mailing it, and is
 * deleted whether or not the mail goes out. A file over the attachment limit
 * is announced without it.
 * Location scoping is applied again here: the user's access is checked when
 * the job runs, not only when it was queued.
 */
final class ExportCheckInsJob implements ShouldQueue
{
    use Queueable;

    public const int CHUNK_SIZE = 1000;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @param  list<string>  $columns  The current view's columns in display order; unused by the master export.
     */
    public function __construct(
        public User $user,
        public CheckInExportScope $scope,
        public array $filters,
        public array $columns,
        public string $exportUuid,
        public string $filename,
    ) {
        $this->onQueue('exports');

        if (config('queue.default') === 'redis') {
            $this->onConnection('redis-exports');
        }
    }

    public function handle(DashboardCheckIns $checkIns, CheckInSpreadsheet $spreadsheet, CheckInExportFiles $files): void
    {
        $disk = $files->disk();
        $path = $files->path($this->exportUuid);
        $timezone = $this->filters['timezone'];
        $dateField = CheckInDateField::from($this->filters['dateField'] ?? CheckInDateField::Submitted->value);

        $disk->makeDirectory(dirname($path));

        try {
            $rowCount = $this->write($disk->path($path), $checkIns, $spreadsheet, $dateField, $timezone);
            $attachable = $disk->size($path) <= (int) config('mail.export_attachment_max_kb') * 1024;

            Mail::to($this->user)->send(new CheckInExportMail(
                $this->filename,
                $this->scope,
                $rowCount,
                Date::now($timezone),
                $attachable ? $disk->path($path) : null,
            ));
        } finally {
            $disk->delete($path);
        }

        $this->user->notify(new CheckInExportReady($this->filename, $rowCount, $attachable));
    }

    public function failed(?Throwable $exception): void
    {
        $this->user->notify(new CheckInExportFailed($this->filename));
    }

    /**
     * @return int The number of check-in rows written.
     */
    private function write(string $absolutePath, DashboardCheckIns $checkIns, CheckInSpreadsheet $spreadsheet, CheckInDateField $dateField, string $timezone): int
    {
        $writer = SimpleExcelWriter::create($absolutePath, 'xlsx');
        $rowCount = 0;

        try {
            $writer->addHeader($this->scope === CheckInExportScope::View
                ? $spreadsheet->viewHeaders($this->columns, $dateField)
                : $spreadsheet->masterHeaders());

            foreach ($this->query($checkIns)->lazy(self::CHUNK_SIZE) as $checkIn) {
                $writer->addRow($this->scope === CheckInExportScope::View
                    ? $spreadsheet->viewRow($checkIn, $this->columns, $dateField, $timezone)
                    : $spreadsheet->masterRow($checkIn, $timezone));
                $rowCount++;
            }
        } finally {
            $writer->close();
        }

        return $rowCount;
    }

    /**
     * The current view is the table's own query. The master export ignores the
     * toolbar: every check-in the user may see, archived ones included, newest
     * first.
     *
     * @return Builder<CheckIn>
     */
    private function query(DashboardCheckIns $checkIns): Builder
    {
        if ($this->scope === CheckInExportScope::View) {
            return $checkIns->query($this->user, $this->filters);
        }

        return CheckIn::query()
            ->withTrashed()
            ->visibleTo($this->user)
            ->with(CheckInSpreadsheet::RELATIONS)
            ->latest()
            ->orderByDesc('id');
    }
}
