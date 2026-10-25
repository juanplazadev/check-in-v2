<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\UserHistoryEntryResolver;
use App\Dashboard\UserRecordResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UserIndexRequest;
use App\Http\Requests\Dashboard\UserShowRequest;
use App\Models\User;
use App\Queries\DashboardUsers;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class UserController extends Controller
{
    public function index(UserIndexRequest $request, DashboardUsers $query, UserRecordResolver $resolver): Response
    {
        $filters = $request->filters();

        return inertia('Dashboard/Users/Index', [
            'users' => $query->execute($filters)->through($resolver->record(...)),
            'typeCounts' => $query->typeCounts($filters),
            'filters' => $filters,
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
}
