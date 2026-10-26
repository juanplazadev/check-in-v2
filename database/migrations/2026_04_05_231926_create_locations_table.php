<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('address_id')->constrained('addresses')->cascadeOnDelete();
            $table->integer('max_distance_allowed');
            $table->string('name');
            $table->string('abbreviation')->unique();
            $table->string('timezone');
            $table->string('phone');
            $table->string('phone_ext')->nullable();
            $table->string('email');
            $table->boolean('is_active');
            $table->boolean('is_checkins_enabled');
            $table->timestampTz('checkins_paused_at')->nullable();
            $table->timestampTz('checkins_paused_until')->nullable();
            $table->string('checkins_paused_reason')->nullable();
            $table->boolean('is_appointments_enabled');
            $table->timestampTz('appointments_paused_at')->nullable();
            $table->timestampTz('appointments_paused_until')->nullable();
            $table->string('appointments_paused_reason')->nullable();
            $table->json('config')->nullable();

            $table->timestampsTz();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
