<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The locations a staff member works at. Only the location-scoped roles
     * (see App\Enums\Role::locationScoped()) read it; admin and sales see every
     * location regardless of what is attached here.
     */
    public function up(): void
    {
        Schema::create('location_user', function (Blueprint $table): void {
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['location_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_user');
    }
};
