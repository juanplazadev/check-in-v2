<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\RecordUserHistoryAction;
use App\Enums\UserHistoryEvent;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Throwable;

final readonly class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(private RecordUserHistoryAction $history) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string|null>  $input
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(User $user, array $input): void
    {
        $this->assertPasswordRecentlyConfirmed();

        $input['cellphone'] = $this->normalizeCellphone($input['cellphone'] ?? null);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],

            'cellphone' => ['nullable', 'string', 'regex:/^\+1\d{10}$/'],

            'drivers_license_number' => ['nullable', 'string', 'min:4', 'max:20'],
            'drivers_license_state' => ['nullable', 'string', 'min:2', 'max:30'],
            'drivers_license_country' => ['nullable', 'required_with:drivers_license_state', 'string', 'size:2', 'alpha'],
            'drivers_license_expiration_date' => ['nullable', 'date_format:Y-m-d', 'after:today', 'before:+60 years'],
        ], [
            'cellphone.regex' => __('messages.accountProfile.cellphoneInvalid'),
            'drivers_license_number.min' => __('messages.accountProfile.licenseNumberInvalid'),
            'drivers_license_number.max' => __('messages.accountProfile.licenseNumberInvalid'),
            'drivers_license_country.required_with' => __('messages.accountProfile.licenseCountryRequired'),
            'drivers_license_country.size' => __('messages.accountProfile.licenseCountryInvalid'),
            'drivers_license_country.alpha' => __('messages.accountProfile.licenseCountryInvalid'),
            'drivers_license_expiration_date.after' => __('messages.accountProfile.licenseExpirationPast'),
            'drivers_license_expiration_date.before' => __('messages.accountProfile.licenseExpirationTooFar'),
        ])->validateWithBag('updateProfileInformation');

        $license = $this->normalizeLicense($input);

        $before = $this->snapshot($user);

        $requiresReverification = $input['email'] !== $user->email;

        DB::transaction(function () use ($user, $input, $license, $before, $requiresReverification): void {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'cellphone' => $input['cellphone'],
                ...$license,
                ...($requiresReverification ? ['email_verified_at' => null] : []),
            ])->save();

            $changes = $this->diff($before, $this->snapshot($user));

            if ($changes !== []) {
                $this->history->handle($user, UserHistoryEvent::ProfileUpdated, $changes);
            }
        });

        // Dispatched after the commit on purpose: VerifyEmail is queued on Redis,
        // so a worker could pick the job up and mail a link for an email address
        // whose row never landed.
        if ($requiresReverification) {
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * Refuse the update unless the password was confirmed recently.
     *
     * Enforced here rather than as route middleware because Fortify hardcodes
     * `['auth']` on PUT /user/profile-information (vendor routes.php) and
     * exposes no confirmPassword option for it, the way it does for two-factor
     * and passkeys. Gating only the GET page would leave the endpoint itself
     * open to a direct request from a hijacked session - which is the whole
     * thing being defended against, since changing the email here is a complete
     * account-takeover path via a password reset to the new address.
     *
     * Same session key and timeout the framework's RequirePassword middleware
     * uses, so confirming once at the profile page covers this too.
     *
     * @throws ValidationException
     */
    private function assertPasswordRecentlyConfirmed(): void
    {
        $confirmedAt = session('auth.password_confirmed_at');

        $isFresh = is_int($confirmedAt)
            && Date::now()->unix() - $confirmedAt < (int) config('auth.password_timeout', 10800);

        if ($isFresh) {
            return;
        }

        throw ValidationException::withMessages([
            'password' => __('messages.confirmPassword.required'),
        ]);
    }

    /**
     * Capture the trackable profile attributes as plain, comparable scalars.
     *
     * This reads through the accessors on purpose — getDirty()/getChanges()
     * can't be used here. Encrypted::set() produces different ciphertext on
     * every write and Eloquent only decrypts-to-compare for its own built-in
     * `encrypted` cast, so drivers_license_number would report dirty on every
     * single save. The expiration date is normalized to its stored Y-m-d shape
     * because the cast hands back a CarbonImmutable.
     *
     * @return array<string, string|null>
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'cellphone' => $user->cellphone,
            'drivers_license_number' => $user->drivers_license_number,
            'drivers_license_state' => $user->drivers_license_state,
            'drivers_license_country' => $user->drivers_license_country,
            'drivers_license_expiration_date' => $user->drivers_license_expiration_date?->format('Y-m-d'),
        ];
    }

    /**
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     * @return array<string, array{from: string|null, to: string|null}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $attribute => $value) {
            if ($before[$attribute] !== $value) {
                $changes[$attribute] = ['from' => $before[$attribute], 'to' => $value];
            }
        }

        return $changes;
    }

    /**
     * Normalize a US cellphone to the "+1XXXXXXXXXX" shape used across the app,
     * or null when empty. Matches the check-in form so the number can prefill.
     */
    private function normalizeCellphone(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : '+1'.$digits;
    }

    /**
     * Coerce the optional license fields to their stored shape: trimmed values
     * or null when blank, so clearing a field wipes it. The country only means
     * something alongside a state, so clearing the state wipes it too.
     *
     * @param  array<string, string|null>  $input
     * @return array{drivers_license_number: string|null, drivers_license_state: string|null, drivers_license_country: string|null, drivers_license_expiration_date: string|null}
     */
    private function normalizeLicense(array $input): array
    {
        $blankToNull = static function (?string $value): ?string {
            $value = mb_trim((string) $value);

            return $value === '' ? null : $value;
        };

        $state = $blankToNull($input['drivers_license_state'] ?? null);
        $country = $blankToNull($input['drivers_license_country'] ?? null);

        return [
            'drivers_license_number' => $blankToNull($input['drivers_license_number'] ?? null),
            'drivers_license_state' => $state,
            'drivers_license_country' => $state === null || $country === null ? null : Str::upper($country),
            'drivers_license_expiration_date' => $blankToNull($input['drivers_license_expiration_date'] ?? null),
        ];
    }
}
