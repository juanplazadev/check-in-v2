<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\UserRecordResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UserIndexRequest;
use App\Queries\DashboardUsers;
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

    public function show(string $uuid, DashboardUsers $query, UserRecordResolver $resolver): Response
    {
        return inertia('Dashboard/Users/Show', [
            'user' => $resolver->record($query->find($uuid)),
        ]);
    }
}
