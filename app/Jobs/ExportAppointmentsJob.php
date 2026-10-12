<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Dashboard\AppointmentSpreadsheet;
use App\Dashboard\ExportFiles;
use App\Enums\AppointmentDateField;
use App\Enums\ExportScope;
use App\Enums\ExportSubject;
use App\Mail\ExportMail;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\ExportFailed;
use App\Notifications\ExportReady;
use App\Queries\DashboardAppointments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Throwable;

/**
 * Builds an appointment spreadsheet for one staff member, emails it to them as
 * an attachment and confirms in their notification bell. Runs exactly like
 * {@see ExportCheckInsJob}: on the low-priority exports supervisor, a chunk of
 * rows at a time, with the file deleted once mailed and location scoping
 * applied again when the job runs.
 */
#[Timeout(600)]
#[Tries(1)]
final class ExportAppointmentsJob implements ShouldQueue
{
    use Queueable;

    public const int CHUNK_SIZE = 1000;

    /**
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @param  list<string>  $columns  The current view's columns in display order; unused by the master export.
     */
    public function __construct(
        public User $user,
        public ExportScope $scope,
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

    public function handle(DashboardAppointments $appointments, AppointmentSpreadsheet $spreadsheet, ExportFiles $files): void
    {
        $disk = $files->disk();
        $path = $files->path($this->exportUuid);
        $timezone = $this->filters['timezone'];
        $dateField = AppointmentDateField::from($this->filters['dateField'] ?? AppointmentDateField::Scheduled->value);

        $disk->makeDirectory(dirname($path));

        try {
            $rowCount = $this->write($disk->path($path), $appointments, $spreadsheet, $dateField, $timezone);
            $attachable = $disk->size($path) <= (int) config('mail.export_attachment_max_kb') * 1024;

            Mail::to($this->user)->send(new ExportMail(
                ExportSubject::Appointments,
                $this->filename,
                $this->scope,
                $rowCount,
                Date::now($timezone),
                $attachable ? $disk->path($path) : null,
            ));
        } finally {
            $disk->delete($path);
        }

        $this->user->notify(new ExportReady(ExportSubject::Appointments, $this->filename, $rowCount, $attachable));
    }

    public function failed(?Throwable $exception): void
    {
        $this->user->notify(new ExportFailed(ExportSubject::Appointments, $this->filename));
    }

    /**
     * @return int The number of appointment rows written.
     */
    private function write(string $absolutePath, DashboardAppointments $appointments, AppointmentSpreadsheet $spreadsheet, AppointmentDateField $dateField, string $timezone): int
    {
        $writer = SimpleExcelWriter::create($absolutePath, 'xlsx');
        $rowCount = 0;

        try {
            $writer->addHeader($this->scope === ExportScope::View
                ? $spreadsheet->viewHeaders($this->columns, $dateField)
                : $spreadsheet->masterHeaders());

            foreach ($this->query($appointments)->lazy(self::CHUNK_SIZE) as $appointment) {
                $writer->addRow($this->scope === ExportScope::View
                    ? $spreadsheet->viewRow($appointment, $this->columns, $dateField, $timezone)
                    : $spreadsheet->masterRow($appointment, $timezone));
                $rowCount++;
            }
        } finally {
            $writer->close();
        }

        return $rowCount;
    }

    /**
     * The current view is the table's own query. The master export ignores the
     * toolbar: every appointment the user may see, archived ones included,
     * latest slot first like the table.
     *
     * @return Builder<Appointment>
     */
    private function query(DashboardAppointments $appointments): Builder
    {
        if ($this->scope === ExportScope::View) {
            return $appointments->query($this->user, $this->filters);
        }

        return Appointment::query()
            ->withTrashed()
            ->visibleTo($this->user)
            ->with(AppointmentSpreadsheet::RELATIONS)
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id');
    }
}
