<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserHistoryEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Replaces the temporary password an admin set, which releases the user from
 * the change page (see EnsurePasswordIsChanged).
 */
final readonly class ChangeRequiredPasswordAction
{
    public function __construct(private RecordUserHistoryAction $recordUserHistory) {}

    /**
     * @throws Throwable
     */
    public function handle(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
                'password_change_required_at' => null,
            ])->save();

            $this->recordUserHistory->handle($user, UserHistoryEvent::PasswordUpdated);
        });
    }
}
