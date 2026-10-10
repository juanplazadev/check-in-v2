<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\CheckInRecordResolver;
use App\Enums\CheckInStatus;
use App\Models\CheckIn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The staff check-ins table: every check-in, newest first, filtered on the
 * server so the page never ships more than one page of rows.
 */
final readonly class DashboardCheckIns
{
    public const int PER_PAGE = 25;

    /**
     * @param  array{search: string|null, status: string|null, locations: list<int>}  $filters
     * @return LengthAwarePaginator<int, CheckIn>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->when($filters['status'], fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->with(CheckInRecordResolver::RELATIONS)
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Per-status totals for the tabs. Search and locations apply, status does
     * not, so each tab shows what clicking it would find.
     *
     * @param  array{search: string|null, status: string|null, locations: list<int>}  $filters
     * @return array<string, int>
     */
    public function statusCounts(array $filters): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->filtered($filters)
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $byStatus = [];

        foreach (CheckInStatus::cases() as $status) {
            $byStatus[$status->value] = $counts[$status->value] ?? 0;
        }

        return ['all' => array_sum($byStatus), ...$byStatus];
    }

    /**
     * @param  array{search: string|null, status: string|null, locations: list<int>}  $filters
     * @return Builder<CheckIn>
     */
    private function filtered(array $filters): Builder
    {
        return CheckIn::query()
            ->when($filters['locations'], fn (Builder $query, array $locations): Builder => $query->whereIn('location_id', $locations))
            ->when($filters['search'], fn (Builder $query, string $search): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->whereLike('reference_number', "%{$search}%")
                    ->orWhereLike('drivers_name', "%{$search}%")
                    ->orWhereLike('customer', "%{$search}%")
                    ->orWhereLike('truck_plate', "%{$search}%")
                    ->orWhereLike('trailer_plate', "%{$search}%")
                    ->orWhereHas('purchaseOrders', fn (Builder $query): Builder => $query->whereLike('number', "%{$search}%")),
            ));
    }
}
