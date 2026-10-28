<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docks', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            // Optional: a single-building site keeps its docks ungrouped. Removing a building ungroups its docks.
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            // An active dock can still be kept out of the booking capacity, e.g. one reserved for walk-ins.
            $table->boolean('accepts_appointments')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestampsTz();

            // Door numbers are unique across the whole site, not per building.
            $table->unique(['location_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docks');
    }
};
