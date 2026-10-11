<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\CheckInExportScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInExportRequest;
use App\Jobs\ExportCheckInsJob;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Check-in spreadsheets for admins and shipping managers (the route group
 * enforces the roles). Asking for one queues {@see ExportCheckInsJob}, which
 * emails the file to the requester.
 */
final class CheckInExportController extends Controller
{
    public function store(#[CurrentUser] User $user, CheckInExportRequest $request): Response
    {
        $scope = $request->scope();
        $filters = $request->filters();
        $stamp = Date::now($filters['timezone'])->format('Y-m-d-Hi');

        ExportCheckInsJob::dispatch(
            $user,
            $scope,
            $filters,
            $request->columns(),
            (string) Str::uuid(),
            $scope === CheckInExportScope::All ? "check-ins-master-{$stamp}.xlsx" : "check-ins-{$stamp}.xlsx",
        );

        return response()->noContent(202);
    }
}
