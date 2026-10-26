<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Passkeys\Passkeys;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // Set when an admin creates the account with a temporary password;
            // EnsurePasswordIsChanged holds the user on the change page until cleared.
            $table->timestamp('password_change_required_at')->nullable();
            $table->string('cellphone', 12)->nullable();
            $table->text('drivers_license_number')->nullable();
            $table->string('drivers_license_state')->nullable();
            $table->string('drivers_license_country', 2)->nullable();
            $table->date('drivers_license_expiration_date')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('theme')->nullable();
            // Per-account UI settings, shaped like locations.config (e.g. dashboard.check_ins.columns).
            $table->json('config')->nullable();
            /*
             * Notification preferences. Not nullable: an opt-out toggle has no
             * meaningful "never chosen" state the way locale and theme do, and
             * keeping them NOT NULL lets a future reminder job filter recipients
             * with a plain indexable where clause. Defaults live here so the
             * database is the source of truth; App\Models\User mirrors them in
             * $attributes so an unsaved model reads the same.
             */
            $table->boolean('notify_check_in_copy')->default(true);
            $table->boolean('notify_appointment_copy')->default(true);
            $table->boolean('notify_appointment_reminder')->default(true);
            // Email rather than both: a text costs money per send, and most
            // drivers have no number on file at signup, so opting in is deliberate.
            $table->string('notification_channel')->default('email');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedBigInteger('pending_check_in_id')->nullable();
            $table->unsignedBigInteger('pending_appointment_id')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Passkeys::userModel(), 'user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passkeys');
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
