<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestampsTz();

            // A campus names each building once, e.g. "Building A" and "Building B".
            $table->unique(['location_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
