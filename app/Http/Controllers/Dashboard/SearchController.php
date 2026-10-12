<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SearchRequest;
use App\Models\User;
use App\Queries\DashboardSearch;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Backs the header search modal. Plain JSON rather than an Inertia visit, since
 * the modal fetches as the user types and never changes the page.
 */
final class SearchController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, SearchRequest $request, DashboardSearch $search): JsonResponse
    {
        return response()->json($search->execute($user, $request->term()));
    }
}
