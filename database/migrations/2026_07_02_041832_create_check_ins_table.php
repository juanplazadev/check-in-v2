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
        Schema::create('check_ins', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference_number', 8)->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('appointment_linked_at')->nullable();
            $table->string('appointment_linked_via')->nullable();
            $table->foreignId('appointment_linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('erp_status');
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancelled_reason')->nullable();
            $table->timestampTz('erp_synced_at')->nullable();
            $table->string('erp_synced_via')->nullable();
            $table->foreignId('erp_synced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer');
            $table->string('destination_city');
            $table->string('destination_state');
            $table->string('destination_country');
            $table->string('truck_name');
            $table->string('truck_plate');
            $table->string('truck_color')->nullable();
            $table->string('truck_plate_state')->nullable();
            $table->string('truck_plate_country')->nullable();
            $table->string('trailer_plate');
            $table->string('trailer_plate_state')->nullable();
            $table->string('trailer_plate_country')->nullable();
            $table->string('trailer_chute')->nullable();
            $table->integer('empty_weight_lbs')->nullable();
            $table->string('drivers_name');
            $table->string('drivers_cellphone');
            $table->string('drivers_email')->nullable();
            $table->text('drivers_license_number');
            $table->string('drivers_license_hash', 64)->nullable()->index();
            $table->date('drivers_license_expiration_date')->nullable();
            $table->string('drivers_license_state')->nullable();
            $table->string('drivers_license_country')->nullable();
            $table->text('loading_instructions')->nullable();
            $table->string('locale', 2)->default('en');
            $table->timestampTz('claimed_at')->nullable();
            $table->string('claimed_via')->nullable();

            $table->timestampsTz();
            $table->softDeletes();

            $table->index('location_id');
            $table->index(['drivers_cellphone', 'created_at']);
            // Serves the driver history list, which filters by user and sorts
            // by created_at. Covers plain user_id lookups as a leftmost prefix.
            $table->index(['user_id', 'created_at']);
            // Serve the dashboard table's date range filter, one per filterable date.
            $table->index('created_at');
            $table->index('completed_at');
            $table->index('erp_synced_at');
            // Lets the dashboard's grouped tab counts read the index alone, not the table.
            $table->index(['status', 'deleted_at']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // The dashboard table orders by created_at, id and leaves out (or, on the archived
        // tab, keeps only) soft-deleted rows. These partial indexes match each of its shapes,
        // so a page is an index walk rather than a sort over the whole table.
        DB::statement('create index check_ins_dashboard_live_index on check_ins (created_at desc, id desc) where deleted_at is null');
        DB::statement('create index check_ins_dashboard_status_index on check_ins (status, created_at desc, id desc) where deleted_at is null');
        DB::statement('create index check_ins_dashboard_location_index on check_ins (location_id, created_at desc, id desc) where deleted_at is null');
        DB::statement('create index check_ins_dashboard_archived_index on check_ins (created_at desc, id desc) where deleted_at is not null');

        // The table search is a case-insensitive "contains", which only a trigram index can
        // serve. pg_trgm is enabled in the purchase_orders migration.
        foreach (['reference_number', 'drivers_name', 'customer', 'truck_plate', 'trailer_plate'] as $column) {
            DB::statement("create index check_ins_{$column}_trgm_index on check_ins using gin ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
