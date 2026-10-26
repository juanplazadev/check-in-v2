<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\CreateStaffUserAction;
use App\Dashboard\NewUserOptions;
use App\Dashboard\UserHistoryEntryResolver;
use App\Dashboard\UserRecordResolver;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UserIndexRequest;
use App\Http\Requests\Dashboard\UserShowRequest;
use App\Http\Requests\Dashboard\UserStoreRequest;
use App\Models\User;
use App\Queries\DashboardUsers;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class UserController extends Controller
{
    /**
     * The add user dialog's roles and locations are optional props, loaded by
     * a partial reload when it opens rather than on every visit.
     */
    public function index(
        #[CurrentUser] User $viewer,
        UserIndexRequest $request,
        DashboardUsers $query,
        UserRecordResolver $resolver,
        NewUserOptions $options,
    ): Response {
        $filters = $request->filters();
        $canCreate = $viewer->can(Permission::CreateUsers);

        return inertia('Dashboard/Users/Index', [
            'users' => $query->execute($filters)->through($resolver->record(...)),
            'typeCounts' => $query->typeCounts($filters),
            'filters' => $filters,
            'newUserRoles' => Inertia::optional(fn (): array => $canCreate ? $options->roles() : []),
            'newUserLocations' => Inertia::optional(fn (): array => $canCreate ? $options->locations($viewer) : []),
        ]);
    }

    /**
     * A history page past the last one shows the empty state: entries are
     * never removed, so no page is left behind to redirect from.
     */
    public function show(
        #[CurrentUser] User $viewer,
        string $uuid,
        UserShowRequest $request,
        DashboardUsers $query,
        UserRecordResolver $resolver,
        UserHistoryEntryResolver $history,
    ): Response {
        $user = $query->find($uuid);

        return inertia('Dashboard/Users/Show', [
            'user' => $resolver->record($user),
            'history' => $history->page($user, $viewer),
        ]);
    }

    /**
     * The dialog stays open on success and shows what happened, so the new
     * user is flashed back rather than redirected to.
     *
     * @throws Throwable
     */
    public function store(#[CurrentUser] User $viewer, UserStoreRequest $request, CreateStaffUserAction $create): RedirectResponse
    {
        $access = $request->accessMethod();

        /** @var array{name: string, email: string, cellphone: string|null} $details */
        $details = $request->safe()->only(['name', 'email', 'cellphone']);

        $user = $create->handle(
            $viewer,
            $details,
            $request->role(),
            $request->locations(),
            $access,
            $request->validated('password'),
        );

        Inertia::flash('createdUser', [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'access' => $access->value,
        ]);

        return back();
    }
}
