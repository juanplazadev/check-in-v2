<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\CheckInRecordResolver;
use App\Enums\CheckInColumn;
use App\Enums\CheckInDateField;
use App\Enums\CheckInStatus;
use App\Models\CheckIn;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The staff check-ins table: every check-in the user may see, newest first, filtered on the
 * server so the page never ships more than one page of rows.
 *
 * A search term that is a full UUID also matches the check-in by uuid. It is an exact match
 * on the unique index, checked with Str::isUuid() first, since Postgres errors on comparing
 * a uuid column to anything else.
 *
 * Archived is a pseudo-status: it lists soft-deleted check-ins of any status. Every other
 * filter leaves them out.
 *
 * Built to stay cheap at hundreds of thousands of rows: one counts query and one page query
 * per visit, both served by the partial indexes in the check_ins migration. The page reuses
 * the tab count as its total instead of counting again, and search runs as a union of id
 * lookups so each branch can use its trigram index.
 *
 * The default newest-first order walks those indexes. A column sort the user picks is a top-N
 * sort over the filtered rows instead, with nulls last either way and the id as tie-breaker
 * so paging stays stable.
 */
final readonly class DashboardCheckIns
{
    /** @var list<int> */
    public const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public const int DEFAULT_PER_PAGE = 25;

    public const string ARCHIVED = 'archived';

    /**
     * Shorter terms are ignored, not searched: the trigram indexes need three
     * characters, so anything less would scan every row.
     */
    public const int MIN_SEARCH_LENGTH = 3;

    /**
     * The {@see CheckInColumn} values the table may sort on. The rest show
     * several fields in one cell or come from a relation that is costly to
     * order by.
     *
     * @var list<string>
     */
    public const array SORTABLE = [
        'reference',
        'driver',
        'customer',
        'location',
        'erpStatus',
        'status',
        'date',
        'cellphone',
        'truckName',
        'trailerChute',
        'destination',
        'truckColor',
        'emptyWeight',
        'licenseExpiration',
    ];

    /**
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @param  int  $total  The active tab's count from statusCounts(), so the page skips its own count query.
     * @return LengthAwarePaginator<int, CheckIn>
     */
    public function execute(User $user, array $filters, int $total): LengthAwarePaginator
    {
        return $this->filtered($user, $filters)
            ->when($filters['status'], fn (Builder $query, string $status): Builder => $status === self::ARCHIVED
                ? $query->onlyTrashed()
                : $query->where('status', $status))
            ->with(CheckInRecordResolver::RELATIONS)
            ->tap(fn (Builder $query): Builder => $this->sorted($query, $filters))
            ->orderByDesc('id')
            ->paginate($filters['perPage'], total: $total)
            ->withQueryString();
    }

    /**
     * Per-status totals for the tabs. Search and locations apply, status does
     * not, so each tab shows what clicking it would find. `all` leaves out the
     * archived check-ins, which only the archived tab counts. One grouped query
     * covers both, split on whether the row is soft-deleted.
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

        $byStatus = array_fill_keys(array_column(CheckInStatus::cases(), 'value'), 0);
        $archived = 0;

        foreach ($rows as $row) {
            if ((bool) $row->archived) {
                $archived += (int) $row->aggregate;
            } elseif (array_key_exists($row->status, $byStatus)) {
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
     * @return Builder<CheckIn>
     */
    private function filtered(User $user, array $filters): Builder
    {
        return CheckIn::query()
            ->visibleTo($user)
            ->when($filters['locations'], fn (Builder $query, array $locations): Builder => $query->whereIn('location_id', $locations))
            ->when($filters['search'], fn (Builder $query, string $search): Builder => $query->whereIn('id', $this->searchMatches($search)))
            ->when($filters['dateField'], fn (Builder $query, string $dateField): Builder => $this->withinDays(
                $query,
                CheckInDateField::from($dateField),
                (string) $filters['from'],
                (string) $filters['to'],
                $filters['timezone'],
            ));
    }

    /**
     * The chosen column sort, or newest first. Column names come from the
     * match below and the direction from the request's allowlist, so neither
     * reaches the raw order clause from user input. The date column sorts on
     * whichever date the table is showing.
     *
     * @param  Builder<CheckIn>  $query
     * @param  array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}  $filters
     * @return Builder<CheckIn>
     */
    private function sorted(Builder $query, array $filters): Builder
    {
        if ($filters['sort'] === null) {
            return $query->latest();
        }

        $direction = $filters['direction'] === 'desc' ? 'desc' : 'asc';
        $sort = CheckInColumn::from($filters['sort']);

        if ($sort === CheckInColumn::Location) {
            return $query->orderBy(
                Location::query()->select('name')->whereColumn('locations.id', 'check_ins.location_id'),
                $direction,
            );
        }

        $column = match ($sort) {
            CheckInColumn::Reference => 'reference_number',
            CheckInColumn::Driver => 'drivers_name',
            CheckInColumn::Customer => 'customer',
            CheckInColumn::ErpStatus => 'erp_status',
            CheckInColumn::Status => 'status',
            CheckInColumn::Date => CheckInDateField::from($filters['dateField'] ?? CheckInDateField::Submitted->value)->column(),
            CheckInColumn::Cellphone => 'drivers_cellphone',
            CheckInColumn::TruckName => 'truck_name',
            CheckInColumn::TrailerChute => 'trailer_chute',
            CheckInColumn::Destination => 'destination_city',
            CheckInColumn::TruckColor => 'truck_color',
            CheckInColumn::EmptyWeight => 'empty_weight_lbs',
            CheckInColumn::LicenseExpiration => 'drivers_license_expiration_date',
            default => 'created_at',
        };

        return $query->orderByRaw("\"{$column}\" {$direction} nulls last");
    }

    /**
     * Ids of the check-ins a search term matches, archived ones included (the outer query
     * decides which of those to show).
     *
     * Kept as a union rather than an OR with whereHas(): an OR across a correlated subquery
     * forces Postgres into a sequential scan, while each union branch can use the trigram
     * indexes on its own columns.
     *
     * @return Builder<CheckIn>
     */
    private function searchMatches(string $search): Builder
    {
        $purchaseOrders = PurchaseOrder::query()
            ->select('purchasable_id')
            ->where('purchasable_type', new CheckIn()->getMorphClass())
            ->whereContains('number', $search);

        return CheckIn::query()
            ->withTrashed()
            ->select('id')
            ->where(fn (Builder $query): Builder => $query
                ->whereContains('reference_number', $search)
                ->orWhereContains('drivers_name', $search)
                ->orWhereContains('customer', $search)
                ->orWhereContains('truck_plate', $search)
                ->orWhereContains('trailer_plate', $search)
                ->when(Str::isUuid($search) ? Str::lower($search) : null, fn (Builder $query, string $uuid): Builder => $query->orWhere('uuid', $uuid)))
            ->union($purchaseOrders);
    }

    /**
     * Whole calendar days in the viewer's timezone, as a half-open UTC range so
     * the last day counts up to its final second. A check-in that never reached
     * the chosen date (not completed, not synced) has a null column and drops out.
     *
     * @param  Builder<CheckIn>  $query
     * @return Builder<CheckIn>
     */
    private function withinDays(Builder $query, CheckInDateField $field, string $from, string $to, string $timezone): Builder
    {
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay();
        $end = CarbonImmutable::parse($to, $timezone)->startOfDay();

        return $query
            ->where($field->column(), '>=', $start->utc())
            ->where($field->column(), '<', $end->addDay()->utc());
    }
}
