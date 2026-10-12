<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\RecordHistoryAction;
use App\Encryption\BlindIndex;
use App\Enums\RecordHistoryEvent;
use App\Events\AppointmentUpdated;
use App\Events\CheckInUpdated;
use App\Models\Appointment;
use App\Models\AppointmentSchedule;
use App\Models\AppointmentScheduleOverride;
use App\Models\CheckIn;
use App\Models\CheckInSchedule;
use App\Models\CheckInScheduleOverride;
use App\Models\Location;
use App\Models\User;
use App\Pdf\BrowsershotRecordPdfRenderer;
use App\Pdf\RecordPdfRenderer;
use App\Reference\RandomCode;
use App\Reference\SecureRandomCode;
use App\Schedule\WeeklyScheduleResolver;
use App\Sms\LogSmsSender;
use App\Sms\SmsSender;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerDatabaseEncrypter();

        // bind, not singleton: Octane keeps singletons across requests.
        $this->app->bind(RecordPdfRenderer::class, BrowsershotRecordPdfRenderer::class);

        /*
         * No carrier account yet, so texts are logged rather than sent. FlowRoute
         * lands as a second SmsSender implementation and one changed line here.
         * Note that Mail::alwaysTo() has no SMS equivalent, so whoever wires the
         * carrier up needs a non-production guard of their own.
         */
        $this->app->bind(SmsSender::class, LogSmsSender::class);

        /*
         * Reference numbers and short link codes both draw from here. Bound so
         * a test can script the draw and force the collision each generator
         * guards against, which is otherwise too rare to cover.
         */
        $this->app->bind(RandomCode::class, SecureRandomCode::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevCommands();
    }

    /**
     * Under Sail the containers already run Octane, Horizon and Reverb, so
     * `artisan dev` only starts Vite and Pail.
     */
    private function configureDevCommands(): void
    {
        if (config('app.sail')) {
            DevCommands::only('vite', 'logs');
        }
    }

    /**
     * Bind a dedicated encrypter for at-rest column encryption, keyed by
     * DB_ENCRYPTION_KEY rather than APP_KEY so the two can rotate independently.
     */
    private function registerDatabaseEncrypter(): void
    {
        $this->app->singleton('db.encrypter', function (): Encrypter {
            $encrypter = new Encrypter($this->databaseEncryptionKey(), config('app.cipher'));

            $previousKeys = array_map(
                $this->parseEncryptionKey(...),
                config('app.db_encryption_previous_keys', []),
            );

            return $previousKeys === [] ? $encrypter : $encrypter->previousKeys($previousKeys);
        });

        // A subkey, so the hash key is never the encryption key itself.
        $this->app->singleton(BlindIndex::class, fn (): BlindIndex => new BlindIndex(
            hash_hkdf('sha256', $this->databaseEncryptionKey(), 32, 'blind-index'),
        ));
    }

    /**
     * The raw bytes of DB_ENCRYPTION_KEY, failing loudly when it is unset.
     */
    private function databaseEncryptionKey(): string
    {
        $key = (string) config('app.db_encryption_key');

        throw_if($key === '', RuntimeException::class, 'DB_ENCRYPTION_KEY is not set. Generate one with: php -r "echo \'base64:\'.base64_encode(random_bytes(32));"');

        return $this->parseEncryptionKey($key);
    }

    /**
     * Decode a "base64:"-prefixed key into its raw bytes, mirroring how Laravel
     * parses APP_KEY.
     */
    private function parseEncryptionKey(string $key): string
    {
        return str_starts_with($key, 'base64:')
            ? (string) base64_decode(mb_substr($key, 7), true)
            : $key;
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    private function configureDefaults(): void
    {
        $this->immutableDates();

        $this->defaultTimezone();

        // TODO: Enable once in prod
        // $this->prohibitDestructiveCommands();

        $this->setPasswordDefault();

        $this->aggressivePrefetching();

        $this->autoEagerLoadRelationships();

        $this->forceHttps();

        $this->redirectMailOutsideProduction();

        $this->strictModels();

        $this->unguardModels();

        $this->enforceMorphMaps();

        $this->flushScheduleCacheOnDataChanges();

        $this->recordStatusTransitions();
        $this->broadcastCheckInStatusChanges();
        $this->broadcastAppointmentChanges();
    }

    /*
     * Deliberately no event listener registration here.
     *
     * Laravel discovers anything in app/Listeners whose method name starts with
     * "handle" and typehints an event, so registering one explicitly as well
     * makes it fire twice. AttachPendingRecordsToUser was registered here and
     * ran twice on every verification; it survived only because the claim is
     * guarded by whereNull('user_id') and the second pass matched no rows.
     *
     * RecordNotificationHistory is picked up the same way, which is also why a
     * new notification joins the trail without touching this file.
     */

    /**
     * Record status transitions on the records themselves, so the ERP sync gets
     * its history for free rather than having to remember to write it.
     *
     * Model events do not fire for mass Query::update(), which is how
     * ClaimPendingRecordsAction writes - that one records explicitly, and any
     * future bulk status update needs the same.
     */
    private function recordStatusTransitions(): void
    {
        // Only check-ins carry an ERP status; appointments have no such column.
        $models = [
            CheckIn::class => ['status', 'erp_status'],
            Appointment::class => ['status'],
        ];

        foreach ($models as $model => $columns) {
            $model::saved(static function (CheckIn|Appointment $record) use ($columns): void {
                foreach ($columns as $column) {
                    if (! $record->wasChanged($column)) {
                        continue;
                    }

                    // Read through getAttribute: the column is a variable here,
                    // and both sides may or may not have been cast to an enum.
                    $scalar = static fn (mixed $value): mixed => $value instanceof BackedEnum
                        ? $value->value
                        : $value;

                    // Credit the signed-in staff member who made the change;
                    // without one (queue jobs, guest flows) the record's own
                    // user stays the actor.
                    $actor = Auth::user();

                    resolve(RecordHistoryAction::class)->handle(
                        record: $record,
                        event: RecordHistoryEvent::StatusChanged,
                        subject: $column,
                        user: $actor instanceof User ? $actor : null,
                        context: [
                            'from' => $scalar($record->getOriginal($column)),
                            'to' => $scalar($record->getAttribute($column)),
                        ],
                    );
                }
            });
        }
    }

    /**
     * Push status and ERP status changes to open staff dashboards.
     *
     * Like {@see self::recordStatusTransitions()}, this misses mass
     * Query::update(); a future bulk status update has to dispatch
     * CheckInUpdated itself.
     */
    private function broadcastCheckInStatusChanges(): void
    {
        CheckIn::updated(static function (CheckIn $checkIn): void {
            if ($checkIn->wasChanged(['status', 'erp_status'])) {
                event(new CheckInUpdated($checkIn));
            }
        });
    }

    /**
     * Push status and time changes to the live queue's arriving rail, so a
     * cancelled, missed or arrived booking leaves it. Misses mass
     * Query::update() for the same reason as above.
     */
    private function broadcastAppointmentChanges(): void
    {
        Appointment::updated(static function (Appointment $appointment): void {
            if ($appointment->wasChanged(['status', 'scheduled_for'])) {
                event(new AppointmentUpdated($appointment));
            }
        });
    }

    /**
     * Invalidate the cached weekly schedule whenever any location or schedule
     * record changes, so admin edits are reflected on the next request.
     */
    private function flushScheduleCacheOnDataChanges(): void
    {
        $models = [
            Location::class,
            CheckInSchedule::class,
            CheckInScheduleOverride::class,
            AppointmentSchedule::class,
            AppointmentScheduleOverride::class,
        ];

        foreach ($models as $model) {
            $model::saved(static function (): void {
                WeeklyScheduleResolver::flushCache();
            });

            $model::deleted(static function (): void {
                WeeklyScheduleResolver::flushCache();
            });
        }
    }

    private function immutableDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function setPasswordDefault(): void
    {
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function aggressivePrefetching(): void
    {
        Vite::useAggressivePrefetching();
    }

    private function autoEagerLoadRelationships(): void
    {
        Model::automaticallyEagerLoadRelationships();
    }

    private function forceHttps(): void
    {
        if (app()->isProduction()) {
            URL::forceHttps();
        }
    }

    /**
     * Funnel every outgoing message to a single inbox outside production.
     *
     * This sits at the transport rather than in the seeder on purpose. The
     * location seeder carries the real @martorifarms.com shipping addresses
     * because production needs them, so scrubbing the data there would be both
     * wrong and incomplete: it would still leave registrations, password
     * resets, and verification mail free to reach whatever address a tester
     * typed in. Rewriting the recipient catches all of it in one place.
     */
    private function redirectMailOutsideProduction(): void
    {
        $address = config('mail.always_to');

        if (! is_string($address) || $address === '' || app()->isProduction()) {
            return;
        }

        Mail::alwaysTo($address);
    }

    private function strictModels(): void
    {
        if (! app()->isProduction()) {
            Model::shouldBeStrict();
        }
    }

    private function unguardModels(): void
    {
        Model::unguard();
    }

    private function defaultTimezone(): void
    {
        date_default_timezone_set(config('app.timezone'));
    }

    /**
     * CheckIn was missing here, so its polymorphic rows stored the class name
     * while appointments stored the alias. The record_history migration
     * realigns the purchase_orders rows written before this was fixed.
     */
    private function enforceMorphMaps(): void
    {
        Relation::morphMap([
            'appointment' => Appointment::class,
            'check_in' => CheckIn::class,
        ]);
    }
}
