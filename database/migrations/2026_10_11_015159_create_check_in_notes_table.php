<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_in_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('check_in_id')->constrained()->cascadeOnDelete();
            // Nullable so a note outlives its author's account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');

            $table->timestampsTz();

            // Serves the notes card: every read is "this check-in, newest first".
            $table->index(['check_in_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_in_notes');
    }
};
