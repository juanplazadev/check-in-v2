<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Encrypted;
use App\Dashboard\MatchesSearchTerm;
use App\Enums\NotificationChannel;
use App\Enums\Role;
use App\Enums\Theme;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $name
 * @property-read string $email
 * @property-read CarbonImmutable|null $email_verified_at
 * @property-read string $password
 * @property-read string|null $cellphone
 * @property-read string|null $drivers_license_number
 * @property-read string|null $drivers_license_state
 * @property-read CarbonImmutable|null $drivers_license_expiration_date
 * @property-read string|null $locale
 * @property-read Theme|null $theme
 * @property-read array<string, mixed>|null $config
 * @property-read bool $notify_check_in_copy
 * @property-read bool $notify_appointment_copy
 * @property-read bool $notify_appointment_reminder
 * @property-read NotificationChannel $notification_channel
 * @property-read int|null $location_id
 * @property-read Location|null $location
 * @property-read Collection<int, Location> $locations
 * @property-read int|null $pending_check_in_id
 * @property-read int|null $pending_appointment_id
 * @property-read string|null $two_factor_secret
 * @property-read string|null $two_factor_recovery_codes
 * @property-read CarbonImmutable|null $two_factor_confirmed_at
 * @property-read string|null $remember_token
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read Collection<int, CheckIn> $checkIns
 * @property-read Collection<int, Appointment> $appointments
 */
#[Fillable(['name', 'email', 'password', 'cellphone', 'drivers_license_number', 'drivers_license_state', 'drivers_license_expiration_date', 'locale', 'theme', 'config', 'notify_check_in_copy', 'notify_appointment_copy', 'notify_appointment_reminder', 'notification_channel'])]
#[Hidden(['id', 'password', 'remember_token', 'drivers_license_number', 'location_id'])]
final class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use MatchesSearchTerm;
    use Notifiable;

    /**
     * Defaults for the optional profile columns so a freshly-built model always
     * exposes them (as null) under strict attribute access, matching a row
     * loaded from the database.
     *
     * The notification preferences carry real defaults rather than null: the
     * column defaults are the source of truth, and these mirror them so an
     * unsaved model answers the same as a stored one.
     *
     * @var array<string, mixed>
     */
    #[Override]
    protected $attributes = [
        // Listed for the same reason as the rest: hasVerifiedEmail() is now on
        // the hot path via the "verified" middleware, and a freshly-created
        // model that never round-tripped through the database would otherwise
        // throw on the missing attribute rather than answer "not verified".
        'email_verified_at' => null,
        'cellphone' => null,
        'drivers_license_number' => null,
        'drivers_license_state' => null,
        'drivers_license_expiration_date' => null,
        'locale' => null,
        'theme' => null,
        'config' => null,
        'notify_check_in_copy' => true,
        'notify_appointment_copy' => true,
        'notify_appointment_reminder' => true,
        'notification_channel' => NotificationChannel::Email->value,
        'location_id' => null,
    ];

    /**
     * The location the driver books appointments at by default.
     *
     * Nullable, and the row it points at may since have been soft-deleted or had
     * appointments disabled, so callers must resolve it through
     * {@see \App\Queries\AppointmentLocations} rather than trusting the relation.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The locations a staff member works at. Only the location-scoped roles are
     * limited by it; see {@see self::dashboardLocationIds()}.
     *
     * Not to be confused with {@see self::location()}, a driver's booking
     * default.
     *
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->withTimestamps();
    }

    /**
     * The locations whose check-ins and appointments this user may see on the
     * staff dashboard, or null for every location.
     *
     * Any all-locations role wins, so an admin who also holds shipping is not
     * narrowed by it. Everyone else gets their attached locations, which fails
     * closed: a shipping user attached to nothing sees nothing.
     *
     * @return list<int>|null
     */
    public function dashboardLocationIds(): ?array
    {
        if ($this->hasAnyRole(Role::allLocations())) {
            return null;
        }

        return array_values($this->locations()->pluck('locations.id')->map(intval(...))->all());
    }

    /**
     * A null column means the driver has never chosen, which behaves the same as
     * explicitly following the operating system.
     */
    public function resolvedTheme(): Theme
    {
        return $this->theme ?? Theme::System;
    }

    /**
     * The notification channels this driver's copies should go out on.
     *
     * A text needs a number on file. Falling back to email rather than
     * returning nothing means a driver who picked Text before adding their
     * number still gets the confirmation instead of it vanishing silently.
     *
     * @return list<string>
     */
    public function notificationChannels(): array
    {
        if ($this->cellphone === null) {
            return ['mail'];
        }

        return match ($this->notification_channel) {
            NotificationChannel::Email => ['mail'],
            NotificationChannel::Sms => [SmsChannel::class],
            NotificationChannel::Both => ['mail', SmsChannel::class],
        };
    }

    /**
     * Generate a uuid for the public identifier only. The auto-incrementing id
     * stays the primary key: HasUuids treats the key as a string only when it
     * is listed here.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The private channel for broadcast notifications, keyed by uuid so the
     * channel name the browser subscribes to does not reveal the id.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'App.Models.User.'.$this->uuid;
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->cellphone;
    }

    /**
     * @return HasMany<CheckIn, $this>
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Send the (queued) email verification notification in the driver's
     * current locale, captured now so the worker renders the right language.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify((new VerifyEmail)->locale(app()->getLocale()));
    }

    /**
     * Send the (queued) password reset notification in the driver's current
     * locale, captured now so the worker renders the right language.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token)->locale(app()->getLocale()));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'drivers_license_number' => Encrypted::class,
            'drivers_license_expiration_date' => 'date:Y-m-d',
            'theme' => Theme::class,
            'config' => 'array',
            'notify_check_in_copy' => 'boolean',
            'notify_appointment_copy' => 'boolean',
            'notify_appointment_reminder' => 'boolean',
            'notification_channel' => NotificationChannel::class,
        ];
    }
}
