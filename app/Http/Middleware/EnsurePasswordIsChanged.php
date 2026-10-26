<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a user who still has the temporary password an admin set on the
 * change page. Signing out stays open, so they are never trapped.
 */
final readonly class EnsurePasswordIsChanged
{
    /**
     * Routes the held user may still reach.
     *
     * @var list<string>
     */
    private const array ALLOWED = ['password.change', 'password.change.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->mustChangePassword() || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(403, 'Choose a new password first.');
        }

        return to_route('password.change');
    }
}
