<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Queries\LiveQueueCheckIns;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;
use Override;

final class HandleInertiaRequests extends Middleware
{
    private const int NOTIFICATIONS_SHOWN = 10;

    private const array PUBLIC_NAV_ROUTES = [
        'appointment.selectLocation',
        'appointment.form',
        'appointment.confirmed',
        'checkIn.selectLocation',
        'checkIn.form',
        'account',
        'account.profile',
        'account.settings',
        'account.history',
        'account.history.checkIn',
        'account.history.appointment',
        // 'checkIn.*',
        // 'appointment.*',
    ];

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    #[Override]
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'commit' => $this->getShortCommit(...),
            'auth' => [
                'user' => $request->user(...),
                'roles' => fn (): array => $request->user()?->getRoleNames()->all() ?? [],
            ],
            'dashboard' => $this->getDashboardCounts(...),
            'notifications' => $this->getNotifications(...),
            'currentRoute' => $this->getCurrentRouteName(...),
            'hasLicense' => $this->getHasLicense(...),
            'userCoordsBrowserTtl' => $this->getUserCoordsBrowserTtl(...),
            'currentLocale' => fn () => app()->getLocale(),
            'localesAvailable' => fn () => config('app.locales'),
            'localesLabels' => fn () => config('app.locales_labels'),
        ];
    }

    /**
     * The short (7-char) deployed commit SHA, or null in local dev.
     */
    private function getShortCommit(): ?string
    {
        if (request()->route()?->getName() !== 'home') {
            return null;
        }

        $commit = config('app.commit');

        return is_string($commit) && $commit !== '' ? mb_substr($commit, 0, 7) : null;
    }

    private function getCurrentRouteName(): ?string
    {
        return in_array(
            request()->route()?->getName(),
            self::PUBLIC_NAV_ROUTES,
            strict: true
        ) ? request()->route()->getName() : null;
    }

    /**
     * Drives the "License on file" chip in the account identity header, which
     * AccountLayout renders on every account page. Shared rather than passed
     * per-controller-method so a new account page cannot silently lose it.
     */
    private function getHasLicense(): ?bool
    {
        $routeName = request()->route()?->getName();

        if ($routeName === null || ! str_starts_with($routeName, 'account')) {
            return null;
        }

        return request()->user()?->drivers_license_number !== null;
    }

    /**
     * Sidebar badge counts. Only the dashboard renders the sidebar, and the
     * route group in front of it already requires a staff role. The live feed
     * reloads this prop alone whenever a check-in arrives or changes.
     *
     * @return array{pendingCheckIns: int}|null
     */
    private function getDashboardCounts(): ?array
    {
        if (! str_starts_with((string) request()->route()?->getName(), 'dashboard')) {
            return null;
        }

        return [
            'pendingCheckIns' => resolve(LiveQueueCheckIns::class)->count(),
        ];
    }

    /**
     * The header notification bell. Dashboard routes only, like the counts
     * above; the bell reloads this prop alone when a notification is broadcast.
     *
     * @return array{unreadCount: int, items: list<array{id: string, type: string, data: array<string, mixed>, readAt: string|null, createdAt: string}>}|null
     */
    private function getNotifications(): ?array
    {
        $user = request()->user();

        if ($user === null || ! str_starts_with((string) request()->route()?->getName(), 'dashboard')) {
            return null;
        }

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'items' => array_values($user->notifications()
                ->limit(self::NOTIFICATIONS_SHOWN)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'readAt' => $notification->read_at?->toIso8601String(),
                    'createdAt' => (string) $notification->created_at?->toIso8601String(),
                ])
                ->all()),
        ];
    }

    private function getUserCoordsBrowserTtl(): ?int
    {
        return request()->route()?->getName() === 'checkIn.selectLocation' ?
            config('app.user_coordinates_browser_ttl') : null;
    }
}
