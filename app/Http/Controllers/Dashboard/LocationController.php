<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\LocationRecordResolver;
use App\Http\Controllers\Controller;
use App\Queries\AdminLocations;
use Inertia\Response;

final class LocationController extends Controller
{
    public function index(AdminLocations $query, LocationRecordResolver $resolver): Response
    {
        return inertia('Dashboard/Locations/Index', [
            'locations' => $query->execute()->map($resolver->summary(...))->values()->all(),
        ]);
    }

    public function show(string $uuid, AdminLocations $query, LocationRecordResolver $resolver): Response
    {
        return inertia('Dashboard/Locations/Show', [
            'location' => $resolver->record($query->find($uuid)),
        ]);
    }
}
