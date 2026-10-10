<?php

declare(strict_types=1);

namespace App\Queries;

use App\Dashboard\UserRecordResolver;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The admin users table and detail page: every account, newest first, filtered
 * on the server so the page never ships more than one page of rows.
 *
 * "Driver" means "holds no staff role" rather than "holds the driver role", so
 * an account that somehow lost its role still shows up somewhere.
 */
final readonly class DashboardUsers
{
    public const int PER_PAGE = 25;

    /**
     * @param  array{search: string|null, type: string|null, roles: list<string>}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        return $this->summarized($this->filtered($filters))
            ->when($filters['type'] === 'staff', fn (Builder $query): Builder => $query->role(Role::staff()))
            ->when($filters['type'] === 'driver', fn (Builder $query): Builder => $query->withoutRole(Role::staff()))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Totals for the tabs. Search and roles apply, type does not, so each tab
     * shows what clicking it would find.
     *
     * @param  array{search: string|null, type: string|null, roles: list<string>}  $filters
     * @return array{all: int, staff: int, driver: int}
     */
    public function typeCounts(array $filters): array
    {
        $all = $this->filtered($filters)->count();
        $staff = $this->filtered($filters)->role(Role::staff())->count();

        return ['all' => $all, 'staff' => $staff, 'driver' => $all - $staff];
    }

    /**
     * The newest accounts matching the header search, with the same matching
     * as the table's search box.
     *
     * @return Collection<int, User>
     */
    public function search(string $term, int $limit): Collection
    {
        return $this->filtered(['search' => $term, 'type' => null, 'roles' => []])
            ->latest()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['uuid', 'name', 'email']);
    }

    public function find(string $uuid): User
    {
        return $this->summarized(User::query())->where('users.uuid', $uuid)->firstOrFail();
    }

    /**
     * Everything {@see UserRecordResolver::record()} reads beyond the columns.
     * Last activity comes from the database session store, so it reads null
     * once a user's sessions have expired.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function summarized(Builder $query): Builder
    {
        return $query
            ->select('users.*')
            ->selectSub(
                fn (QueryBuilder $query): QueryBuilder => $query
                    ->from('sessions')
                    ->selectRaw('max(last_activity)')
                    ->whereColumn('sessions.user_id', 'users.id'),
                'last_activity',
            )
            ->with(UserRecordResolver::RELATIONS)
            ->withCount(['checkIns', 'appointments']);
    }

    /**
     * Search matches name, email and phone by substring, and a full UUID
     * exactly. The UUID is checked with Str::isUuid() first, since Postgres
     * errors on comparing a uuid column to anything else.
     *
     * @param  array{search: string|null, type: string|null, roles: list<string>}  $filters
     * @return Builder<User>
     */
    private function filtered(array $filters): Builder
    {
        $staffRoles = array_values(array_diff($filters['roles'], [Role::Driver->value]));
        $includesDrivers = in_array(Role::Driver->value, $filters['roles'], true);

        return User::query()
            ->when($filters['search'], fn (Builder $query, string $search): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->whereContains('name', $search)
                    ->orWhereContains('email', $search)
                    ->when(
                        preg_replace('/\D/', '', $search),
                        fn (Builder $query, string $digits): Builder => $query->orWhereContains('cellphone', $digits),
                    )
                    ->when(
                        Str::isUuid($search) ? Str::lower($search) : null,
                        fn (Builder $query, string $uuid): Builder => $query->orWhere('uuid', $uuid),
                    ),
            ))
            ->when($filters['roles'] !== [], fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->when($staffRoles !== [], fn (Builder $query): Builder => $query->role($staffRoles))
                    ->when($includesDrivers, fn (Builder $query): Builder => $query->orWhere(
                        fn (Builder $query): Builder => $query->withoutRole(Role::staff()),
                    )),
            ));
    }
}
