<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Role;
use App\Enums\UserAccessMethod;
use App\Enums\UserHistoryEvent;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates a staff account from the add user dialog: the user with their role,
 * attached to their locations, and either invited by email or given a
 * temporary password they must replace at first sign-in.
 *
 * The email counts as verified because an admin vouched for it. An invited
 * user gets an unguessable placeholder password until they accept. The
 * creation is logged on the admin's trail; each location attachment is
 * logged by AttachLocationUsersAction.
 */
final readonly class CreateStaffUserAction
{
    public function __construct(
        private AttachLocationUsersAction $attachUsers,
        private SendUserInvitationAction $sendInvitation,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @param  array{name: string, email: string, cellphone: string|null}  $details
     * @param  Collection<int, Location>  $locations
     *
     * @throws Throwable
     */
    public function handle(
        User $actor,
        array $details,
        Role $role,
        Collection $locations,
        UserAccessMethod $access,
        ?string $temporaryPassword = null,
    ): User {
        return DB::transaction(function () use ($actor, $details, $role, $locations, $access, $temporaryPassword): User {
            $user = User::query()->create([
                'name' => $details['name'],
                'email' => $details['email'],
                'cellphone' => $details['cellphone'],
                'password' => $access === UserAccessMethod::TemporaryPassword
                    ? (string) $temporaryPassword
                    : Str::password(64),
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'password_change_required_at' => $access === UserAccessMethod::TemporaryPassword ? now() : null,
            ])->save();

            $user->assignRole($role);

            $this->recordUserHistory->handle($actor, UserHistoryEvent::UserCreated, [
                'user' => $user->uuid,
                'role' => $role->value,
                'access' => $access->value,
            ]);

            foreach ($locations as $location) {
                $this->attachUsers->handle($location, [$user], $actor);
            }

            if ($access === UserAccessMethod::Invite) {
                $this->sendInvitation->handle($user, $actor);
            }

            return $user;
        });
    }
}
