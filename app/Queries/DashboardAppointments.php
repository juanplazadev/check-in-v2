<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\AppointmentRecordResolver;
use App\Dashboard\PhoneSearchTerm;
use App\Enums\AppointmentColumn;
use App\Enums\AppointmentDateField;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The staff appointments table: every appointment the user may see, latest slot first,
 * filtered on the server so the page never ships more than one page of rows. Built the
 * same way as {@see DashboardCheckIns}.
 *
 * A search term that is a full UUID also matches the appointment by uuid, and a term that
 * reads as a phone number matches the driver's cellphone on its digits.
 *
 * Archived is a pseudo-status: it lists soft-deleted appointments of any status. Every
 * other filter leaves them out.
 *
 * Locations are filtered by id only, never by whether they take appointments today, so a
 * location that has since turned bookings off still lists the ones it took.
 */
final readonly class DashboardAppointments
{
    /** @var list<int> */
    public const array PER_PAGE_OPTIONS = DashboardCheckIns::PER_PAGE_OPTIONS;

    public const int DEFAULT_PER_PAGE = DashboardCheckIns::DEFAULT_PER_PAGE;

    public const string ARCHIVED = 'archived';

    public const int MIN_SEARCH_LENGTH = DashboardCheckIns::MIN_SEARCH_LENGTH;

    /**
     * The {@see AppointmentColumn} values the table may sort on.
     *
     * @var list<string>
     */
    public const array SORTABLE = [
        'reference',
        'date',
        'driver',
        'cellphone',
        'location',
        'status',
    ];

    /**
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @param  int  $total  The active tab's count from statusCounts(), so the page skips its own count query.
     * @return LengthAwarePaginator<int, Appointment>
     */
    public function execute(User $user, array $filters, int $total): LengthAwarePaginator
    {
        return $this->query($user, $filters)
            ->paginate($filters['perPage'], total: $total)
            ->withQueryString();
    }

    /**
     * Every row the table would show across all its pages, in the same order.
     *
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @return Builder<Appointment>
     */
    public function query(User $user, array $filters): Builder
    {
        return $this->filtered($user, $filters)
            ->when($filters['status'], fn (Builder $query, string $status): Builder => $status === self::ARCHIVED
                ? $query->onlyTrashed()
                : $query->where('status', $status))
            ->with(AppointmentRecordResolver::RELATIONS)
            ->tap(fn (Builder $query): Builder => $this->sorted($query, $filters))
            ->orderByDesc('id');
    }

    /**
     * Per-status totals for the tabs. Search, locations and dates apply, status does not,
     * so each tab shows what clicking it would find. `all` leaves out the archived
     * appointments, which only the archived tab counts.
     *
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @return array<string, int>
     */
    public function statusCounts(User $user, array $filters): array
    {
        $rows = $this->filtered($user, $filters)
            ->withTrashed()
            ->toBase()
            ->selectRaw('status, deleted_at is not null as archived, count(*) as aggregate')
            ->groupByRaw('status, deleted_at is not null')
            ->get();

        $byStatus = array_fill_keys(array_column(AppointmentStatus::cases(), 'value'), 0);
        $archived = 0;

        foreach ($rows as $row) {
            if ((bool) $row->archived) {
                $archived += (int) $row->aggregate;
            } elseif (array_key_exists((string) $row->status, $byStatus)) {
                $byStatus[$row->status] = (int) $row->aggregate;
            }
        }

        return [
            'all' => array_sum($byStatus),
            ...$byStatus,
            self::ARCHIVED => $archived,
        ];
    }

    /**
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @return Builder<Appointment>
     */
    private function filtered(User $user, array $filters): Builder
    {
        return Appointment::query()
            ->visibleTo($user)
            ->when($filters['locations'], fn (Builder $query, array $locations): Builder => $query->whereIn('location_id', $locations))
            ->when($filters['search'], fn (Builder $query, string $search): Builder => $query->whereIn('id', $this->searchMatches($search)))
            ->when($filters['dateField'], fn (Builder $query, string $dateField): Builder => $this->withinDays(
                $query,
                AppointmentDateField::from($dateField),
                (string) $filters['from'],
                (string) $filters['to'],
                $filters['timezone'],
            ));
    }

    /**
     * The chosen column sort, or latest slot first. Column names come from the match below
     * and the direction from the request's allowlist, so neither reaches the raw order
     * clause from user input. The date column sorts on whichever date the table is showing.
     *
     * @param  Builder<Appointment>  $query
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @return Builder<Appointment>
     */
    private function sorted(Builder $query, array $filters): Builder
    {
        if ($filters['sort'] === null) {
            return $query->orderByDesc('scheduled_for');
        }

        $direction = $filters['direction'] === 'desc' ? 'desc' : 'asc';
        $sort = AppointmentColumn::from($filters['sort']);

        if ($sort === AppointmentColumn::Location) {
            return $query->orderBy(
                Location::query()->select('name')->whereColumn('locations.id', 'appointments.location_id'),
                $direction,
            );
        }

        $column = match ($sort) {
            AppointmentColumn::Reference => 'reference_number',
            AppointmentColumn::Driver => 'drivers_name',
            AppointmentColumn::Cellphone => 'drivers_cellphone',
            AppointmentColumn::Status => 'status',
            AppointmentColumn::Date => AppointmentDateField::from($filters['dateField'] ?? AppointmentDateField::Scheduled->value)->column(),
            default => 'scheduled_for',
        };

        return $query->orderByRaw("\"{$column}\" {$direction} nulls last");
    }

    /**
     * Ids of the appointments a search term matches, archived ones included (the outer
     * query decides which of those to show). A union rather than an OR, so each branch
     * can use its own trigram index; see {@see DashboardCheckIns}.
     *
     * @return Builder<Appointment>
     */
    private function searchMatches(string $search): Builder
    {
        $purchaseOrders = PurchaseOrder::query()
            ->select('purchasable_id')
            ->where('purchasable_type', new Appointment()->getMorphClass())
            ->whereContains('number', $search);

        return Appointment::query()
            ->withTrashed()
            ->select('id')
            ->where(fn (Builder $query): Builder => $query
                ->whereContains('reference_number', $search)
                ->orWhereContains('drivers_name', $search)
                ->when(PhoneSearchTerm::digits($search, self::MIN_SEARCH_LENGTH), fn (Builder $query, string $digits): Builder => $query->orWhereContains('drivers_cellphone', $digits))
                ->when(Str::isUuid($search) ? Str::lower($search) : null, fn (Builder $query, string $uuid): Builder => $query->orWhere('uuid', $uuid)))
            ->union($purchaseOrders);
    }

    /**
     * Whole calendar days in the viewer's timezone, as a half-open UTC range so the last
     * day counts up to its final second. Scheduled ranges may lie in the future.
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    private function withinDays(Builder $query, AppointmentDateField $field, string $from, string $to, string $timezone): Builder
    {
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $end = CarbonImmutable::parse($to, $timezone)->startOfDay();

        return $query
            ->where($field->column(), '>=', $start->utc())
            ->where($field->column(), '<', $end->addDay()->utc());
    }
}
