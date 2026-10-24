<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Dashboard\LocationRecordResolver;
use App\Dashboard\LocationTimezones;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Closure;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The locations table and each section of a location's page. Every section
 * sends the same location summary for the shared header and nav
 * (LocationLayout), plus its own payload. The lookup goes through
 * LocationDirectory, so a location-scoped user gets a 404 for a location they
 * are not attached to.
 */
final class LocationController extends Controller
{
    public function __construct(
        private readonly LocationDirectory $query,
        private readonly LocationRecordResolver $resolver,
    ) {}

    public function index(#[CurrentUser] User $user): Response
    {
        return inertia('Dashboard/Locations/Index', [
            'locations' => $this->query->execute($user)->map($this->resolver->summary(...))->values()->all(),
            // The restore list, only for those who may restore.
            'deletedLocations' => $user->can(Permission::RestoreLocations)
                ? $this->query->deleted()->map($this->resolver->deleted(...))->values()->all()
                : [],
        ]);
    }

    public function show(#[CurrentUser] User $user, string $uuid): Response
    {
        return $this->section($user, $uuid, 'Overview', fn (Location $location): array => [
            'overview' => $this->resolver->overview($location, now()),
        ]);
    }

    public function details(#[CurrentUser] User $user, string $uuid, LocationTimezones $timezones): Response
    {
        return $this->section($user, $uuid, 'Details', fn (Location $location): array => [
            'details' => $this->resolver->details($location),
            // The edit form's options, only for those who may edit.
            'timezones' => $user->can(Permission::EditLocations) ? $timezones->for($location) : [],
        ]);
    }

    public function history(#[CurrentUser] User $user, string $uuid): Response
    {
        return $this->section($user, $uuid, 'History', fn (Location $location): array => [
            'history' => $this->resolver->history($location),
        ]);
    }

    public function users(#[CurrentUser] User $user, string $uuid): Response
    {
        return $this->section($user, $uuid, 'Users', fn (Location $location): array => [
            'users' => $this->resolver->users($location),
            // Loaded only when the add dialog opens, and only for those who may attach.
            'attachableUsers' => Inertia::optional(fn (): array => $user->can(Permission::ManageLocationUsers)
                ? $this->resolver->attachableUsers($location)
                : []),
        ]);
    }

    public function checkInHours(#[CurrentUser] User $user, string $uuid): Response
    {
        return $this->section($user, $uuid, 'CheckInHours', fn (Location $location): array => [
            'hours' => $this->resolver->checkInHours($location),
        ]);
    }

    public function checkInOptions(#[CurrentUser] User $user, string $uuid): Response
    {
        return $this->section($user, $uuid, 'CheckInOptions', fn (Location $location): array => [
            'options' => $this->resolver->checkInOptions($location),
        ]);
    }

    public function appointmentHours(#[CurrentUser] User $user, string $uuid): Response
    {
        return $this->section($user, $uuid, 'AppointmentHours', fn (Location $location): array => [
            'hours' => $this->resolver->appointmentHours($location),
        ]);
    }

    public function appointmentOptions(#[CurrentUser] User $user, string $uuid): Response
    {
        return $this->section($user, $uuid, 'AppointmentOptions', fn (Location $location): array => [
            'options' => $this->resolver->appointmentOptions($location),
        ]);
    }

    public function closures(#[CurrentUser] User $user, string $uuid): Response
    {
        return $this->section($user, $uuid, 'Closures', fn (Location $location): array => [
            'closures' => $this->resolver->closures($location, now()),
        ]);
    }

    /**
     * @param  Closure(Location): array<string, mixed>  $props
     */
    private function section(User $user, string $uuid, string $component, Closure $props): Response
    {
        $location = $this->query->find($user, $uuid);

        return inertia('Dashboard/Locations/'.$component, [
            'location' => $this->resolver->summary($location),
            ...$props($location),
        ]);
    }
}
