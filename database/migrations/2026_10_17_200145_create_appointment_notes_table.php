<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            // Nullable so a note outlives its author's account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');

            $table->timestampsTz();

            // Serves the notes card: every read is "this appointment, newest first".
            $table->index(['appointment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notes');
    }
};
