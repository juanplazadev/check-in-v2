<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\LocationRecordResolver;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class LocationController extends Controller
{
    public function index(#[CurrentUser] User $user, LocationDirectory $query, LocationRecordResolver $resolver): Response
    {
        return inertia('Dashboard/Locations/Index', [
            'locations' => $query->execute($user)->map($resolver->summary(...))->values()->all(),
        ]);
    }

    public function show(#[CurrentUser] User $user, string $uuid, LocationDirectory $query, LocationRecordResolver $resolver): Response
    {
        return inertia('Dashboard/Locations/Show', [
            'location' => $resolver->record($query->find($user, $uuid)),
        ]);
    }
}
