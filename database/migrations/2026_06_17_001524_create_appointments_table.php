<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference_number', 8)->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Assigned by staff after booking; null until then.
            $table->foreignId('dock_id')->nullable()->constrained('docks')->nullOnDelete();
            $table->timestampTz('dock_assigned_at')->nullable();
            $table->foreignId('dock_assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('scheduled_for');
            // Null at a location with no load types; the booking then lasts one slot.
            $table->foreignId('load_type_id')->nullable()->constrained('load_types')->nullOnDelete();
            // scheduled_for plus the load type's duration at booking time, so a later
            // change to the load type never moves or stretches existing bookings.
            $table->timestampTz('ends_at');
            $table->string('drivers_name');
            $table->string('drivers_cellphone');
            $table->string('locale', 2)->default('en');
            $table->string('status')->default('scheduled');
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancelled_reason')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->string('claimed_via')->nullable();
            // Stamped when the day-before reminder is queued, and the only thing
            // stopping the hourly command sending it twice. If rescheduling is
            // ever built, moving scheduled_for must null this or the booking
            // silently loses its reminder.
            $table->timestampTz('reminder_sent_at')->nullable();

            $table->timestampsTz();
            $table->softDeletes();

            // Serves the capacity check, which looks for bookings overlapping a time range.
            $table->index(['location_id', 'scheduled_for', 'ends_at']);
            $table->index(['drivers_cellphone', 'created_at']);
            // Serves the driver history list, which filters by user and sorts
            // by scheduled_for. Covers plain user_id lookups as a leftmost prefix.
            $table->index(['user_id', 'scheduled_for']);
            // Serves the hourly reminder sweep, which filters on exactly this
            // pair. Neither index above works for it as a leftmost prefix.
            $table->index(['status', 'scheduled_for']);
            // Serves the "is this dock free for this time range" check.
            $table->index(['dock_id', 'scheduled_for', 'ends_at']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // The dashboard table orders by scheduled_for, id and leaves out (or, on the archived
        // tab, keeps only) soft-deleted rows. These partial indexes match each of its shapes,
        // so a page is an index walk rather than a sort over the whole table.
        DB::statement('create index appointments_dashboard_live_index on appointments (scheduled_for desc, id desc) where deleted_at is null');
        DB::statement('create index appointments_dashboard_status_index on appointments (status, scheduled_for desc, id desc) where deleted_at is null');
        DB::statement('create index appointments_dashboard_location_index on appointments (location_id, scheduled_for desc, id desc) where deleted_at is null');
        DB::statement('create index appointments_dashboard_archived_index on appointments (scheduled_for desc, id desc) where deleted_at is not null');

        // The dashboard table search is a case-insensitive "contains", which only a trigram
        // index can serve. pg_trgm is enabled in the purchase_orders migration.
        foreach (['reference_number', 'drivers_name', 'drivers_cellphone'] as $column) {
            DB::statement("create index appointments_{$column}_trgm_index on appointments using gin ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
