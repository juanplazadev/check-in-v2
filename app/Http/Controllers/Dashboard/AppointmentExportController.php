<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\ExportScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentExportRequest;
use App\Jobs\ExportAppointmentsJob;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Appointment spreadsheets, like {@see CheckInExportController}. Asking for one
 * queues {@see ExportAppointmentsJob}, which emails the file to the requester.
 */
final class AppointmentExportController extends Controller
{
    public function store(#[CurrentUser] User $user, AppointmentExportRequest $request): Response
    {
        $scope = $request->scope();
        $filters = $request->filters();
        $stamp = Date::now($filters['timezone'])->format('Y-m-d-Hi');

        dispatch(new ExportAppointmentsJob($user, $scope, $filters, $request->columns(), (string) Str::uuid(), $scope === ExportScope::All ? "appointments-master-{$stamp}.xlsx" : "appointments-{$stamp}.xlsx"));

        return response()->noContent(202);
    }
}
